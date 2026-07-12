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
            search: $request->query('search'),
            categoryId: $request->query('category_id') ? (int) $request->query('category_id') : null,
            page: (int) $request->query('page', 1),
            perPage: min((int) $request->query('per_page', 20), 100),
            includeVendor: $request->boolean('include_vendor'),
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

        $product = $this->resellerService->getProduct($id, $request->boolean('include_vendor'));

        if (! $product) {
            return response()->json(['error' => 'Product not found.'], 404);
        }

        return response()->json(['data' => $product]);
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

        return in_array($value, ['local_codes', 'supplier_codes'], true) ? $value : null;
    }

    private function normalizeSellerType(mixed $value): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        return in_array($value, ['in_house', 'vendor'], true) ? $value : null;
    }
}
