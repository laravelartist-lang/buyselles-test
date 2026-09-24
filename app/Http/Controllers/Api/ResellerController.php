<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Partner\PartnerWalletService;
use App\Services\ResellerApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ResellerController extends Controller
{
    public function __construct(
        private readonly ResellerApiService $resellerService,
        private readonly PartnerWalletService $partnerWallet,
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

        $validator = Validator::make($request->all(), [
            'quantity' => 'nullable|integer|min:1|max:100',
            'supplier_denomination_id' => 'nullable|integer|exists:supplier_product_denominations,id',
            'custom_amount' => 'nullable|numeric|min:0.0000000001',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $quote = $this->resellerService->quoteProduct(
                resellerKey: $resellerKey,
                productId: $id,
                quantity: (int) $request->input('quantity', 1),
                denominationId: $request->filled('supplier_denomination_id')
                    ? (int) $request->input('supplier_denomination_id')
                    : null,
                customAmount: $request->filled('custom_amount')
                    ? (float) $request->input('custom_amount')
                    : null,
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

        $validator = Validator::make($request->all(), [
            'product_id' => 'required|integer|exists:products,id',
            'quantity' => 'required|integer|min:1|max:100',
            'supplier_denomination_id' => 'nullable|integer|exists:supplier_product_denominations,id',
            'custom_amount' => 'nullable|numeric|min:0.0000000001',
            'direct_topup_account_id' => 'nullable|string|max:255',
            'expected_total' => 'nullable|numeric|min:0.0000000001',
            'reference' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $result = $this->resellerService->createOrder(
            resellerKey: $resellerKey,
            productId: $request->input('product_id'),
            quantity: $request->input('quantity'),
            reference: $request->input('reference'),
            idempotencyKey: $request->header('X-Idempotency-Key'),
            denominationId: $request->filled('supplier_denomination_id')
                ? (int) $request->input('supplier_denomination_id')
                : null,
            customAmount: $request->filled('custom_amount')
                ? (float) $request->input('custom_amount')
                : null,
            directTopUpAccountId: $request->input('direct_topup_account_id'),
            expectedTotal: $request->filled('expected_total')
                ? (float) $request->input('expected_total')
                : null,
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
