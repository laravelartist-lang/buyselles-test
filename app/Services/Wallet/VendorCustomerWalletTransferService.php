<?php

namespace App\Services\Wallet;

use App\Events\AddFundToWalletEvent;
use App\Models\CustomerWallet;
use App\Models\SellerWallet;
use App\Models\Shop;
use App\Models\User;
use App\Models\VendorPermission;
use App\Models\WalletTransaction;
use App\Models\WalletTransfer;
use App\Traits\PushNotificationTrait;
use App\Utils\BackEndHelper;
use App\Utils\Convert;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class VendorCustomerWalletTransferService
{
    use PushNotificationTrait;

    public function sellerCanTransfer(int $sellerId): bool
    {
        $permission = VendorPermission::where('seller_id', $sellerId)->first();

        return $permission?->hasAccess('vendor_wallet_transfer') ?? true;
    }

    /**
     * @return Collection<int, array{id: int, name: string, email: ?string, phone: ?string, wallet_balance: float}>
     */
    public function searchCustomers(string $term): Collection
    {
        return User::query()
            ->where(function ($query) use ($term) {
                $query->where('f_name', 'like', "%{$term}%")
                    ->orWhere('l_name', 'like', "%{$term}%")
                    ->orWhere('name', 'like', "%{$term}%")
                    ->orWhere('email', 'like', "%{$term}%")
                    ->orWhere('phone', 'like', "%{$term}%");
            })
            ->orderBy('f_name')
            ->limit(20)
            ->get(['id', 'f_name', 'l_name', 'email', 'phone', 'wallet_balance'])
            ->map(function (User $customer) {
                return [
                    'id' => $customer->id,
                    'name' => trim($customer->f_name.' '.$customer->l_name),
                    'email' => $customer->email,
                    'phone' => $customer->phone,
                    'wallet_balance' => (float) ($customer->wallet_balance ?? 0),
                ];
            });
    }

    public function getTransferHistory(int $sellerId, int $limit = 20): LengthAwarePaginator
    {
        return WalletTransfer::query()
            ->where('from_user_type', 'vendor')
            ->where('from_user_id', $sellerId)
            ->with('toUser')
            ->latest()
            ->paginate($limit);
    }

    public function transfer(
        int $sellerId,
        int $customerId,
        float $displayAmount,
        ?string $reference = null,
    ): VendorCustomerWalletTransferResult {
        if ($displayAmount < 0.01) {
            return new VendorCustomerWalletTransferResult(
                success: false,
                message: translate('Invalid_withdraw_request'),
                failureCode: 'invalid_amount',
            );
        }

        $vendorWallet = SellerWallet::where('seller_id', $sellerId)->first();

        if (! $vendorWallet) {
            return new VendorCustomerWalletTransferResult(
                success: false,
                message: translate('Invalid_withdraw_request'),
                failureCode: 'invalid_wallet',
            );
        }

        $usdAmount = BackEndHelper::currency_to_usd($displayAmount);

        if (($vendorWallet->total_earning ?? 0) < Convert::usd($displayAmount)) {
            return new VendorCustomerWalletTransferResult(
                success: false,
                message: translate('insufficient_balance'),
                failureCode: 'insufficient_balance',
            );
        }

        $customer = User::find($customerId);

        if (! $customer) {
            return new VendorCustomerWalletTransferResult(
                success: false,
                message: translate('customer_not_found'),
                failureCode: 'customer_not_found',
            );
        }

        $customerWallet = CustomerWallet::firstOrCreate(
            ['customer_id' => $customerId],
            ['balance' => 0]
        );

        DB::beginTransaction();

        try {
            $vendorWallet->decrement('total_earning', $usdAmount);
            $customerWallet->increment('balance', $displayAmount);
            $customer->increment('wallet_balance', $displayAmount);

            $vendorShop = Shop::where('seller_id', $sellerId)->first();
            $shopName = $vendorShop?->name ?? translate('vendor');

            $walletTransaction = new WalletTransaction;
            $walletTransaction->user_id = $customerId;
            $walletTransaction->transaction_id = Str::uuid();
            $walletTransaction->reference = $shopName;
            $walletTransaction->transaction_type = 'vendor_transfer_to_customer';
            $walletTransaction->credit = $displayAmount;
            $walletTransaction->debit = 0;
            $walletTransaction->balance = $customerWallet->fresh()->balance;
            $walletTransaction->payment_method = 'wallet_transfer';
            $walletTransaction->save();

            $transfer = WalletTransfer::create([
                'from_user_type' => 'vendor',
                'from_user_id' => $sellerId,
                'to_user_type' => 'customer',
                'to_user_id' => $customerId,
                'amount' => $usdAmount,
                'reference' => $reference,
            ]);

            DB::commit();

            $this->sendTransferNotification($customer, $displayAmount);

            try {
                event(new AddFundToWalletEvent(email: $customer->email, data: [
                    'walletTransaction' => $walletTransaction,
                    'userName' => $customer->f_name,
                    'userType' => 'customer',
                    'templateName' => 'add-fund-to-wallet',
                    'subject' => translate('balance_received'),
                    'title' => translate('balance_received'),
                    'shopName' => $shopName,
                ]));
            } catch (\Exception $exception) {
                Log::warning('Failed to send transfer email: '.$exception->getMessage());
            }

            return new VendorCustomerWalletTransferResult(
                success: true,
                message: translate('balance_transferred_successfully'),
                transfer: $transfer->load('toUser'),
                walletTransaction: $walletTransaction,
                vendorTotalEarningUsd: (float) $vendorWallet->fresh()->total_earning,
            );
        } catch (\Exception $exception) {
            DB::rollBack();

            return new VendorCustomerWalletTransferResult(
                success: false,
                message: translate('transfer_failed').': '.$exception->getMessage(),
                failureCode: 'transfer_failed',
            );
        }
    }

    private function sendTransferNotification(User $customer, float $amount): void
    {
        if (empty($customer->cm_firebase_token)) {
            return;
        }

        $lang = $customer->app_language ?? getDefaultLanguage();
        $value = $this->pushNotificationMessage('fund_added_by_admin_message', 'customer', $lang);

        if ($value) {
            $this->sendPushNotificationToDevice($customer->cm_firebase_token, [
                'title' => setCurrencySymbol(
                    amount: currencyConverter(amount: $amount),
                    currencyCode: getCurrencyCode(type: 'default')
                ).' '.translate('_fund_added'),
                'description' => $value,
                'image' => '',
                'type' => 'wallet',
            ]);
        }
    }
}
