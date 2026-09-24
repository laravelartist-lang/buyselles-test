<?php

namespace App\Services\Order;

use App\Models\Cart;
use App\Models\IapTransaction;
use App\Models\Order;
use App\Models\Product;
use App\Services\Apple\AppleIapVerificationService;
use App\Utils\CartManager;
use App\Utils\Helpers;
use App\Utils\OrderManager;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class CustomerIapCheckoutService
{
    public function __construct(
        private readonly CustomerCheckoutGuardService $checkoutGuard,
    ) {}

    /**
     * @param  array<int, array{transaction_id: string, apple_product_id: string, product_id: int, verification_data: string}>  $transactions
     * @return array{http_status: int, payload: array<string, mixed>}
     */
    public function placeOrder(Request $request, array $transactions, AppleIapVerificationService $verificationService): array
    {
        $cartGroupIds = CartManager::get_cart_group_ids(request: $request, type: 'checked');
        $carts = Cart::whereHas('product', function ($query) {
            return $query->active();
        })->with('product')->whereIn('cart_group_id', $cartGroupIds)->where(['is_checked' => 1])->get();

        if ($carts->isEmpty()) {
            return [
                'http_status' => 403,
                'payload' => ['message' => translate('cart_is_empty')],
            ];
        }

        if (! $this->cartIsDigitalOnly($carts)) {
            return [
                'http_status' => 403,
                'payload' => ['message' => translate('apple_iap_is_only_available_for_digital_products')],
            ];
        }

        $user = Helpers::getCustomerInformation($request);
        if ($user === 'offline' || ! is_object($user)) {
            return [
                'http_status' => 403,
                'payload' => ['message' => translate('login_first')],
            ];
        }

        $mappingErrors = $this->validateTransactionMapping($carts, $transactions);
        if ($mappingErrors !== null) {
            return $mappingErrors;
        }

        $verifiedTransactions = [];
        foreach ($transactions as $transaction) {
            $verification = $verificationService->verify(
                verificationData: (string) $transaction['verification_data'],
                expectedAppleProductId: (string) $transaction['apple_product_id'],
                expectedTransactionId: (string) $transaction['transaction_id'],
            );

            if (! $verification['valid']) {
                return [
                    'http_status' => 422,
                    'payload' => [
                        'message' => $verification['message'] ?? translate('apple_iap_verification_failed'),
                    ],
                ];
            }

            $verifiedTransactions[] = $verification;
        }

        return $this->checkoutGuard->executeIapCheckout(
            $request,
            $carts,
            fn (): array => $this->processIapCheckout($request, $carts, $user, $verifiedTransactions, $transactions),
        );
    }

    /**
     * @param  Collection<int, Cart>  $carts
     * @param  array<int, array{valid: bool, transaction_id: string, apple_product_id: string, environment: string|null}>  $verifiedTransactions
     * @param  array<int, array{transaction_id: string, apple_product_id: string, product_id: int, verification_data: string}>  $rawTransactions
     * @return array{http_status: int, payload: array<string, mixed>}
     */
    private function processIapCheckout(Request $request, Collection $carts, object $user, array $verifiedTransactions, array $rawTransactions): array
    {
        $productStock = CartManager::product_stock_check($carts);
        $digitalErrors = app(\App\Services\DigitalProductCodeService::class)->getDigitalStockErrors($carts);

        if (! $productStock || $digitalErrors !== []) {
            return [
                'http_status' => 403,
                'payload' => [
                    'message' => ! empty($digitalErrors)
                        ? implode(' | ', $digitalErrors)
                        : translate('The_following_items_in_your_cart_are_currently_out_of_stock'),
                ],
            ];
        }

        $verifyStatus = OrderManager::verifyCartListMinimumOrderAmount($request);
        if ($verifyStatus['status'] == 0) {
            return [
                'http_status' => 403,
                'payload' => ['message' => translate('Check_minimum_order_amount_requirement')],
            ];
        }

        $orderIds = OrderManager::generateOrder(data: [
            'is_guest' => 0,
            'guest_id' => 0,
            'customer_id' => $user->id,
            'order_status' => 'confirmed',
            'payment_method' => 'apple_iap',
            'payment_status' => 'paid',
            'transaction_ref' => collect($verifiedTransactions)->pluck('transaction_id')->implode(','),
            'address_id' => $request->input('address_id', session('address_id')),
            'billing_address_id' => $request->input('billing_address_id', session('billing_address_id')),
            'payment_note' => $request->input('payment_note'),
            'order_note' => $request->input('order_note'),
            'coupon_code' => $request->input('coupon_code', session('coupon_code') ?? ''),
            'requestObj' => $request,
        ]);

        $firstOrder = ! empty($orderIds) ? Order::query()->find($orderIds[0]) : null;

        foreach ($rawTransactions as $index => $transaction) {
            $verified = $verifiedTransactions[$index] ?? null;
            if (! $verified) {
                continue;
            }

            IapTransaction::query()->create([
                'transaction_id' => $verified['transaction_id'],
                'apple_product_id' => $verified['apple_product_id'],
                'product_id' => (int) $transaction['product_id'],
                'customer_id' => (int) $user->id,
                'order_id' => $firstOrder?->id,
                'environment' => $verified['environment'],
                'payload' => [
                    'verification_data' => $transaction['verification_data'],
                ],
            ]);
        }

        $cartGroupIds = $carts->pluck('cart_group_id')->unique()->filter()->values()->all();
        if ($cartGroupIds !== []) {
            CartManager::cartCleanByCartGroupIds(cartGroupIDs: $cartGroupIds);
        }

        return [
            'http_status' => 200,
            'payload' => [
                'messages' => translate('order_placed_successfully'),
                'message' => translate('order_placed_successfully'),
                'order_ids' => $orderIds,
                'order_status' => $firstOrder?->order_status,
                'pending_fulfillment' => false,
            ],
        ];
    }

    /**
     * @param  Collection<int, Cart>  $carts
     * @param  array<int, array{transaction_id: string, apple_product_id: string, product_id: int, verification_data: string}>  $transactions
     * @return array{http_status: int, payload: array<string, mixed>}|null
     */
    private function validateTransactionMapping(Collection $carts, array $transactions): ?array
    {
        $digitalCartItems = $carts->filter(fn (Cart $cart): bool => ($cart->product_type ?? $cart->product?->product_type) === 'digital');

        if ($digitalCartItems->count() !== count($transactions)) {
            return [
                'http_status' => 422,
                'payload' => ['message' => translate('apple_iap_requires_one_transaction_per_digital_product')],
            ];
        }

        $expectedProducts = $digitalCartItems->map(function (Cart $cart): array {
            $product = Product::query()->find($cart->product_id);
            $appleProductId = trim((string) ($product?->apple_product_id ?? ''));

            if ($appleProductId === '') {
                $appleProductId = 'com.buyselles.app.digital.'.$cart->product_id;
            }

            return [
                'product_id' => (int) $cart->product_id,
                'apple_product_id' => $appleProductId,
            ];
        })->values();

        foreach ($expectedProducts as $expected) {
            $matched = collect($transactions)->first(function (array $transaction) use ($expected): bool {
                return (int) $transaction['product_id'] === $expected['product_id']
                    && (string) $transaction['apple_product_id'] === $expected['apple_product_id'];
            });

            if ($matched === null) {
                return [
                    'http_status' => 422,
                    'payload' => ['message' => translate('apple_iap_product_mapping_mismatch')],
                ];
            }
        }

        return null;
    }

    /**
     * @param  Collection<int, Cart>  $carts
     */
    private function cartIsDigitalOnly(Collection $carts): bool
    {
        return $carts->every(function (Cart $cart): bool {
            $productType = $cart->product_type ?? $cart->product?->product_type;

            return $productType === 'digital';
        });
    }
}
