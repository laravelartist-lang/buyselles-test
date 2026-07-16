<?php

namespace App\Services\Partner;

use App\Models\DigitalProductCode;
use App\Models\Order;
use App\Models\PartnerOrderIdempotency;
use App\Models\ResellerApiKey;
use App\Services\ResellerApiService;

class PartnerApiDocumentationExamplesService
{
    private const DUMMY_ORDER_ID = 100053;

    private const DUMMY_PRODUCT_ID = 14;

    private const DUMMY_CODE = 'XXXX-1234-YYYY-5678';

    private const DUMMY_SERIAL = 'SN-0001';

    private const DUMMY_REFERENCE = 'your-internal-ref-001';

    public function __construct(
        private readonly ResellerApiService $resellerApiService,
        private readonly PartnerWalletService $partnerWallet,
        private readonly PartnerProductCatalogQuery $catalogQuery,
    ) {}

    /**
     * Build sanitized API response examples that mirror live response shapes.
     *
     * @return array{
     *     sample_product_id: int,
     *     sample_order_id: int,
     *     products_list: array<string, mixed>|null,
     *     product_detail: array<string, mixed>|null,
     *     balance: array<string, mixed>|null,
     *     create_order_fulfilled: array<string, mixed>|null,
     *     create_order_pending: array<string, mixed>|null,
     *     create_order_idempotent_replay: array<string, mixed>|null,
     *     order_detail: array<string, mixed>|null,
     *     catalog_field_sample: array<string, mixed>|null,
     * }
     */
    public function build(): array
    {
        $sampleKey = ResellerApiKey::query()
            ->where('status', 'active')
            ->orderBy('id')
            ->first();

        $productsList = $sampleKey !== null
            ? $this->resellerApiService->listProducts(
                resellerKey: $sampleKey,
                search: null,
                categoryId: null,
                page: 1,
                perPage: 20,
            )
            : ['data' => [], 'meta' => ['current_page' => 1, 'last_page' => 1, 'per_page' => 20, 'total' => 0]];

        $liveProduct = $productsList['data'][0] ?? null;
        $liveProductDetail = $liveProduct !== null && $sampleKey !== null
            ? $this->resellerApiService->getProduct($sampleKey, (int) $liveProduct['id'])
            : null;

        $walletSource = $sampleKey !== null && $this->partnerWallet->usesVendorWallet($sampleKey)
            ? 'vendor'
            : 'customer';

        $createOrderFulfilled = $this->findCreateOrderExample('fulfilled')
            ?? $this->buildCreateOrderExampleFromOrder($sampleKey, 'fulfilled');
        $createOrderPending = $this->findCreateOrderExample('pending_fulfillment')
            ?? $this->buildCreateOrderExampleFromOrder($sampleKey, 'pending_fulfillment');
        $createOrderIdempotentReplay = $this->findIdempotentReplayExample();
        $orderDetail = $this->buildOrderDetailExample($sampleKey, $createOrderFulfilled);

        $examples = [
            'sample_product_id' => self::DUMMY_PRODUCT_ID,
            'sample_order_id' => self::DUMMY_ORDER_ID,
            'products_list' => ! empty($productsList['data'])
                ? $this->sanitizeProductsList($productsList, $liveProduct)
                : null,
            'product_detail' => $liveProductDetail !== null
                ? ['data' => $this->sanitizeProductDetail($liveProductDetail)]
                : null,
            'balance' => [
                'data' => [
                    'balance' => 1250.00,
                    'currency' => 'USD',
                    'key_id' => 1,
                    'key_name' => 'My Integration Key',
                    'wallet_source' => $walletSource,
                ],
            ],
            'create_order_fulfilled' => $this->sanitizeCreateOrderResponse($createOrderFulfilled, 'fulfilled'),
            'create_order_pending' => $this->sanitizeCreateOrderResponse($createOrderPending, 'pending_fulfillment'),
            'create_order_idempotent_replay' => $this->sanitizeIdempotentReplay($createOrderIdempotentReplay, $createOrderFulfilled),
            'order_detail' => $this->sanitizeOrderDetail($orderDetail),
            'catalog_field_sample' => $liveProduct !== null
                ? $this->sanitizeProductListItem($liveProduct)
                : null,
        ];

        return $examples;
    }

