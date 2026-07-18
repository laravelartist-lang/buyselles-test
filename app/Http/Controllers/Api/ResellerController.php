<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Partner\PartnerOrderRequestValidator;
use App\Services\Partner\PartnerWalletService;
use App\Services\ResellerApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ResellerController extends Controller
{
    public function __construct(
        private readonly ResellerApiService $resellerService,
        private readonly PartnerWalletService $partnerWallet,
        private readonly PartnerOrderRequestValidator $orderRequestValidator,
    ) {}

    /**
     * GET /api/reseller/products
     * List available digital products with stock counts.
     */
    public function products(Request $request): JsonResponse
    {
        $resellerKey = $request->attributes->get('reseller_key');

        if (! $resellerKey->hasPermission('products.list')) {
            return response()->json(['error' => 'Permission denied.'], 403);
        }

        $products = $this->resellerService->listProducts(
            resellerKey: $resellerKey,
            search: $request->query('search'),
            categoryId: $request->query('category_id') ? (int) $request->query('category_id') : null,
            page: (int) $request->query('page', 1),
            perPage: min((int) $request->query('per_page', 20), 100),
            fulfillmentType: $this->normalizeFulfillmentType($request->query('fulfillment_type')),
            sellerType: $this->normalizeSellerType($request->query('seller_type')),
        );

        return response()->json($products);
    }

    /**
     * GET /api/reseller/products/{id}
     * Show product details with real-time stock count.
     */
    public function productDetail(Request $request, int $id): JsonResponse
    {
        $resellerKey = $request->attributes->get('reseller_key');

        if (! $resellerKey->hasPermission('products.list')) {
            return response()->json(['error' => 'Permission denied.'], 403);
        }

        $product = $this->resellerService->getProduct($resellerKey, $id);

        if (! $product) {
            return response()->json(['error' => 'Product not found.'], 404);
        }

        return response()->json(['data' => $product]);
    }

    /**
     * POST /api/reseller/products/{id}/quote
     * Return the authoritative current price for a selected product variant.
     */
    public function quoteProduct(Request $request, int $id): JsonResponse
    {
        $resellerKey = $request->attributes->get('reseller_key');

        if (! $resellerKey->hasPermission('products.list')) {
            return response()->json(['error' => 'Permission denied.'], 403);
        }

        $this->orderRequestValidator->mergeNormalizedRequest($request);

        $validation = $this->orderRequestValidator->validateQuoteForProduct(
            resellerKey: $resellerKey,
            productId: $id,
            input: $request->all(),
        );

        if (isset($validation['errors']) || isset($validation['error'])) {
            return response()->json(
                array_filter([
                    'error' => $validation['error'] ?? null,
                    'errors' => $validation['errors'] ?? null,
                ]),
                $validation['status'] ?? 422,
            );
        }

        try {
            $quote = $this->resellerService->quoteProduct(
                resellerKey: $resellerKey,
                productId: $id,
                quantity: $validation['quantity'],
                denominationId: $validation['denomination_id'],
                customAmount: $validation['custom_amount'],
            );
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        if ($quote === null) {
            return response()->json(['error' => 'Product not found.'], 404);
        }

        return response()->json(['data' => $quote]);
    }

    /**
     * POST /api/reseller/orders
     * Create an order and attempt immediate code assignment.
     */
    public function createOrder(Request $request): JsonResponse
    {
        $resellerKey = $request->attributes->get('reseller_key');

        if (! $resellerKey->hasPermission('orders.create')) {
            return response()->json(['error' => 'Permission denied.'], 403);
        }

        $this->orderRequestValidator->mergeNormalizedRequest($request);

        $validation = $this->orderRequestValidator->validateOrderPayload(
            resellerKey: $resellerKey,
            input: $request->all(),
        );

        if (isset($validation['errors']) || isset($validation['error'])) {
            return response()->json(
                array_filter([
                    'error' => $validation['error'] ?? null,
                    'errors' => $validation['errors'] ?? null,
                ]),
                $validation['status'] ?? 422,
            );
        }

        $result = $this->resellerService->createOrder(
            resellerKey: $resellerKey,
            productId: $validation['product_id'],
            quantity: $validation['quantity'],
            reference: $validation['reference'],
            idempotencyKey: $request->header('X-Idempotency-Key'),
            denominationId: $validation['denomination_id'],
            customAmount: $validation['custom_amount'],
            directTopUpAccountId: $validation['direct_topup_account_id'],
            expectedTotal: $validation['expected_total'],
        );

        if (isset($result['error'])) {
            return response()->json($result, $result['status'] ?? 400);
        }

        return response()->json($result, 201);
    }

    /**
     * GET /api/reseller/orders/{id}
     * Get order status and assigned codes (if fulfilled).
     */
    public function orderDetail(Request $request, int $id): JsonResponse
    {
        $resellerKey = $request->attributes->get('reseller_key');

        if (! $resellerKey->hasPermission('orders.view')) {
            return response()->json(['error' => 'Permission denied.'], 403);
        }

        $order = $this->resellerService->getOrder($id, $resellerKey);

        if (! $order) {
            return response()->json(['error' => 'Order not found.'], 404);
        }

        return response()->json(['data' => $order]);
    }

    /**
     * GET /api/reseller/balance
     * Get reseller's current wallet balance.
     */
    public function balance(Request $request): JsonResponse
    {
        $resellerKey = $request->attributes->get('reseller_key');

        if (! $resellerKey->hasPermission('balance.view')) {
            return response()->json(['error' => 'Permission denied.'], 403);
        }

        return response()->json([
            'data' => [
                'balance' => $this->partnerWallet->getAvailableBalance($resellerKey),
                'currency' => 'USD',
                'key_id' => $resellerKey->id,
                'key_name' => $resellerKey->name,
                'wallet_source' => $this->partnerWallet->usesVendorWallet($resellerKey) ? 'vendor' : 'customer',
            ],
        ]);
    }

    private function normalizeFulfillmentType(mixed $value): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        return in_array($value, ['local_codes', 'supplier_codes', 'direct_topup'], true) ? $value : null;
    }

    private function normalizeSellerType(mixed $value): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        return in_array($value, ['in_house', 'vendor'], true) ? $value : null;
    }
}
