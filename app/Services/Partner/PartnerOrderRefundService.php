<?php

namespace App\Services\Partner;

use App\Models\Order;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\Log;

class PartnerOrderRefundService
{
    public function __construct(
        private readonly PartnerWalletService $partnerWallet,
        private readonly PartnerOrderSettlementService $settlementService,
    ) {}

    public function refundPaidPartnerOrder(Order $order, string $logContext = 'PartnerOrderRefundService'): bool
    {
        if ($order->payment_method !== 'partner_wallet' || $order->payment_status !== 'paid') {
            return false;
        }

        if ($this->hasRefundForOrder($order)) {
            return false;
        }

        $amount = (float) $order->order_amount;

        if ($amount <= 0) {
            return false;
        }

        $this->partnerWallet->creditForOrderRefund($order, $amount);
        $this->settlementService->reverseSettlement($order);

        Log::info("{$logContext}: partner wallet refunded after failed order fulfillment", [
            'order_id' => $order->id,
            'amount' => $amount,
        ]);

        return true;
    }

    public function hasRefundForOrder(Order $order): bool
    {
        if ($order->seller_id !== null) {
            return false;
        }

        if ($order->customer_id === null) {
            return false;
        }

        return WalletTransaction::query()
            ->where('user_id', $order->customer_id)
            ->where('transaction_type', 'order_refund')
            ->whereNotNull('order_ids')
            ->get()
            ->contains(function (WalletTransaction $transaction) use ($order): bool {
                return in_array($order->id, (array) $transaction->order_ids, true);
            });
    }
}
