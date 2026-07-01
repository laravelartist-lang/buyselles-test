<?php

namespace App\Services;

use App\Jobs\ReleasePartnerEscrowJob;
use App\Models\DigitalProductCode;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\PartnerOrderIdempotency;
use App\Models\Product;
use App\Models\ResellerApiKey;
use App\Models\SellerWallet;
use App\Models\SupplierProductMapping;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ResellerApiService
{
    /**
     * List digital "ready_product" products with available stock counts.
     */
    public function listProducts(?string $search, ?int $categoryId, int $page, int $perPage): array
    {
        $query = Product::query()
            ->where('product_type', 'digital')
            ->where('digital_product_type', 'ready_product')
            ->where('status', 1)
            ->where('request_status', 1)
            ->where('partner_approved', 1)
            ->withCount(['digitalProductCodes as available_stock' => function ($q) {
                $q->where('status', 'available')
                    ->where('is_active', true)
                    ->where(function ($q2) {
                        $q2->whereNull('expiry_date')
                            ->orWhereDate('expiry_date', '>=', now()->toDateString());
                    });
            }]);

        if ($search) {
            $query->where('name', 'like', '%'.$search.'%');
        }

        if ($categoryId) {
            $query->where('category_id', $categoryId);
        }

        $products = $query->paginate($perPage, ['*'], 'page', $page);

        return [
            'data' => $products->map(fn (Product $p) => [
                'id' => $p->id,
                'name' => $p->name,
                'slug' => $p->slug,
                'category_id' => $p->category_id,
                'unit_price' => (float) $p->unit_price,
                'purchase_price' => (float) $p->purchase_price,
                'available_stock' => $p->available_stock,
                'thumbnail' => $p->thumbnail_full_url ?? null,
            ])->all(),
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
    public function getProduct(int $id): ?array
    {
        $product = Product::query()
            ->where('product_type', 'digital')
            ->where('digital_product_type', 'ready_product')
            ->where('status', 1)
            ->where('request_status', 1)
            ->where('partner_approved', 1)
            ->withCount(['digitalProductCodes as available_stock' => function ($q) {
                $q->where('status', 'available')
                    ->where('is_active', true)
                    ->where(function ($q2) {
                        $q2->whereNull('expiry_date')
                            ->orWhereDate('expiry_date', '>=', now()->toDateString());
                    });
            }])
            ->find($id);

        if (! $product) {
            return null;
        }

        return [
            'id' => $product->id,
            'name' => $product->name,
            'slug' => $product->slug,
            'category_id' => $product->category_id,
            'sub_category_id' => $product->sub_category_id,
            'brand_id' => $product->brand_id,
            'unit_price' => (float) $product->unit_price,
            'purchase_price' => (float) $product->purchase_price,
            'available_stock' => $product->available_stock,
            'thumbnail' => $product->thumbnail_full_url ?? null,
            'description' => $product->details,
        ];
    }

    /**
     * Create a reseller order idempotently: debit wallet, assign codes, return result.
     * If $idempotencyKey is provided and a matching record exists, the cached response is returned.
     */
    public function createOrder(ResellerApiKey $resellerKey, int $productId, int $quantity, ?string $reference, ?string $idempotencyKey = null): array
    {
        // ── Idempotency check ────────────────────────────────────────────
        if ($idempotencyKey !== null) {
            $existing = PartnerOrderIdempotency::query()
                ->where('reseller_api_key_id', $resellerKey->id)
                ->where('idempotency_key', $idempotencyKey)
                ->first();

            if ($existing) {
                return array_merge($existing->response_payload, ['idempotent_replay' => true]);
            }
        }

        $product = Product::query()
            ->where('product_type', 'digital')
            ->where('digital_product_type', 'ready_product')
            ->where('status', 1)
            ->where('request_status', 1)
            ->where('partner_approved', 1)
            ->find($productId);

        if (! $product) {
            return ['error' => 'Product not found or not available.', 'status' => 404];
        }

        // Check available stock - skip if supplier mapping exists
        if (! SupplierProductMapping::hasActiveMapping($productId)) {
            $availableCount = DigitalProductCode::query()
                ->where('product_id', $productId)
                ->where('status', 'available')
                ->where('is_active', true)
                ->where(function ($q) {
                    $q->whereNull('expiry_date')
                        ->orWhereDate('expiry_date', '>=', now()->toDateString());
                })
                ->count();

            if ($availableCount < $quantity) {
                return [
                    'error' => 'Insufficient stock.',
                    'available' => $availableCount,
                    'requested' => $quantity,
                    'status' => 409,
                ];
            }
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
                // Debit partner wallet balance
                ResellerApiKey::where('id', $resellerKey->id)
                    ->lockForUpdate()
                    ->decrement('wallet_balance', $totalCost);

                // Create order (seller_id on key acts as the buyer identity)
                $order = Order::create([
                    'customer_id' => null,
                    'customer_type' => 'partner',
                    'payment_status' => 'paid',
                    'order_status' => 'delivered',
                    'payment_method' => 'partner_wallet',
                    'order_amount' => $totalCost,
                    'order_type' => 'default',
                    'order_note' => $reference ? 'Partner ref: '.$reference : 'Partner API order (key #'.$resellerKey->id.')',
                    'is_guest' => 0,
                    'seller_id' => $resellerKey->seller_id,
                ]);

                // Create order detail
                $orderDetail = OrderDetail::create([
                    'order_id' => $order->id,
                    'product_id' => $productId,
                    'seller_id' => $product->user_id,
                    'product_details' => json_encode($product->toArray()),
                    'qty' => $quantity,
                    'price' => $product->unit_price,
                    'tax' => 0,
                    'discount' => 0,
                    'product_type' => 'digital',
                    'digital_product_type' => 'ready_product',
                    'payment_status' => 'paid',
                ]);

                // Assign codes
                $codes = [];
                for ($i = 0; $i < $quantity; $i++) {
                    $code = DigitalProductCode::query()
                        ->where('product_id', $productId)
                        ->where('status', 'available')
                        ->where('is_active', true)
                        ->where(function ($q) {
                            $q->whereNull('expiry_date')
                                ->orWhereDate('expiry_date', '>=', now()->toDateString());
                        })
                        ->lockForUpdate()
                        ->first();

                    if (! $code) {
                        Log::warning('ResellerApiService: ran out of codes mid-assignment', [
                            'product_id' => $productId,
                            'order_id' => $order->id,
                            'assigned' => $i,
                            'requested' => $quantity,
                        ]);
                        break;
                    }

                    $code->update([
                        'status' => 'sold',
                        'order_id' => $order->id,
                        'order_detail_id' => $orderDetail->id,
                        'assigned_at' => now(),
                    ]);

                    $codes[] = [
                        'code' => $code->decryptCode(),
                        'pin' => $code->decryptPin(),
                        'serial' => $code->serial_number,
                        'expiry' => $code->expiry_date?->format('Y-m-d'),
                    ];
                }

                // ── Escrow: credit vendor pending_balance ────────────────────
                $sellerId = $product->user_id;
                SellerWallet::where('seller_id', $sellerId)
                    ->increment('pending_balance', $totalCost);

                // Release escrow after 48 h
                ReleasePartnerEscrowJob::dispatch($order->id, $sellerId, $totalCost)
                    ->delay(now()->addHours(48));

                $result = [
                    'data' => [
                        'order_id' => $order->id,
                        'product_id' => $productId,
                        'product_name' => $product->name,
                        'quantity_requested' => $quantity,
                        'quantity_fulfilled' => count($codes),
                        'total_cost' => $totalCost,
                        'status' => count($codes) === $quantity ? 'fulfilled' : 'partial',
                        'reference' => $reference,
                        'codes' => $codes,
                    ],
                ];

                // ── Persist idempotency record ───────────────────────────────
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
        // Orders placed via partner API are scoped to the seller_id of the key
        $order = Order::query()
            ->where('id', $orderId)
            ->where('seller_id', $resellerKey->seller_id)
            ->where('payment_method', 'partner_wallet')
            ->with(['orderDetails'])
            ->first();

        if (! $order) {
            return null;
        }

        $codes = DigitalProductCode::query()
            ->where('order_id', $order->id)
            ->where('status', 'sold')
            ->get()
            ->map(fn ($code) => [
                'code' => $code->decryptCode(),
                'pin' => $code->decryptPin(),
                'serial' => $code->serial_number,
                'product_id' => $code->product_id,
                'expiry' => $code->expiry_date?->format('Y-m-d'),
            ])
            ->all();

        return [
            'order_id' => $order->id,
            'status' => $order->order_status,
            'payment_status' => $order->payment_status,
            'total' => (float) $order->order_amount,
            'created_at' => $order->created_at?->toIso8601String(),
            'items' => $order->orderDetails->map(fn ($d) => [
                'product_id' => $d->product_id,
                'quantity' => $d->qty,
                'price' => (float) $d->price,
            ])->all(),
            'codes' => $codes,
        ];
    }

    /**
     * Generate a new API key pair for a user.
     */
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
