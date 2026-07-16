<?php

namespace App\Services;

use App\Jobs\DirectTopUpFulfillmentJob;
use App\Jobs\ReleasePartnerEscrowJob;
use App\Jobs\SupplierCodeFetchJob;
use App\Models\DigitalProductCode;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\PartnerCatalogItem;
use App\Models\PartnerOrderIdempotency;
use App\Models\ResellerApiKey;
use App\Models\SellerWallet;
use App\Services\DirectTopUp\DirectTopUpService;
use App\Services\Partner\PartnerCatalogPricingService;
use App\Services\Partner\PartnerProductCatalogQuery;
use App\Services\Partner\PartnerProductPresenter;
use App\Services\Partner\PartnerProductStockResolver;
use App\Services\Partner\PartnerWalletService;
use App\Services\Supplier\SupplierOrderEligibilityService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use InvalidArgumentException;

class ResellerApiService
{
    public function __construct(
        private readonly PartnerProductCatalogQuery $catalogQuery,
        private readonly PartnerProductPresenter $productPresenter,
        private readonly PartnerCatalogPricingService $catalogPricing,
        private readonly PartnerProductStockResolver $stockResolver,
        private readonly DigitalProductCodeService $codeService,
        private readonly SupplierOrderEligibilityService $supplierOrderEligibilityService,
        private readonly PartnerWalletService $partnerWallet,
        private readonly DirectTopUpService $directTopUpService,
    ) {}

    /**
     * List digital ready-product catalog items with available stock counts.
     */
    public function listProducts(
        ResellerApiKey $resellerKey,
        ?string $search,
        ?int $categoryId,
        int $page,
        int $perPage,
        ?string $fulfillmentType = null,
        ?string $sellerType = null,
    ): array {
        $query = $this->catalogQuery->baseQuery(
            resellerKey: $resellerKey,
            fulfillmentType: $fulfillmentType,
            sellerType: $sellerType,
        );

        if ($search) {
            $query->whereHas('product', fn ($productQuery) => $productQuery
                ->where('name', 'like', '%'.$search.'%'));
        }

        if ($categoryId) {
            $query->whereHas('product', fn ($productQuery) => $productQuery
                ->where('category_id', $categoryId));
        }

        $catalogItems = $query->paginate($perPage, ['*'], 'page', $page);

        return [
            'data' => $catalogItems
                ->map(fn (PartnerCatalogItem $catalogItem) => $this->productPresenter->toListArray($catalogItem))
                ->all(),
            'meta' => [
                'current_page' => $catalogItems->currentPage(),
                'last_page' => $catalogItems->lastPage(),
                'per_page' => $catalogItems->perPage(),
                'total' => $catalogItems->total(),
            ],
        ];
    }

