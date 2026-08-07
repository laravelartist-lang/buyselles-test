<?php

namespace App\Http\Controllers\RestAPI\v3\seller;

use App\Http\Controllers\Controller;
use App\Models\SellerWallet;
use App\Models\WalletTransfer;
use App\Services\Wallet\VendorCustomerWalletTransferService;
use App\Utils\Convert;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WalletTransferController extends Controller
{
    public function __construct(
        private readonly VendorCustomerWalletTransferService $walletTransferService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $seller = $request->seller;

        if (! $this->walletTransferService->sellerCanTransfer($seller->id)) {
            return response()->json(['message' => translate('access_Denied').'!'], 403);
        }

        $vendorWallet = SellerWallet::where('seller_id', $seller->id)->first();
        $totalEarningUsd = (float) ($vendorWallet?->total_earning ?? 0);
        $pendingWithdrawUsd = (float) ($vendorWallet?->pending_withdraw ?? 0);

        $limit = (int) ($request->query('limit') ?? 20);
        $transfers = $this->walletTransferService->getTransferHistory($seller->id, $limit);

        return response()->json([
            'total_earning' => Convert::default($totalEarningUsd),
            'withdrawable_balance' => Convert::default($totalEarningUsd - $pendingWithdrawUsd),
            'transfers' => [
                'total_size' => $transfers->total(),
                'limit' => $transfers->perPage(),
                'offset' => $transfers->currentPage(),
                'data' => $transfers->getCollection()->map(fn (WalletTransfer $transfer) => $this->formatTransfer($transfer)),
            ],
        ], 200);
    }

    public function searchCustomers(Request $request): JsonResponse
    {
        $seller = $request->seller;

        if (! $this->walletTransferService->sellerCanTransfer($seller->id)) {
            return response()->json(['message' => translate('access_Denied').'!'], 403);
        }

        $request->validate([
            'term' => 'required|string|min:1',
        ]);

        $customers = $this->walletTransferService
            ->searchCustomers($request->query('term'))
            ->map(function (array $customer) {
                return [
                    ...$customer,
                    'wallet_balance' => Convert::default($customer['wallet_balance']),
                ];
            });

        return response()->json(['customers' => $customers], 200);
    }

    public function transfer(Request $request): JsonResponse
    {
        $seller = $request->seller;

        if (! $this->walletTransferService->sellerCanTransfer($seller->id)) {
            return response()->json(['message' => translate('access_Denied').'!'], 403);
        }

        $request->validate([
            'customer_id' => 'required|exists:users,id',
            'amount' => 'required|numeric|min:0.01',
            'reference' => 'nullable|string|max:255',
        ]);

        $result = $this->walletTransferService->transfer(
            sellerId: $seller->id,
            customerId: (int) $request->customer_id,
            displayAmount: (float) $request->amount,
            reference: $request->reference,
        );

        if (! $result->success) {
            $status = match ($result->failureCode) {
                'insufficient_balance' => 422,
                'customer_not_found', 'invalid_amount' => 422,
                default => 400,
            };

            return response()->json(['message' => $result->message], $status);
        }

        return response()->json([
            'message' => $result->message,
            'transfer' => $this->formatTransfer($result->transfer),
            'total_earning' => Convert::default($result->vendorTotalEarningUsd ?? 0),
        ], 200);
    }

    /**
     * @return array<string, mixed>
     */
    private function formatTransfer(WalletTransfer $transfer): array
    {
        $customer = $transfer->toUser;

        return [
            'id' => $transfer->id,
            'amount' => Convert::default($transfer->amount),
            'reference' => $transfer->reference,
            'created_at' => $transfer->created_at?->toDateTimeString(),
            'customer' => [
                'id' => $customer?->id,
                'name' => trim(($customer?->f_name ?? '').' '.($customer?->l_name ?? '')),
                'email' => $customer?->email,
                'phone' => $customer?->phone,
            ],
        ];
    }
}
