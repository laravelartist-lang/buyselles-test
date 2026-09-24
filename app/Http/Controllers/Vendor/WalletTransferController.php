<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\SellerWallet;
use App\Services\Wallet\VendorCustomerWalletTransferService;
use Devrabiul\ToastMagic\Facades\ToastMagic;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class WalletTransferController extends Controller
{
    public function __construct(
        private readonly VendorCustomerWalletTransferService $walletTransferService,
    ) {}

    /**
     * Show the wallet transfer form.
     */
    public function index(): View
    {
        $vendorId = auth('seller')->id();
        $vendorWallet = SellerWallet::where('seller_id', $vendorId)->first();

        $transfers = $this->walletTransferService->getTransferHistory(
            $vendorId,
            (int) getWebConfig(name: 'pagination_limit')
        );

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

        $results = $this->walletTransferService
            ->searchCustomers($request->query('term'))
            ->map(function (array $customer) {
                $label = $customer['name'];
                if ($customer['email']) {
                    $label .= ' ('.$customer['email'].')';
                }

                return [
                    'id' => $customer['id'],
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

        $result = $this->walletTransferService->transfer(
            sellerId: auth('seller')->id(),
            customerId: (int) $request->customer_id,
            displayAmount: (float) $request->amount,
            reference: $request->reference,
        );

        if ($result->success) {
            ToastMagic::success($result->message);

            return redirect()->route('vendor.wallet-transfer.index');
        }

        ToastMagic::error($result->message);

        return redirect()->back()->withInput();
    }
}