    /**
     * Get single product details with stock count.
     */
    public function getProduct(ResellerApiKey $resellerKey, int $id): ?array
    {
        $catalogItem = $this->catalogQuery->findEligibleItem($resellerKey, $id);

        if (! $catalogItem) {
            return null;
        }

        return $this->productPresenter->toDetailArray($catalogItem);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function quoteProduct(
        ResellerApiKey $resellerKey,
        int $productId,
        int $quantity,
        ?int $denominationId = null,
        ?float $customAmount = null,
    ): ?array {
        $catalogItem = $this->catalogQuery->findEligibleItem($resellerKey, $productId);

        if ($catalogItem === null) {
            return null;
        }

        return $this->catalogPricing->quote(
            catalogItem: $catalogItem,
            quantity: $quantity,
            denominationId: $denominationId,
            customAmount: $customAmount,
        );
    }

    /**
     * Create a reseller order idempotently: debit wallet, assign codes, return result.
     * If $idempotencyKey is provided and a matching record exists, the cached response is returned.
     */
    public function createOrder(
        ResellerApiKey $resellerKey,
        int $productId,
        int $quantity,
        ?string $reference,
        ?string $idempotencyKey = null,
        ?int $denominationId = null,
        ?float $customAmount = null,
        ?string $directTopUpAccountId = null,
        ?float $expectedTotal = null,
    ): array {
        if ($idempotencyKey !== null) {
            $existing = PartnerOrderIdempotency::query()
                ->where('reseller_api_key_id', $resellerKey->id)
                ->where('idempotency_key', $idempotencyKey)
                ->first();

            if ($existing) {
                return array_merge($existing->response_payload, ['idempotent_replay' => true]);
            }
        }

        $previewItem = $this->catalogQuery->findEligibleItem($resellerKey, $productId);

        if ($previewItem === null) {
            return ['error' => 'Product is not available in this partner catalog.', 'status' => 404];
        }

        $product = $previewItem->product;
        $isDirectTopUp = (bool) $product->supplierMapping?->is_direct_topup;

        if ($isDirectTopUp) {
            $bundleQuantity = $this->directTopUpService->resolveBundleQuantity($product);
            $directTopUpErrors = $this->directTopUpService->validatePurchase(
                product: $product,
                accountId: trim((string) $directTopUpAccountId),
                quantity: $bundleQuantity,
            );

            if ($directTopUpErrors !== []) {
                return [
                    'error' => (string) reset($directTopUpErrors),
                    'errors' => $directTopUpErrors,
                    'status' => 422,
                ];
            }
        } else {
            $availableCount = $this->stockResolver->resolve($product);

            if ($availableCount < $quantity) {
                return [
                    'error' => 'Insufficient stock.',
                    'available' => $availableCount,
                    'requested' => $quantity,
                    'status' => 409,
                ];
            }
        }

        try {
            return DB::transaction(function () use (
                $resellerKey,
                $productId,
                $quantity,
                $reference,
                $idempotencyKey,
                $denominationId,
                $customAmount,
                $directTopUpAccountId,
                $expectedTotal,
            ) {
                $catalogItem = $this->catalogQuery->findOrderItem(
                    resellerKey: $resellerKey,
                    productId: $productId,
                    lockForUpdate: true,
                );

                if ($catalogItem === null) {
                    return ['error' => 'Product is not available in this partner catalog.', 'status' => 404];
                }

                $quote = $this->catalogPricing->quote(
                    catalogItem: $catalogItem,
                    quantity: $quantity,
                    denominationId: $denominationId,
                    customAmount: $customAmount,
                );
                $totalCost = $quote['subtotal'];

                if ($expectedTotal !== null && abs($expectedTotal - $totalCost) >= 0.0000001) {
                    return [
                        'error' => 'Price changed. Request a new quote before ordering.',
                        'code' => 'price_changed',
                        'expected_total' => $expectedTotal,
                        'current_quote' => $quote,
                        'status' => 409,
                    ];
                }

                $availableBalance = $this->partnerWallet->getAvailableBalance($resellerKey);

                if ($availableBalance < $totalCost) {
                    return [
                        'error' => 'Insufficient wallet balance.',
                        'balance' => $availableBalance,
                        'required' => $totalCost,
                        'status' => 402,
                    ];
                }

                $product = $catalogItem->product;
                $isDirectTopUp = (bool) $product->supplierMapping?->is_direct_topup;

                $order = Order::withoutEvents(function () use ($resellerKey, $totalCost, $reference) {
                    return Order::create([
                        'customer_id' => $resellerKey->seller_id === null ? $resellerKey->user_id : null,
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

                $this->partnerWallet->debitForOrder($resellerKey, $totalCost, $order->id);

                $orderDetail = OrderDetail::create([
                    'order_id' => $order->id,
                    'product_id' => $productId,
                    'seller_id' => $product->user_id,
                    'product_details' => json_encode($this->productPresenter->toOrderSnapshot($catalogItem, $quote)),
                    'qty' => $quantity,
                    'price' => $quote['unit_price'],
                    'custom_amount' => $quote['custom_amount'],
                    'supplier_denomination_id' => $quote['denomination_id'],
                    'direct_topup_account_id' => $isDirectTopUp ? trim((string) $directTopUpAccountId) : null,
                    'direct_topup_quantity' => $isDirectTopUp
                        ? $this->directTopUpService->resolveBundleQuantity($product)
                        : null,
                    'tax' => 0,
                    'discount' => 0,
                    'product_type' => 'digital',
                    'digital_product_type' => $product->digital_product_type,
                    'payment_status' => 'paid',
                ]);

                $order->load('orderDetails');

                if ($isDirectTopUp) {
                    DirectTopUpFulfillmentJob::dispatch($order->id);
                } else {
                    $this->codeService->assignAndNotify($order);

                    if ($this->supplierOrderEligibilityService->orderNeedsSupplierCodeFetch($order)) {
                        SupplierCodeFetchJob::dispatch($order->id);
                    }
                }

                $codes = $isDirectTopUp ? [] : $this->collectOrderCodes($order);
                $quantityFulfilled = count($codes);
                $isFulfilled = ! $isDirectTopUp && $quantityFulfilled >= $quantity;

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
                        'pricing' => $quote,
                        'status' => $isFulfilled ? 'fulfilled' : 'pending_fulfillment',
                        'reference' => $reference,
                        'order_detail_id' => $orderDetail->id,
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
        } catch (InvalidArgumentException $e) {
            return ['error' => $e->getMessage(), 'status' => 422];
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
            ->when(
                $resellerKey->seller_id !== null,
                fn ($query) => $query->where('seller_id', $resellerKey->seller_id),
                fn ($query) => $query
                    ->where('customer_id', $resellerKey->user_id)
                    ->whereNull('seller_id'),
            )
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
     *
     * @return array{key: ResellerApiKey, raw_api_key: string, raw_api_secret: string}
     */
    public static function generateKeyPair(?int $userId, string $name = 'API Key', ?int $sellerId = null, ?string $requestNote = null): array
    {
        $rawKey = 'rslr_'.Str::random(40);
        $rawSecret = Str::random(48);

        $key = ResellerApiKey::create([
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
        ]);

        return [
            'key' => $key,
            'raw_api_key' => $rawKey,
            'raw_api_secret' => $rawSecret,
        ];
    }

    /**
     * Replace credentials for an existing key while preserving settings.
     *
     * @return array{key: ResellerApiKey, raw_api_key: string, raw_api_secret: string}
     */
    public static function regenerateCredentials(ResellerApiKey $key): array
    {
        $rawKey = 'rslr_'.Str::random(40);
        $rawSecret = Str::random(48);

        Cache::forget("reseller_key:{$key->api_key}");

        $key->update([
            'api_key' => hash('sha256', $rawKey),
            'api_secret' => hash('sha256', $rawSecret),
        ]);

        return [
            'key' => $key->fresh(),
            'raw_api_key' => $rawKey,
            'raw_api_secret' => $rawSecret,
        ];
    }
}
