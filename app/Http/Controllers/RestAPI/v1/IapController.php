<?php

namespace App\Http\Controllers\RestAPI\v1;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\Product;
use App\Services\Apple\AppleIapVerificationService;
use App\Services\Order\CustomerIapCheckoutService;
use App\Utils\CartManager;
use App\Utils\Helpers;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class IapController extends Controller
{
    public function __construct(
        private readonly CustomerIapCheckoutService $iapCheckoutService,
        private readonly AppleIapVerificationService $verificationService,
    ) {}

    public function cartProducts(Request $request): JsonResponse
    {
        if (! $this->iosIapEnabled()) {
            return response()->json(['products' => []], 200);
        }

        $user = Helpers::getCustomerInformation($request);
        $cartGroupIds = CartManager::get_cart_group_ids(request: $request, type: 'checked');

        $carts = Cart::query()
            ->whereHas('product', fn ($query) => $query->active())
            ->with('product')
            ->whereIn('cart_group_id', $cartGroupIds)
            ->where('is_checked', 1)
            ->when($user == 'offline', fn ($query) => $query->where(['customer_id' => $request->guest_id, 'is_guest' => 1]))
            ->when($user != 'offline', fn ($query) => $query->where(['customer_id' => $user->id, 'is_guest' => '0']))
            ->get();

        $products = $carts
            ->filter(fn (Cart $cart): bool => ($cart->product_type ?? $cart->product?->product_type) === 'digital')
            ->map(function (Cart $cart): array {
                $product = Product::query()->find($cart->product_id);
                $appleProductId = trim((string) ($product?->apple_product_id ?? ''));

                if ($appleProductId === '') {
                    $appleProductId = 'com.buyselles.app.digital.'.$cart->product_id;
                }

                return [
                    'cart_id' => $cart->id,
                    'product_id' => (int) $cart->product_id,
                    'quantity' => (int) $cart->quantity,
                    'apple_product_id' => $appleProductId,
                    'name' => $product?->name,
                ];
            })
            ->values();

        return response()->json(['products' => $products], 200);
    }

    public function verifyPurchase(Request $request): JsonResponse
    {
        if (! $this->iosIapEnabled()) {
            return response()->json(['message' => translate('apple_iap_is_not_enabled')], 403);
        }

        $validator = Validator::make($request->all(), [
            'transactions' => 'required|array|min:1',
            'transactions.*.transaction_id' => 'required|string',
            'transactions.*.apple_product_id' => 'required|string',
            'transactions.*.product_id' => 'required|integer',
            'transactions.*.verification_data' => 'required|string',
            'coupon_code' => 'nullable|string',
            'order_note' => 'nullable|string',
            'address_id' => 'nullable',
            'billing_address_id' => 'nullable',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::validationErrorProcessor($validator)], 403);
        }

        $result = $this->iapCheckoutService->placeOrder(
            $request,
            $request->input('transactions', []),
            $this->verificationService,
        );

        return response()->json($result['payload'], $result['http_status']);
    }

    private function iosIapEnabled(): bool
    {
        return filter_var(getWebConfig(name: 'ios_iap_status'), FILTER_VALIDATE_BOOLEAN);
    }
}
