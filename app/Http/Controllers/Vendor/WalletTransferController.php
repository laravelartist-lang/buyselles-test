<?php

namespace App\Http\Controllers\Vendor;

use App\Events\AddFundToWalletEvent;
use App\Http\Controllers\Controller;
use App\Models\CustomerWallet;
use App\Models\SellerWallet;
use App\Models\Shop;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Models\WalletTransfer;
use App\Traits\PushNotificationTrait;
use Devrabiul\ToastMagic\Facades\ToastMagic;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class WalletTransferController extends Controller
{
    use PushNotificationTrait;

    /**
     * Show the wallet transfer form.
     */
    public function index(): View
    {
        $vendorId = auth('seller')->id();
        $vendorWallet = SellerWallet::where('seller_id', $vendorId)->first();

        $transfers = WalletTransfer::where('from_user_type', 'vendor')
            ->where('from_user_id', $vendorId)
            ->with('toUser')
            ->latest()
            ->paginate(getWebConfig(name: 'pagination_limit'));

        return view('vendor-views.wallet-transfer.index', compact('vendorWallet', 'transfers'));
    }

    /**
     * Search for customers by name, email, or phone.
     */
    public function searchCustomers(Request $request): JsonResponse
    {
        $request->validate([
            'term' => 'required|string|min:1',
        ]);

        $term = $request->query('term');

        $customers = User::where(function ($q) use ($term) {
            $q->where('f_name', 'like', "%{$term}%")
                ->orWhere('l_name', 'like', "%{$term}%")
                ->orWhere('name', 'like', "%{$term}%")
                ->orWhere('email', 'like', "%{$term}%")
                ->orWhere('phone', 'like', "%{$term}%");
        })
            ->orderBy('f_name')
            ->limit(20)
            ->get(['id', 'f_name', 'l_name', 'email', 'phone']);

        $results = $customers->map(function ($customer) {
            $label = $customer->f_name.' '.$customer->l_name;
            if ($customer->email) {
                $label .= ' ('.$customer->email.')';
            }

            return [
                'id' => $customer->id,
                'text' => trim($label),
            ];
        });

        return response()->json(['results' => $results]);
    }

    /**
     * Transfer balance from vendor wallet to customer wallet.
     */
    public function transfer(Request $request): RedirectResponse
    {
        $request->validate([
            'customer_id' => 'required|exists:users,id',
            'amount' => 'required|numeric|min:0.01',
            'reference' => 'nullable|string|max:255',
        ]);

        $vendorId = auth('seller')->id();
        $customerId = $request->customer_id;
        $amount = (float) $request->amount;

        $vendorWallet = SellerWallet::where('seller_id', $vendorId)->firstOrFail();

        if (($vendorWallet->total_earning ?? 0) < $amount) {
            ToastMagic::error(translate('insufficient_balance'));

            return redirect()->back()->withInput();
        }

        $customer = User::findOrFail($customerId);
        $customerWallet = CustomerWallet::firstOrCreate(
            ['customer_id' => $customerId],
            ['balance' => 0]
        );

        DB::beginTransaction();
        try {
            // Deduct from vendor wallet
            $vendorWallet->decrement('total_earning', $amount);

            // Add to customer wallet
            $customerWallet->increment('balance', $amount);

            // Update user wallet_balance column
            $customer->increment('wallet_balance', $amount);

            // Create WalletTransaction for customer (credit)
            $walletTransaction = new WalletTransaction;
            $walletTransaction->user_id = $customerId;
            $walletTransaction->transaction_id = Str::uuid();
            $vendorShop = Shop::where('seller_id', $vendorId)->first();
            $shopName = $vendorShop?->name ?? translate('vendor');
            $walletTransaction->reference = $shopName;
            $walletTransaction->transaction_type = 'vendor_transfer_to_customer';
            $walletTransaction->credit = $amount;
            $walletTransaction->debit = 0;
            $walletTransaction->balance = $customerWallet->balance;
            $walletTransaction->payment_method = 'wallet_transfer';
            $walletTransaction->save();

            // Log the transfer
            WalletTransfer::create([
                'from_user_type' => 'vendor',
                'from_user_id' => $vendorId,
                'to_user_type' => 'customer',
                'to_user_id' => $customerId,
                'amount' => $amount,
                'reference' => $request->reference,
            ]);

            DB::commit();

            // Send push notification to customer
            $this->sendTransferNotification($customer, $amount);

            // Send email notification to customer
            try {
                $emailData = [
                    'walletTransaction' => $walletTransaction,
                    'userName' => $customer->f_name,
                    'userType' => 'customer',
                    'templateName' => 'add-fund-to-wallet',
                    'subject' => translate('balance_received'),
                    'title' => translate('balance_received'),
                    'shopName' => $shopName,
                ];
                event(new AddFundToWalletEvent(email: $customer->email, data: $emailData));
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::warning('Failed to send transfer email: '.$e->getMessage());
            }

            ToastMagic::success(translate('balance_transferred_successfully'));

            return redirect()->route('vendor.wallet-transfer.index');
        } catch (\Exception $e) {
            DB::rollBack();
            ToastMagic::error(translate('transfer_failed').': '.$e->getMessage());

            return redirect()->back()->withInput();
        }
    }

    /**
     * Send push notification to the customer about the received funds.
     */
    private function sendTransferNotification(User $customer, float $amount): void
    {
        if (empty($customer->cm_firebase_token)) {
            return;
        }

        $lang = $customer->app_language ?? getDefaultLanguage();
        $value = $this->pushNotificationMessage('fund_added_by_admin_message', 'customer', $lang);

        if ($value) {
            $data = [
                'title' => setCurrencySymbol(
                    amount: currencyConverter(amount: $amount),
                    currencyCode: getCurrencyCode(type: 'default')
                ).' '.translate('_fund_added'),
                'description' => $value,
                'image' => '',
                'type' => 'wallet',
            ];
            $this->sendPushNotificationToDevice($customer->cm_firebase_token, $data);
        }
    }
}
