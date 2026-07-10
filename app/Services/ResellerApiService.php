<?php

namespace App\Services;

use App\Jobs\ReleasePartnerEscrowJob;
use App\Jobs\SupplierCodeFetchJob;
use App\Models\DigitalProductCode;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\PartnerOrderIdempotency;
use App\Models\Product;
use App\Models\ResellerApiKey;
use App\Models\SellerWallet;
use App\Services\Partner\PartnerProductCatalogQuery;
use App\Services\Partner\PartnerProductPresenter;
use App\Services\Partner\PartnerProductStockResolver;
use App\Services\Supplier\SupplierOrderEligibilityService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ResellerApiService
{
    public function __construct(
        private readonly PartnerProductCatalogQuery $catalogQuery,
        private readonly PartnerProductPresenter $productPresenter,
        private readonly PartnerProductStockResolver $stockResolver,
        private readonly DigitalProductCodeService $codeService,
        private readonly SupplierOrderEligibilityService $supplierOrderEligibilityService,
    ) {}

    /**
     * List digital "ready_product" products with available stock counts.
     */
    public function listProducts(
        ?string $search,
        ?int $categoryId,
        int $page,
        int $perPage,
        bool $includeVendor = false,
        ?string $fulfillmentType = null,
        ?string $sellerType = null,
    ): array {
        $query = $this->catalogQuery->baseQuery(
            includeVendor: $includeVendor,
            fulfillmentType: $fulfillmentType,
            sellerType: $sellerType,
        )->with(['supplierMapping.supplierApi']);

        if ($search) {
            $query->where('name', 'like', '%'.$search.'%');
        }

        if ($categoryId) {
            $query->where('category_id', $categoryId);
        }

        $products = $query->paginate($perPage, ['*'], 'page', $page);

        return [
            'data' => $products->map(fn (Product $product) => $this->productPresenter->toListArray($product))->all(),
            'meta' => [
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
                'per_page' => $products->perPage(),
                'total' => $products->total(),
            ],
        ];
    }

    /**
     * Get single product details with stock count.
     */
    public function getProduct(int $id, bool $includeVendor = false): ?array
    {
        $product = $this->catalogQuery->findEligibleProduct($id, $includeVendor);

        if (! $product) {
            return null;
        }

        return $this->productPresenter->toDetailArray($product);
    }

    /**
     * Create a reseller order idempotently: debit wallet, assign codes, return result.
     * If $idempotencyKey is provided and a matching record exists, the cached response is returned.
     */
    public function createOrder(ResellerApiKey $resellerKey, int $productId, int $quantity, ?string $reference, ?string $idempotencyKey = null): array
    {
        if ($idempotencyKey !== null) {
            $existing = PartnerOrderIdempotency::query()
                ->where('reseller_api_key_id', $resellerKey->id)
                ->where('idempotency_key', $idempotencyKey)
                ->first();

            if ($existing) {
                return array_merge($existing->response_payload, ['idempotent_replay' => true]);
            }
        }

        $product = $this->catalogQuery->findOrderProduct($productId);

        if (! $product) {
            return ['error' => 'Product not found or not available.', 'status' => 404];
        }

        if ($this->catalogQuery->hasActiveDirectTopupMapping($productId)) {
            return [
                'error' => 'Direct top-up products are not supported via Partner API.',
                'status' => 422,
            ];
        }

        $availableCount = $this->stockResolver->resolve($product);

        if ($availableCount < $quantity) {
            return [
                'error' => 'Insufficient stock.',
                'available' => $availableCount,
                'requested' => $quantity,
                'status' => 409,
            ];
        }

        $totalCost = $product->unit_price * $quantity;

        if ((float) $resellerKey->wallet_balance < $totalCost) {
            return [
                'error' => 'Insufficient wallet balance.',
                'balance' => (float) $resellerKey->wallet_balance,
                'required' => $totalCost,
                'status' => 402,
            ];
        }

        try {
            return DB::transaction(function () use ($resellerKey, $product, $productId, $quantity, $totalCost, $reference, $idempotencyKey) {
                ResellerApiKey::where('id', $resellerKey->id)
                    ->lockForUpdate()
                    ->decrement('wallet_balance', $totalCost);

                $order = Order::withoutEvents(function () use ($resellerKey, $totalCost, $reference) {
                    return Order::create([
                        'customer_id' => null,
                        'customer_type' => 'partner',
                        'payment_status' => 'paid',
                        'order_status' => 'processing',
                        'payment_method' => 'partner_wallet',
                        'order_amount' => $totalCost,
                        'order_type' => 'default',
                        'order_note' => $reference ? 'Partner ref: '.$reference : 'Partner API order (key #'.$resellerKey->id.')',
                        'is_guest' => 0,
                        'seller_id' => $resellerKey->seller_id,
                    ]);
                });

                OrderDetail::create([
                    'order_id' => $order->id,
                    'product_id' => $productId,
                    'seller_id' => $product->user_id,
                    'product_details' => json_encode($this->productPresenter->toOrderSnapshot($product)),
                    'qty' => $quantity,
                    'price' => $product->unit_price,
                    'tax' => 0,
                    'discount' => 0,
                    'product_type' => 'digital',
                    'digital_product_type' => 'ready_product',
                    'payment_status' => 'paid',
                ]);

                $order->load('orderDetails');
                $this->codeService->assignAndNotify($order);

                if ($this->supplierOrderEligibilityService->orderNeedsSupplierCodeFetch($order)) {
                    SupplierCodeFetchJob::dispatch($order->id);
                }

                $codes = $this->collectOrderCodes($order);
                $quantityFulfilled = count($codes);
                $isFulfilled = $quantityFulfilled >= $quantity;

                $order->update([
                    'order_status' => $isFulfilled ? 'delivered' : 'processing',
                ]);

                $sellerId = $product->user_id;
                SellerWallet::where('seller_id', $sellerId)
                    ->increment('pending_balance', $totalCost);

                ReleasePartnerEscrowJob::dispatch($order->id, $sellerId, $totalCost)
                    ->delay(now()->addHours(48));

                $result = [
                    'data' => [
                        'order_id' => $order->id,
                        'product_id' => $productId,
                        'product_name' => $product->name,
                        'quantity_requested' => $quantity,
                        'quantity_fulfilled' => $quantityFulfilled,
                        'total_cost' => $totalCost,
                        'status' => $isFulfilled ? 'fulfilled' : 'pending_fulfillment',
                        'reference' => $reference,
                        'codes' => $codes,
                    ],
                ];

                if ($idempotencyKey !== null) {
                    PartnerOrderIdempotency::create([
                        'reseller_api_key_id' => $resellerKey->id,
                        'idempotency_key' => $idempotencyKey,
                        'order_id' => $order->id,
                        'response_payload' => $result,
                    ]);
                }

                return $result;
            });
        } catch (\Throwable $e) {
            Log::error('ResellerApiService: order creation failed', [
                'product_id' => $productId,
                'quantity' => $quantity,
                'error' => $e->getMessage(),
            ]);

            return ['error' => 'Order processing failed. Please try again.', 'status' => 500];
        }
    }

    /**
     * Get order details for a reseller.
     */
    public function getOrder(int $orderId, ResellerApiKey $resellerKey): ?array
    {
        $order = Order::query()
            ->where('id', $orderId)
            ->where('seller_id', $resellerKey->seller_id)
            ->where('payment_method', 'partner_wallet')
            ->with(['orderDetails'])
            ->first();

        if (! $order) {
            return null;
        }

        $codes = $this->collectOrderCodes($order);
        $quantityRequested = (int) $order->orderDetails->sum('qty');
        $quantityFulfilled = count($codes);

        return [
            'order_id' => $order->id,
            'status' => $order->order_status,
            'payment_status' => $order->payment_status,
            'fulfillment_status' => $quantityFulfilled >= $quantityRequested && $quantityRequested > 0
                ? 'fulfilled'
                : 'pending_fulfillment',
            'quantity_requested' => $quantityRequested,
            'quantity_fulfilled' => $quantityFulfilled,
            'total' => (float) $order->order_amount,
            'created_at' => $order->created_at?->toIso8601String(),
            'items' => $order->orderDetails->map(fn ($detail) => [
                'product_id' => $detail->product_id,
                'quantity' => $detail->qty,
                'price' => (float) $detail->price,
            ])->all(),
            'codes' => $codes,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function collectOrderCodes(Order $order): array
    {
        return DigitalProductCode::query()
            ->where('order_id', $order->id)
            ->where('status', 'sold')
            ->get()
            ->map(fn (DigitalProductCode $code) => [
                'code' => $code->decryptCode(),
                'pin' => $code->decryptPin(),
                'serial' => $code->serial_number,
                'product_id' => $code->product_id,
                'expiry' => $code->expiry_date?->format('Y-m-d'),
            ])
            ->all();
    }

    /**
     * Generate a new API key pair for a seller.
     * Key starts as pending — requires admin approval before it becomes active.
     */
    public static function generateKeyPair(?int $userId, string $name = 'API Key', ?int $sellerId = null, ?string $requestNote = null): ResellerApiKey
    {
        $rawKey = 'rslr_'.Str::random(40);
        $rawSecret = Str::random(48);

        return ResellerApiKey::create([
            'user_id' => $userId,
            'seller_id' => $sellerId,
            'name' => $name,
            'api_key' => hash('sha256', $rawKey),
            'api_secret' => hash('sha256', $rawSecret),
            'permissions' => ['products.list', 'orders.create', 'orders.view', 'balance.view'],
            'rate_limit_per_minute' => 60,
            'is_active' => false,
            'status' => 'pending',
            'request_note' => $requestNote,
        ])->setAttribute('raw_api_key', $rawKey)
            ->setAttribute('raw_api_secret', $rawSecret);
    }
}
