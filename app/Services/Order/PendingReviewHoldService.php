<?php

namespace App\Services\Order;

use App\Models\Order;
use App\Models\SupplierOrder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class PendingReviewHoldService
{
    public const STATUS = 'pending_review';

    public const REASON_UNAVAILABLE = 'supplier_unavailable';

    public const REASON_API_FAILED = 'supplier_api_failed';

    /**
     * @return list<string>
     */
    public static function terminalFulfillmentStatuses(): array
    {
        return [
            'delivered',
            'canceled',
            'returned',
            'failed',
            self::STATUS,
        ];
    }

    public function isHeldForReview(object $order): bool
    {
        return (string) ($order->order_status ?? '') === self::STATUS;
    }

    public function isNonRetryable(object $order): bool
    {
        return in_array($order->order_status ?? null, self::terminalFulfillmentStatuses(), true);
    }

    /**
     * Hold a paid order for manual review. Does not refund.
     */
    public function holdOrder(object $order, string $reason, ?string $detail = null): object
    {
        if ($this->isHeldForReview($order) && (string) ($order->review_reason ?? '') === $reason) {
            return $order;
        }

        $order->order_status = self::STATUS;
        $order->review_reason = $reason;

        if (property_exists($order, 'order_note') || isset($order->order_note)) {
            $note = trim((string) ($order->order_note ?? ''));
            $holdNote = 'Pending review: '.$reason.($detail !== null && $detail !== '' ? ' — '.$detail : '');
            if (! str_contains($note, $holdNote)) {
                $order->order_note = trim($note === '' ? $holdNote : $note."\n".$holdNote);
            }
        }

        if (method_exists($order, 'save')) {
            $order->save();
        }

        $this->logHold($order, $reason, $detail);

        return $order;
    }

    public function holdForFulfillmentFailure(object $order, ?string $detail = null): object
    {
        return $this->holdOrder($order, self::REASON_API_FAILED, $detail);
    }

    public function holdOrdersForPayment(object $payment, string $reason, ?string $detail = null): Collection
    {
        $orders = $this->findOrdersForPayment($payment);

        return $orders->map(fn (object $order) => $this->holdOrder($order, $reason, $detail));
    }

    public function holdBySupplierOrderId(?string $supplierOrderId, string $reason = self::REASON_API_FAILED): ?object
    {
        if ($supplierOrderId === null || $supplierOrderId === '') {
            return null;
        }

        $supplierOrder = SupplierOrder::query()
            ->where('supplier_order_id', $supplierOrderId)
            ->orderByDesc('id')
            ->first();

        $order = $supplierOrder?->order ?? ($supplierOrder?->order_id
            ? Order::query()->find($supplierOrder->order_id)
            : null);

        if ($order === null) {
            return null;
        }

        return $this->holdOrder($order, $reason);
    }

    private function logHold(object $order, string $reason, ?string $detail): void
    {
        try {
            Log::warning('PendingReviewHoldService: order held for manual review', [
                'order_id' => $order->id ?? null,
                'reason' => $reason,
                'detail' => $detail,
                'payment_status' => $order->payment_status ?? null,
            ]);
        } catch (\Throwable) {
        }
    }

    /**
     * @return Collection<int, object>
     */
    protected function findOrdersForPayment(object $payment): Collection
    {
        $transactionId = $payment->transaction_id ?? null;

        if (! is_string($transactionId) || $transactionId === '') {
            return collect();
        }

        return Order::query()
            ->where('transaction_ref', $transactionId)
            ->orderByDesc('id')
            ->get();
    }
}
