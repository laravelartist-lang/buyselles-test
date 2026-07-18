<?php

namespace App\Services\DirectTopUp;

use App\Models\AdminWallet;
use App\Models\Cart;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\OrderTransaction;
use App\Models\SupplierOrder;
use App\Models\SupplierProductMapping;
use App\Services\Order\OrderFulfillmentStatusService;
use App\Services\Partner\PartnerOrderRefundService;
use App\Services\Supplier\SupplierManager;
use App\Services\Wallet\FailedWalletOrderRefundService;
use App\Utils\Convert;
use App\Utils\CustomerManager;
use App\Utils\OrderManager;
use Illuminate\Support\Collection;

class DirectTopUpWalletCheckoutService
{
    public function __construct(
        private readonly DirectTopUpService $directTopUpService,
        private readonly SupplierManager $supplierManager,
        private readonly FailedWalletOrderRefundService $walletRefundService,
        private readonly OrderFulfillmentStatusService $fulfillmentStatusService,
    ) {}

    /**
     * @param  Collection<int, Cart>|iterable<int, Cart>  $carts
     */
    public function requiresFulfillmentBeforePayment(iterable $carts): bool
    {
        foreach ($carts as $cart) {
            if ($cart->direct_topup_quantity === null || empty($cart->direct_topup_account_id)) {
                continue;
            }

            $product = $cart->product;
            if ($product === null || ! $this->directTopUpService->isDirectTopUpProduct($product)) {
                continue;
            }

            if (! $this->orderRequiresDirectTopUpFulfillmentForProduct((int) $product->id)) {
                continue;
            }

            return true;
        }

        return false;
    }

    /**
     * Fulfill direct top-up via supplier API, then charge the customer wallet on success.
     *
     * @param  int[]  $orderIds
     * @return array{success: bool, error: ?string, order_ids: int[]}
     */
    public function completeWalletPaymentAfterFulfillment(array $orderIds, int $userId, float $paymentAmount): array
    {
        $hasPending = false;
        $failureMessage = null;

        foreach ($orderIds as $orderId) {
            $order = $this->findOrderWithDirectTopUpDetails($orderId);

            if ($order === null || ! $this->orderRequiresDirectTopUpFulfillment($order)) {
                continue;
            }

            $result = $this->supplierManager->fulfillDirectTopUpOrder($order);

            if ($result['fulfilled']) {
                $this->markDirectTopUpOrderDelivered($order, $userId);

                continue;
            }

            if (($result['pending'] ?? false) && ($result['placed'] ?? false) && empty($result['error'])) {
                $hasPending = true;
                $this->markDirectTopUpOrderProcessing($order, $userId);

                continue;
            }

            $failureMessage = $this->formatFulfillmentError($result['error']);
            $this->cancelUnpaidCheckoutOrders($orderIds, $failureMessage, $userId);

            return [
                'success' => false,
                'error' => $failureMessage,
                'order_ids' => $orderIds,
                'pending' => false,
            ];
        }

        CustomerManager::create_wallet_transaction(
            $userId,
            Convert::default($paymentAmount),
            'order_place',
            'order payment',
            [],
            $orderIds
        );

        foreach ($orderIds as $orderId) {
            $order = Order::find($orderId);

            if ($order === null) {
                continue;
            }

            $order->payment_status = 'paid';
            $order->save();

            OrderDetail::where('order_id', $orderId)->update([
                'payment_status' => 'paid',
            ]);

            OrderManager::getWalletManageOnOrderStatusChange($order->fresh(), 'admin');
        }

        OrderManager::completeDeferredCheckout();

        return [
            'success' => true,
            'error' => null,
            'order_ids' => $orderIds,
            'pending' => $hasPending,
        ];
    }

    public static function isDirectTopUpAlreadyFulfilled(Order $order): bool
    {
        return SupplierOrder::query()
            ->where('order_id', $order->id)
            ->where('status', 'fulfilled')
            ->exists();
    }

