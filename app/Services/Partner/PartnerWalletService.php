<?php

namespace App\Services\Partner;

use App\Models\CustomerWallet;
use App\Models\Order;
use App\Models\ResellerApiKey;
use App\Models\SellerWallet;
use App\Models\SellerWalletHistory;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Utils\CustomerManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PartnerWalletService
{
    /**
     * Resolve spendable balance from the linked customer or vendor account wallet.
     */
    public function getAvailableBalance(ResellerApiKey $key): float
    {
        if ($key->seller_id) {
            $wallet = SellerWallet::query()->where('seller_id', $key->seller_id)->first();

            if (! $wallet) {
                return 0.0;
            }

            return max(0, (float) $wallet->total_earning - (float) $wallet->pending_withdraw);
        }

        if ($key->user_id) {
            $user = User::query()->find($key->user_id);

            return $user ? (float) $user->wallet_balance : 0.0;
        }

        return 0.0;
    }

    /**
     * Debit the linked account wallet for a partner API order.
     *
     * @throws \RuntimeException when the account wallet cannot cover the amount
     */
    public function debitForOrder(ResellerApiKey $key, float $amount, ?int $orderId = null): void
    {
        if ($amount <= 0) {
            return;
        }

        if ($key->seller_id) {
            $this->debitVendorWallet($key->seller_id, $amount, $orderId);

            return;
        }

        if ($key->user_id) {
            $this->debitCustomerWallet($key->user_id, $amount, $orderId);

            return;
        }

        throw new \RuntimeException('Partner API key is not linked to a customer or vendor wallet.');
    }

    public function usesVendorWallet(ResellerApiKey $key): bool
    {
        return $key->seller_id !== null;
    }

    public function usesCustomerWallet(ResellerApiKey $key): bool
    {
        return $key->user_id !== null && $key->seller_id === null;
    }

    /**
     * Credit the linked account wallet when a partner API order is refunded.
     */
    public function creditForOrderRefund(Order $order, float $amount): void
    {
        if ($amount <= 0) {
            return;
        }

        if ($order->seller_id) {
            DB::transaction(function () use ($order, $amount): void {
                $sellerId = (int) $order->seller_id;

                if ($this->vendorRefundHistoryExists($sellerId, (int) $order->id)) {
                    return;
                }

                $wallet = SellerWallet::query()
                    ->where('seller_id', $sellerId)
                    ->lockForUpdate()
                    ->first();

                if (! $wallet) {
                    throw new \RuntimeException('Vendor wallet not found.');
                }

                $wallet->increment('total_earning', $amount);

                SellerWalletHistory::create([
                    'seller_id' => $sellerId,
                    'amount' => $amount,
                    'order_id' => $order->id,
                    'product_id' => null,
                    'payment' => 'partner_api_order_refund',
                ]);
            });

            return;
        }

        if ($order->customer_id) {
            CustomerManager::create_wallet_transaction(
                user_id: (int) $order->customer_id,
                amount: $amount,
                transaction_type: 'order_refund',
                reference: 'Partner API order refund',
                order_ids: [$order->id],
            );
        }
    }

    public function vendorRefundHistoryExists(int $sellerId, int $orderId): bool
    {
        return SellerWalletHistory::query()
            ->where('seller_id', $sellerId)
            ->where('order_id', $orderId)
            ->where('payment', 'partner_api_order_refund')
            ->exists();
    }

    private function debitVendorWallet(int $sellerId, float $amount, ?int $orderId = null): void
    {
        DB::transaction(function () use ($sellerId, $amount, $orderId): void {
            $wallet = SellerWallet::query()
                ->where('seller_id', $sellerId)
                ->lockForUpdate()
                ->first();

            if (! $wallet) {
                throw new \RuntimeException('Vendor wallet not found.');
            }

            $available = (float) $wallet->total_earning - (float) $wallet->pending_withdraw;

            if ($available < $amount) {
                throw new \RuntimeException('Insufficient vendor wallet balance.');
            }

            $wallet->decrement('total_earning', $amount);

            SellerWalletHistory::create([
                'seller_id' => $sellerId,
                'amount' => -$amount,
                'order_id' => $orderId,
                'product_id' => null,
                'payment' => 'partner_api_order_debit',
            ]);
        });
    }

    private function debitCustomerWallet(int $userId, float $amount, ?int $orderId): void
    {
        $transaction = CustomerManager::create_wallet_transaction(
            user_id: $userId,
            amount: $amount,
            transaction_type: 'order_place',
            reference: 'Partner API order',
            order_ids: $orderId,
        );

        if ($transaction !== false) {
            return;
        }

        DB::transaction(function () use ($userId, $amount): void {
            $user = User::query()->where('id', $userId)->lockForUpdate()->first();

            if (! $user || (float) $user->wallet_balance < $amount) {
                throw new \RuntimeException('Insufficient customer wallet balance.');
            }

            $user->decrement('wallet_balance', $amount);

            $customerWallet = CustomerWallet::query()->firstOrCreate(
                ['customer_id' => $userId],
                ['balance' => 0]
            );
            $customerWallet->decrement('balance', min((float) $customerWallet->balance, $amount));

            $walletTransaction = new WalletTransaction;
            $walletTransaction->user_id = $userId;
            $walletTransaction->transaction_id = Str::uuid();
            $walletTransaction->reference = 'Partner API order';
            $walletTransaction->transaction_type = 'order_place';
            $walletTransaction->debit = $amount;
            $walletTransaction->credit = 0;
            $walletTransaction->balance = (float) $user->wallet_balance;
            $walletTransaction->save();
        });
    }
}
