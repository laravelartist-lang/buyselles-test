<?php

namespace App\Services\Wallet;

use App\Models\Order;
use App\Models\WalletTransaction;
use App\Utils\Convert;
use App\Utils\CustomerManager;
use Illuminate\Support\Facades\Log;

class FailedWalletOrderRefundService
{
    public function refundPaidWalletOrder(Order $order, string $logContext = 'FailedWalletOrderRefundService'): bool
    {
        if ($order->payment_method !== 'pay_by_wallet' || $order->payment_status !== 'paid') {
            return false;
        }

        if ($this->hasRefundForOrder($order)) {
            return false;
        }

        CustomerManager::create_wallet_transaction(
            $order->customer_id,
            Convert::default((float) $order->order_amount),
            'order_refund',
            'order refund',
            [],
            [$order->id]
        );

        Log::info("{$logContext}: wallet refunded after failed order fulfillment", [
            'order_id' => $order->id,
            'amount' => $order->order_amount,
        ]);

        return true;
    }

    public function hasRefundForOrder(Order $order): bool
    {
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
