<?php

namespace App\Services\Order;

use App\Http\Controllers\RestAPI\v1\OrderController;
use App\Models\Cart;
use App\Models\Order;
use App\Models\ShippingAddress;
use App\Services\CustomerServiceFeeService;
use App\Services\DirectTopUp\DirectTopUpWalletCheckoutService;
use App\Utils\CartManager;
use App\Utils\Convert;
use App\Utils\CustomerManager;
use App\Utils\Helpers;
use App\Utils\OrderManager;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class CustomerWalletCheckoutService
{
    public function __construct(
        private readonly CustomerCheckoutGuardService $checkoutGuard,
        private readonly DirectTopUpWalletCheckoutService $directTopUpCheckout,
        private readonly CustomerServiceFeeService $customerServiceFeeService,
    ) {}

    /**
     * @return array{http_status: int, payload: array<string, mixed>, idempotent_replay?: bool, checkout_in_progress?: bool}
     */
    public function placeOrder(Request $request): array
    {
        $cartGroupIds = CartManager::get_cart_group_ids(request: $request, type: 'checked');
        $carts = Cart::whereHas('product', function ($query) {
            return $query->active();
        })->with('product')->whereIn('cart_group_id', $cartGroupIds)->where(['is_checked' => 1])->get();

        if ($carts->isEmpty()) {
            return [
                'http_status' => 403,
                'payload' => [
                    'message' => translate('cart_is_empty'),
                ],
            ];
        }

        $productStock = CartManager::product_stock_check($carts);
        if (! $productStock) {
            $digitalErrors = app(\App\Services\DigitalProductCodeService::class)->getDigitalStockErrors($carts);
            $errorMsg = ! empty($digitalErrors)
                ? implode(' | ', $digitalErrors)
                : translate('The_following_items_in_your_cart_are_currently_out_of_stock');

            return [
                'http_status' => 403,
                'payload' => [
                    'message' => $errorMsg,
                ],
            ];
        }

        $verifyStatus = OrderManager::verifyCartListMinimumOrderAmount($request);
        if ($verifyStatus['status'] == 0) {
            return [
                'http_status' => 403,
                'payload' => [
                    'message' => translate('Check_minimum_order_amount_requirement'),
                ],
            ];
        }

        $user = Helpers::getCustomerInformation($request);
        if ($user === 'offline' || ! is_object($user)) {
            return [
                'http_status' => 403,
                'payload' => [
                    'message' => translate('login_first'),
                ],
            ];
        }

        $paymentAmount = $this->resolvePaymentAmount($request, $carts);
        if (round($paymentAmount, 4) > round($user->wallet_balance, 4)) {
            return [
                'http_status' => 403,
                'payload' => [
                    'message' => translate('inefficient_balance_in_your_wallet_to_pay_for_this_order'),
                ],
            ];
        }

        $physicalAddressError = $this->validatePhysicalProductAddress($request, $carts, $user);
        if ($physicalAddressError !== null) {
            return $physicalAddressError;
        }

        return $this->checkoutGuard->executeWalletCheckout(
            $request,
            $carts,
            fn (): array => $this->processWalletCheckout($request, $carts, $user, $paymentAmount),
        );
    }

    /**
     * @param  Collection<int, Cart>  $carts
     * @return array{http_status: int, payload: array<string, mixed>}
     */
    private function processWalletCheckout(Request $request, Collection $carts, object $user, float $paymentAmount): array
    {
        $requiresFulfillmentBeforePayment = $this->directTopUpCheckout->requiresFulfillmentBeforePayment($carts);
        $deferOrderPlacedEmail = ! $requiresFulfillmentBeforePayment
            && $this->cartRequiresDeferredOrderPlacedEmail($carts);

        $orderIds = OrderManager::generateOrder(data: [
            'is_guest' => 0,
            'guest_id' => 0,
            'customer_id' => $user->id,
            'order_status' => $requiresFulfillmentBeforePayment ? 'pending' : 'confirmed',
            'payment_method' => 'pay_by_wallet',
            'payment_status' => $requiresFulfillmentBeforePayment ? 'unpaid' : 'paid',
            'defer_checkout_completion' => $requiresFulfillmentBeforePayment || $deferOrderPlacedEmail,
            'transaction_ref' => '',
            'address_id' => $request->input('address_id', session('address_id')),
            'billing_address_id' => $request->input('billing_address_id', session('billing_address_id')),
            'payment_note' => $request->input('payment_note'),
            'order_note' => $request->input('order_note'),
            'coupon_code' => $request->input('coupon_code', session('coupon_code') ?? ''),
            'requestObj' => $request,
        ]);

        if ($requiresFulfillmentBeforePayment) {
            $checkoutResult = $this->directTopUpCheckout->completeWalletPaymentAfterFulfillment(
                $orderIds,
                (int) $user->id,
                $paymentAmount
            );

            if (! $checkoutResult['success']) {
                return [
                    'http_status' => 422,
                    'payload' => [
                        'message' => $checkoutResult['error'],
                        'order_ids' => $orderIds,
                    ],
                ];
            }
        } elseif ($this->directTopUpCheckout->requiresFulfillmentBeforePayment($carts)) {
            return [
                'http_status' => 422,
                'payload' => [
                    'message' => translate('direct_topup_fulfillment_failed'),
                    'order_ids' => $orderIds,
                ],
            ];
        } else {
            CustomerManager::create_wallet_transaction(
                $user->id,
                Convert::default($paymentAmount),
                'order_place',
                'order payment',
                [],
                $orderIds
            );
        }

        $firstOrder = ! empty($orderIds) ? Order::query()->find($orderIds[0]) : null;
        $orderStatus = $firstOrder?->order_status;
        $pendingFulfillment = in_array($orderStatus, ['pending', 'processing'], true);

        return [
            'http_status' => 200,
            'payload' => [
                'messages' => translate('order_placed_successfully'),
                'message' => translate('order_placed_successfully'),
                'order_ids' => $orderIds,
                'order_status' => $orderStatus,
                'pending_fulfillment' => $pendingFulfillment,
            ],
        ];
    }

    /**
     * @param  Collection<int, Cart>  $carts
     */
    private function cartRequiresDeferredOrderPlacedEmail(Collection $carts): bool
    {
        if ($carts->isEmpty()) {
            return false;
        }

        return $carts->every(function (Cart $cart): bool {
            $productType = $cart->product_type ?? $cart->product?->product_type;

            return $productType === 'digital';
        });
    }

    /**
     * @param  Collection<int, Cart>  $carts
     */
    private function resolvePaymentAmount(Request $request, Collection $carts): float
    {
        $vendorWiseCartList = OrderManager::processOrderGenerateData(data: [
            'coupon_code' => $request->input('coupon_code', session('coupon_code') ?? ''),
            'requestObj' => $request,
        ]);
        $vendorCollection = collect($vendorWiseCartList);
        $amountBeforeServiceFee = (float) (
            $vendorCollection->sum('order_amount_with_tax')
            - $vendorCollection->sum('refer_and_earn_discount')
        );

        return (float) $this->customerServiceFeeService
            ->calculateCheckoutPayable(amountBeforeServiceFee: $amountBeforeServiceFee)['payable_amount'];
    }

    /**
     * @param  Collection<int, Cart>  $carts
     * @return array{http_status: int, payload: array<string, mixed>}|null
     */
    private function validatePhysicalProductAddress(Request $request, Collection $carts, object $user): ?array
    {
        $physicalProduct = $carts->contains(static fn (Cart $cart): bool => $cart->product_type === 'physical');

        if (! $physicalProduct) {
            return null;
        }

        $billingAddressId = $request->input('billing_address_id', session('billing_address_id'));
        if (! $billingAddressId) {
            return null;
        }

        $zipRestrictStatus = getWebConfig(name: 'delivery_zip_code_area_restriction');
        $countryRestrictStatus = getWebConfig(name: 'delivery_country_restriction');
        $shippingAddress = ShippingAddress::where([
            'customer_id' => $user->id,
            'id' => $billingAddressId,
        ])->first();

        if (! $shippingAddress) {
            return [
                'http_status' => 403,
                'payload' => [
                    'message' => translate('address_not_found'),
                ],
            ];
        }

        if ($countryRestrictStatus && ! OrderController::delivery_country_exist_check($shippingAddress->country)) {
            return [
                'http_status' => 403,
                'payload' => [
                    'message' => translate('Delivery_unavailable_for_this_country'),
                ],
            ];
        }

        if ($zipRestrictStatus && ! OrderController::delivery_zipcode_exist_check($shippingAddress->zip)) {
            return [
                'http_status' => 403,
                'payload' => [
                    'message' => translate('Delivery_unavailable_for_this_zip_code_area'),
                ],
            ];
        }

        return null;
    }
}
