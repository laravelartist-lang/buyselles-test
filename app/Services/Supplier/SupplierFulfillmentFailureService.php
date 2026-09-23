<?php

namespace App\Services\Supplier;

use App\Models\Order;
use App\Models\OrderDetail;
use App\Services\Order\OrderFailureNoteFormatter;
use App\Services\Order\PendingReviewHoldService;
use App\Services\Partner\PartnerOrderRefundService;
use App\Services\Wallet\FailedWalletOrderRefundService;
use App\Utils\OrderManager;

class SupplierFulfillmentFailureService
{
    public function __construct(
        private readonly FailedWalletOrderRefundService $walletRefundService,
        private readonly PartnerOrderRefundService $partnerOrderRefundService,
        private readonly OrderFailureNoteFormatter $failureNoteFormatter,
        private readonly PendingReviewHoldService $pendingReviewHold,
    ) {}

    public function markOrderFailed(Order $order, string $error, ?int $customerId = null): void
    {
        if ($this->isStripePaidOrder($order)) {
            $this->pendingReviewHold->holdForFulfillmentFailure($order, $error);

            return;
        }

        $refunded = $this->walletRefundService->refundPaidWalletOrder(
            $order,
            'SupplierFulfillmentFailureService'
        );

        if (! $refunded) {
            $refunded = $this->partnerOrderRefundService->refundPaidPartnerOrder(
                $order,
                'SupplierFulfillmentFailureService'
            );
        }

        $order->update([
            'order_status' => 'failed',
            'payment_status' => $refunded ? 'unpaid' : $order->payment_status,
            'order_note' => $this->failureNoteFormatter->supplierFailureNote($error),
        ]);

        OrderDetail::where('order_id', $order->id)->update([
            'delivery_status' => 'canceled',
            'payment_status' => $refunded ? 'unpaid' : 'paid',
        ]);

        OrderManager::add_order_status_history(
            $order->id,
            $customerId ?? (int) $order->customer_id,
            'failed',
            'admin'
        );

        OrderManager::abortDeferredCheckout($order);
    }

    private function isStripePaidOrder(Order $order): bool
    {
        return $order->payment_status === 'paid'
            && $order->payment_method === 'stripe';
    }
}
