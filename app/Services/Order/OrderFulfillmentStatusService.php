<?php

namespace App\Services\Order;

use App\Models\DigitalProductCode;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\SupplierOrder;
use App\Services\Supplier\SupplierOrderEligibilityService;
use App\Utils\OrderManager;
use Illuminate\Support\Facades\Schema;

class OrderFulfillmentStatusService
{
    /** @var string[] */
    private const TERMINAL_ORDER_STATUSES = [
        'delivered',
        'failed',
        'canceled',
        'returned',
    ];

    public function __construct(
        private readonly SupplierOrderEligibilityService $eligibilityService,
    ) {}

    /**
     * Re-evaluate and persist the correct order status after fulfillment progress.
     */
    public function syncOrderFulfillmentStatus(Order $order): void
    {
        $order = Order::query()
            ->with(['orderDetails' => fn ($query) => $query->without('storage')])
            ->find($order->id);

        if ($order === null) {
            return;
        }

        $targetStatus = $this->resolveTargetStatus($order);

        if ($targetStatus === null || $order->order_status === $targetStatus) {
            return;
        }

        $this->applyStatus($order, $targetStatus);
    }

    public function resolveTargetStatus(Order $order): ?string
    {
        if (in_array($order->order_status, self::TERMINAL_ORDER_STATUSES, true)) {
            return null;
        }

        if ($order->payment_status !== 'paid') {
            return null;
        }

        $evaluation = $this->evaluateFulfillment($order);

        if ($evaluation['fully_fulfilled']) {
            return 'delivered';
        }

        if (
            $evaluation['awaiting_supplier']
            || $evaluation['partially_fulfilled']
            || $evaluation['has_unfulfilled_digital_lines']
        ) {
            return 'processing';
        }

        return null;
    }

    /**
     * @return array{
     *     fully_fulfilled: bool,
     *     partially_fulfilled: bool,
     *     awaiting_supplier: bool,
     *     has_unfulfilled_digital_lines: bool
     * }
     */
    public function evaluateFulfillment(Order $order): array
    {
        $fullyFulfilled = true;
        $partiallyFulfilled = false;
        $awaitingSupplier = false;
        $hasUnfulfilledDigitalLines = false;

        foreach ($order->orderDetails ?? [] as $detail) {
            $lineEvaluation = $this->evaluateOrderDetail($detail);

            if (! $lineEvaluation['fulfilled']) {
                $fullyFulfilled = false;

                if ($lineEvaluation['is_digital']) {
                    $hasUnfulfilledDigitalLines = true;
                }
            }

            if ($lineEvaluation['partial']) {
                $partiallyFulfilled = true;
            }

            if ($lineEvaluation['awaiting_supplier']) {
                $awaitingSupplier = true;
            }
        }

        if ($order->orderDetails === null || $order->orderDetails->isEmpty()) {
            $fullyFulfilled = false;
        }

        return [
            'fully_fulfilled' => $fullyFulfilled,
            'partially_fulfilled' => $partiallyFulfilled,
            'awaiting_supplier' => $awaitingSupplier,
            'has_unfulfilled_digital_lines' => $hasUnfulfilledDigitalLines,
        ];
    }

    /**
     * @return array{fulfilled: bool, partial: bool, awaiting_supplier: bool, is_digital: bool}
     */
    private function evaluateOrderDetail(OrderDetail $detail): array
    {
        if ($this->eligibilityService->isDirectTopUpOrderLine($detail)) {
            $fulfilled = SupplierOrder::query()
                ->where('order_detail_id', $detail->id)
                ->where('status', 'fulfilled')
                ->exists();

            $awaitingSupplier = ! $fulfilled && SupplierOrder::query()
                ->where('order_detail_id', $detail->id)
                ->whereIn('status', ['pending', 'processing', 'partial'])
                ->exists();

            return [
                'fulfilled' => $fulfilled,
                'partial' => $fulfilled,
                'awaiting_supplier' => $awaitingSupplier,
                'is_digital' => true,
            ];
        }

        $productDetails = json_decode($detail->product_details ?? '{}');
        $productType = is_object($productDetails) ? ($productDetails->product_type ?? null) : null;
        $isDigital = $productType === 'digital';

        if (! $isDigital) {
            return [
                'fulfilled' => false,
                'partial' => false,
                'awaiting_supplier' => false,
                'is_digital' => false,
            ];
        }

        $assignedCount = DigitalProductCode::query()
            ->where('order_detail_id', $detail->id)
            ->where('status', 'sold')
            ->count();

        $needed = max(0, (int) $detail->qty);
        $fulfilled = $needed > 0 && $assignedCount >= $needed;
        $partial = $assignedCount > 0 && ! $fulfilled;

        $awaitingSupplier = false;

        if (! $fulfilled && Schema::hasTable('supplier_orders')) {
            $awaitingSupplier = SupplierOrder::query()
                ->where('order_id', $detail->order_id)
                ->where(function ($query) use ($detail): void {
                    $query->where('order_detail_id', $detail->id)
                        ->orWhereNull('order_detail_id');
                })
                ->whereIn('status', ['pending', 'processing', 'partial'])
                ->exists();
        }

        return [
            'fulfilled' => $fulfilled,
            'partial' => $partial || $fulfilled,
            'awaiting_supplier' => $awaitingSupplier,
            'is_digital' => true,
        ];
    }

    private function applyStatus(Order $order, string $status): void
    {
        $order->update(['order_status' => $status]);

        $detailDeliveryStatus = match ($status) {
            'delivered' => 'delivered',
            'processing' => 'processing',
            'failed' => 'canceled',
            default => $status,
        };

        OrderDetail::query()
            ->where('order_id', $order->id)
            ->update([
                'delivery_status' => $detailDeliveryStatus,
                'payment_status' => $order->payment_status,
            ]);

        OrderManager::add_order_status_history(
            $order->id,
            (int) ($order->customer_id ?? 0),
            $status,
            'admin',
        );

        if ($status === 'delivered' && $order->payment_method !== 'partner_wallet') {
            OrderManager::getWalletManageOnOrderStatusChange($order->fresh(), 'admin');
        }
    }
}