    public function orderRequiresDirectTopUpFulfillment(Order $order): bool
    {
        $order->loadMissing(['orderDetails' => fn ($query) => $query->without('storage')]);

        foreach ($order->orderDetails as $detail) {
            if ($detail->direct_topup_quantity === null || empty($detail->direct_topup_account_id)) {
                continue;
            }

            $productId = $detail->product_id;
            if ($productId === null) {
                continue;
            }

            if (! $this->orderRequiresDirectTopUpFulfillmentForProduct((int) $productId)) {
                continue;
            }

            return true;
        }

        return false;
    }

    public function refundWalletForFailedDirectTopUp(Order $order): bool
    {
        return $this->walletRefundService->refundPaidWalletOrder(
            $order,
            'DirectTopUpWalletCheckoutService'
        );
    }

    public function markDirectTopUpOrderDelivered(Order $order, int $customerId): void
    {
        $this->fulfillmentStatusService->syncOrderFulfillmentStatus($order);
    }

    public function markDirectTopUpOrderProcessing(Order $order, int $customerId): void
    {
        Order::where('id', $order->id)->update([
            'order_status' => 'processing',
        ]);

        OrderDetail::where('order_id', $order->id)->update([
            'delivery_status' => 'processing',
            'payment_status' => 'unpaid',
        ]);

        OrderManager::add_order_status_history($order->id, $customerId, 'processing', 'admin');
    }

    public function markDirectTopUpOrderFailed(Order $order, string $error, int $customerId): void
    {
        $refunded = $order->payment_method === 'partner_wallet'
            ? app(PartnerOrderRefundService::class)->refundPaidPartnerOrder($order, 'DirectTopUpWalletCheckoutService')
            : $this->refundWalletForFailedDirectTopUp($order);

        $order->update([
            'order_status' => 'failed',
            'payment_status' => $refunded ? 'unpaid' : $order->payment_status,
            'order_note' => 'Direct top-up fulfillment failed: '.$error,
        ]);

        OrderDetail::where('order_id', $order->id)->update([
            'delivery_status' => 'canceled',
            'payment_status' => $refunded ? 'unpaid' : 'paid',
        ]);

        OrderManager::add_order_status_history($order->id, $customerId, 'failed', 'admin');
    }

    public function formatFulfillmentError(?string $error): string
    {
        if ($error === null || trim($error) === '') {
            return translate('direct_topup_fulfillment_failed');
        }

        if (preg_match("/^Product '[^']+': (.+)$/u", $error, $matches)) {
            return trim($matches[1]);
        }

        return trim($error);
    }

    /**
     * @param  int[]  $orderIds
     */
    private function cancelUnpaidCheckoutOrders(array $orderIds, string $error, int $customerId): void
    {
        OrderManager::discardDeferredCheckout();

        foreach ($orderIds as $orderId) {
            $order = Order::find($orderId);

            if ($order === null) {
                continue;
            }

            $order->update([
                'order_status' => 'canceled',
                'payment_status' => 'unpaid',
                'order_note' => 'Direct top-up failed: '.$error,
            ]);

            OrderDetail::where('order_id', $orderId)->update([
                'delivery_status' => 'canceled',
                'payment_status' => 'unpaid',
            ]);

            OrderManager::add_order_status_history($orderId, $customerId, 'canceled', 'admin');
            $this->reverseUnpaidOrderTransactionHold($order);
        }
    }

    private function reverseUnpaidOrderTransactionHold(Order $order): void
    {
        $transaction = OrderTransaction::where('order_id', $order->id)->first();

        if ($transaction === null || $transaction->status !== 'hold') {
            return;
        }

        AdminWallet::where('admin_id', 1)->decrement('pending_amount', (float) $order->order_amount);
        $transaction->delete();
    }

    private function findOrderWithDirectTopUpDetails(int $orderId): ?Order
    {
        return Order::query()
            ->with(['orderDetails' => fn ($query) => $query->without('storage')])
            ->find($orderId);
    }

    private function orderRequiresDirectTopUpFulfillmentForProduct(int $productId): bool
    {
        return SupplierProductMapping::query()
            ->where('product_id', $productId)
            ->where('is_active', true)
            ->whereHas('supplierApi', fn ($q) => $q->where('is_active', true)->where('supports_direct_top_up', true))
            ->exists();
    }
}