    public function formatJson(mixed $data): string
    {
        if ($data === null) {
            return '{}';
        }

        return (string) json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * @param  array<string, mixed>  $productsList
     * @param  array<string, mixed>|null  $liveProduct
     * @return array<string, mixed>
     */
    private function sanitizeProductsList(array $productsList, ?array $liveProduct): array
    {
        $item = $liveProduct !== null
            ? $this->sanitizeProductListItem($liveProduct)
            : $this->dummyProductListItem();

        return [
            'data' => [$item],
            'meta' => [
                'current_page' => (int) ($productsList['meta']['current_page'] ?? 1),
                'last_page' => max(1, (int) ($productsList['meta']['last_page'] ?? 1)),
                'per_page' => (int) ($productsList['meta']['per_page'] ?? 20),
                'total' => max(1, (int) ($productsList['meta']['total'] ?? 1)),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $product
     * @return array<string, mixed>
     */
    private function sanitizeProductListItem(array $product): array
    {
        return [
            'id' => self::DUMMY_PRODUCT_ID,
            'name' => 'Example Digital Product',
            'slug' => 'example-digital-product',
            'category_id' => is_numeric($product['category_id'] ?? null) ? (int) $product['category_id'] : 1,
            'pricing' => [
                'type' => 'fixed',
                'currency' => 'USD',
                'unit_price' => 20.00,
            ],
            'available_stock' => 42,
            'thumbnail' => $this->dummyThumbnail($product['thumbnail'] ?? null),
            'seller_type' => $product['seller_type'] ?? 'in_house',
            'fulfillment_type' => $product['fulfillment_type'] ?? 'local_codes',
            'supplier' => ($product['fulfillment_type'] ?? null) === 'supplier_codes'
                ? ($product['supplier'] ?? 'bamboo')
                : null,
            'requires_account_id' => false,
            'direct_topup' => null,
        ];
    }

    /**
     * @param  array<string, mixed>  $product
     * @return array<string, mixed>
     */
    private function sanitizeProductDetail(array $product): array
    {
        return array_merge($this->sanitizeProductListItem($product), [
            'sub_category_id' => is_numeric($product['sub_category_id'] ?? null) ? (int) $product['sub_category_id'] : null,
            'brand_id' => null,
            'description' => '<p>Example product description shown in Partner API documentation.</p>',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function dummyProductListItem(): array
    {
        return [
            'id' => self::DUMMY_PRODUCT_ID,
            'name' => 'Example Digital Product',
            'slug' => 'example-digital-product',
            'category_id' => 1,
            'pricing' => [
                'type' => 'fixed',
                'currency' => 'USD',
                'unit_price' => 20.00,
            ],
            'available_stock' => 42,
            'thumbnail' => $this->dummyThumbnail(null),
            'seller_type' => 'in_house',
            'fulfillment_type' => 'local_codes',
            'supplier' => null,
            'requires_account_id' => false,
        ];
    }

    /**
     * @param  array<string, mixed>|array<int, string>|string|null  $thumbnail
     * @return array<string, mixed>|null
     */
    private function dummyThumbnail(mixed $thumbnail): ?array
    {
        if (is_array($thumbnail) && array_key_exists('path', $thumbnail)) {
            $host = parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'example.com';

            return [
                'key' => 'example-thumbnail.webp',
                'path' => 'https://'.$host.'/storage/product/thumbnail/example-thumbnail.webp',
                'status' => 200,
            ];
        }

        if (is_string($thumbnail) && $thumbnail !== '') {
            return [
                'key' => 'example-thumbnail.webp',
                'path' => $thumbnail,
                'status' => 200,
            ];
        }

        return null;
    }

    /**
     * @param  array<string, mixed>|null  $response
     * @return array<string, mixed>|null
     */
    private function sanitizeCreateOrderResponse(?array $response, string $status): ?array
    {
        if ($response === null && $status === 'pending_fulfillment') {
            return [
                'data' => [
                    'order_id' => self::DUMMY_ORDER_ID + 1,
                    'product_id' => self::DUMMY_PRODUCT_ID,
                    'product_name' => 'Example Digital Product',
                    'quantity_requested' => 1,
                    'quantity_fulfilled' => 0,
                    'total_cost' => 20.00,
                    'status' => 'pending_fulfillment',
                    'reference' => 'your-internal-ref-002',
                    'codes' => [],
                ],
            ];
        }

        if ($response === null) {
            return [
                'data' => [
                    'order_id' => self::DUMMY_ORDER_ID,
                    'product_id' => self::DUMMY_PRODUCT_ID,
                    'product_name' => 'Example Digital Product',
                    'quantity_requested' => 1,
                    'quantity_fulfilled' => 1,
                    'total_cost' => 20.00,
                    'status' => 'fulfilled',
                    'reference' => self::DUMMY_REFERENCE,
                    'codes' => [$this->dummyCodeEntry(includePin: false)],
                ],
            ];
        }

        $data = $response['data'] ?? [];
        $codes = $data['codes'] ?? [];
        $includePin = $this->responseIncludesPin($codes);

        return [
            'data' => [
                'order_id' => self::DUMMY_ORDER_ID,
                'product_id' => self::DUMMY_PRODUCT_ID,
                'product_name' => 'Example Digital Product',
                'quantity_requested' => max(1, (int) ($data['quantity_requested'] ?? 1)),
                'quantity_fulfilled' => $status === 'fulfilled'
                    ? max(1, (int) ($data['quantity_requested'] ?? 1))
                    : 0,
                'total_cost' => 20.00,
                'status' => $status,
                'reference' => $status === 'pending_fulfillment'
                    ? 'your-internal-ref-002'
                    : self::DUMMY_REFERENCE,
                'codes' => $status === 'fulfilled'
                    ? [$this->dummyCodeEntry($includePin)]
                    : [],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>|null  $response
     * @param  array<string, mixed>|null  $fallbackFulfilled
     * @return array<string, mixed>|null
     */
    private function sanitizeIdempotentReplay(?array $response, ?array $fallbackFulfilled): ?array
    {
        $sanitized = $this->sanitizeCreateOrderResponse($response ?? $fallbackFulfilled, 'fulfilled');

        if ($sanitized === null) {
            return null;
        }

        return array_merge($sanitized, ['idempotent_replay' => true]);
    }

    /**
     * @param  array<string, mixed>|null  $response
     * @return array<string, mixed>|null
     */
    private function sanitizeOrderDetail(?array $response): ?array
    {
        if ($response === null) {
            return [
                'data' => [
                    'order_id' => self::DUMMY_ORDER_ID,
                    'status' => 'delivered',
                    'payment_status' => 'paid',
                    'fulfillment_status' => 'fulfilled',
                    'quantity_requested' => 1,
                    'quantity_fulfilled' => 1,
                    'total' => 20.00,
                    'created_at' => '2026-04-09T12:34:56+00:00',
                    'items' => [
                        [
                            'product_id' => self::DUMMY_PRODUCT_ID,
                            'quantity' => 1,
                            'price' => 20.00,
                        ],
                    ],
                    'codes' => [$this->dummyCodeEntry(includePin: false)],
                ],
            ];
        }

        $data = $response['data'] ?? [];
        $codes = $data['codes'] ?? [];

        return [
            'data' => [
                'order_id' => self::DUMMY_ORDER_ID,
                'status' => $data['status'] ?? 'delivered',
                'payment_status' => $data['payment_status'] ?? 'paid',
                'fulfillment_status' => $data['fulfillment_status'] ?? 'fulfilled',
                'quantity_requested' => max(1, (int) ($data['quantity_requested'] ?? 1)),
                'quantity_fulfilled' => max(1, (int) ($data['quantity_fulfilled'] ?? 1)),
                'total' => 20.00,
                'created_at' => '2026-04-09T12:34:56+00:00',
                'items' => [
                    [
                        'product_id' => self::DUMMY_PRODUCT_ID,
                        'quantity' => max(1, (int) ($data['quantity_requested'] ?? 1)),
                        'price' => 20.00,
                    ],
                ],
                'codes' => $this->responseIncludesPin($codes)
                    ? [$this->dummyCodeEntry(includePin: true)]
                    : [$this->dummyCodeEntry(includePin: false)],
            ],
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $codes
     */
    private function responseIncludesPin(array $codes): bool
    {
        foreach ($codes as $code) {
            if (! empty($code['pin'])) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, mixed>
     */
    private function dummyCodeEntry(bool $includePin): array
    {
        return [
            'code' => self::DUMMY_CODE,
            'pin' => $includePin ? '1234' : null,
            'serial' => self::DUMMY_SERIAL,
            'product_id' => self::DUMMY_PRODUCT_ID,
            'expiry' => '2026-12-31',
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findCreateOrderExample(string $status): ?array
    {
        $records = PartnerOrderIdempotency::query()
            ->latest('id')
            ->limit(25)
            ->get();

        foreach ($records as $record) {
            $payload = $record->response_payload;

            if (($payload['data']['status'] ?? null) === $status) {
                return $payload;
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findIdempotentReplayExample(): ?array
    {
        $record = PartnerOrderIdempotency::query()->latest('id')->first();

        if ($record === null) {
            return null;
        }

        return array_merge($record->response_payload, ['idempotent_replay' => true]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function buildCreateOrderExampleFromOrder(?ResellerApiKey $sampleKey, string $status): ?array
    {
        $order = Order::query()
            ->where('payment_method', 'partner_wallet')
            ->when($sampleKey?->seller_id, fn ($query, $sellerId) => $query->where('seller_id', $sellerId))
            ->with(['orderDetails.product'])
            ->latest('id')
            ->get()
            ->first(function (Order $candidate) use ($status): bool {
                $codes = DigitalProductCode::query()
                    ->where('order_id', $candidate->id)
                    ->where('status', 'sold')
                    ->count();
                $requested = (int) $candidate->orderDetails->sum('qty');
                $fulfillmentStatus = $codes >= $requested && $requested > 0
                    ? 'fulfilled'
                    : 'pending_fulfillment';

                return $fulfillmentStatus === $status;
            });

        if ($order === null) {
            return null;
        }

        $detail = $order->orderDetails->first();
        $codes = $this->mapOrderCodes($order);
        $quantityRequested = (int) $order->orderDetails->sum('qty');
        $quantityFulfilled = count($codes);

        return [
            'data' => [
                'order_id' => $order->id,
                'product_id' => $detail?->product_id,
                'product_name' => $detail?->product?->name,
                'quantity_requested' => $quantityRequested,
                'quantity_fulfilled' => $quantityFulfilled,
                'total_cost' => (float) $order->order_amount,
                'status' => $quantityFulfilled >= $quantityRequested && $quantityRequested > 0
                    ? 'fulfilled'
                    : 'pending_fulfillment',
                'reference' => $this->extractReference($order->order_note),
                'codes' => $codes,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>|null  $createOrderFulfilled
     * @return array<string, mixed>|null
     */
    private function buildOrderDetailExample(?ResellerApiKey $sampleKey, ?array $createOrderFulfilled): ?array
    {
        $orderId = $createOrderFulfilled['data']['order_id'] ?? null;

        if ($orderId !== null && $sampleKey !== null) {
            $orderDetail = $this->resellerApiService->getOrder((int) $orderId, $sampleKey);

            if ($orderDetail !== null) {
                return ['data' => $orderDetail];
            }
        }

        $order = Order::query()
            ->where('payment_method', 'partner_wallet')
            ->when($sampleKey?->seller_id, fn ($query, $sellerId) => $query->where('seller_id', $sellerId))
            ->with('orderDetails')
            ->latest('id')
            ->first();

        if ($order === null) {
            return null;
        }

        $codes = $this->mapOrderCodes($order);
        $quantityRequested = (int) $order->orderDetails->sum('qty');
        $quantityFulfilled = count($codes);

        return [
            'data' => [
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
            ],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function mapOrderCodes(Order $order): array
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

    private function extractReference(?string $orderNote): ?string
    {
        if ($orderNote === null) {
            return null;
        }

        if (preg_match('/Partner ref:\s*(.+)$/i', $orderNote, $matches) === 1) {
            return trim($matches[1]);
        }

        return null;
    }
}
