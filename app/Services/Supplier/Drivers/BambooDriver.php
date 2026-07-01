<?php

namespace App\Services\Supplier\Drivers;

use App\Contracts\SupplierDriverInterface;
use App\DTOs\Supplier\BalanceResult;
use App\DTOs\Supplier\HealthResult;
use App\DTOs\Supplier\StockResult;
use App\DTOs\Supplier\SupplierOrderResult;
use App\DTOs\Supplier\SupplierProductDTO;
use App\DTOs\Supplier\WebhookResult;
use App\Models\SupplierApi;
use Illuminate\Http\Client\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Bamboo Card Portal API driver (V2).
 *
 * Auth: HTTP Basic Auth — ClientId as username, ClientSecret as password.
 * Host: https://api.bamboocardportal.com  (same for sandbox and production;
 *       credentials differ per environment)
 *
 * Rate limits:
 *  - Catalog V2:   1 req/s
 *  - Place Order:  2 req/s
 *  - Get Order:  120 req/min
 *  - Other:        1 req/s
 *
 * Docs: https://docs.bamboocardportal.com
 */
class BambooDriver implements SupplierDriverInterface
{
    private SupplierApi $supplier;

    /** @var array<string, mixed> */
    private array $credentials = [];

    /** @var array<string, mixed> */
    private array $settings = [];

    public function configure(SupplierApi $supplier): static
    {
        $this->supplier = $supplier;
        $this->credentials = $supplier->getDecryptedCredentials();
        $this->settings = $supplier->settings ?? [];

        return $this;
    }

    // ─── SupplierDriverInterface ──────────────────────────────────────────────

    public function authenticate(): bool
    {
        try {
            $response = $this->get('/api/integration/v2.0/catalog', [
                'PageSize' => 1,
                'PageIndex' => 0,
            ]);

            return $response->successful();
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Fetch the Bamboo product catalog.
     *
     * Each catalog "item" is a brand containing one or more products (SKUs).
     * We flatten brand → products into individual SupplierProductDTOs
     * so the rest of the system can map them 1-to-1.
     *
     * When `fetch_all` is true (default when no explicit page is given), the method
     * automatically paginates through every page and returns the complete catalog.
     * The Bamboo API paginates by **brand**, not by product, so a single page of
     * 100 brands can contain many more products.
     *
     * @param  array<string, mixed>  $filters  Accepts: country_code, currency_code,
     *                                         name, page, size, modified_since,
     *                                         brand_id, product_id, target_currency,
     *                                         fetch_all (bool, default true)
     * @return SupplierProductDTO[]
     */
    public function fetchProducts(array $filters = []): array
    {
        // When a caller explicitly passes a page number, honour it (single-page mode).
        // Otherwise default to fetching every page automatically.
        $fetchAll = $filters['fetch_all'] ?? ! isset($filters['page']);

        $pageSize = min((int) ($filters['size'] ?? 100), 100); // API cap: 100 brands/page
        $startPage = (int) ($filters['page'] ?? 0);

        // Optional progress callback: fn(int $page, int $brandsOnPage, int $totalBrandCount): void
        $onPage = $filters['on_page'] ?? null;

        $baseParams = [];

        if (! empty($filters['country_code'])) {
            $baseParams['CountryCode'] = strtoupper($filters['country_code']);
        }

        if (! empty($filters['currency_code'])) {
            $baseParams['CurrencyCode'] = strtoupper($filters['currency_code']);
        }

        if (! empty($filters['name'])) {
            $baseParams['Name'] = $filters['name'];
        }

        if (! empty($filters['modified_since'])) {
            $baseParams['ModifiedDate'] = $filters['modified_since'];
        }

        if (! empty($filters['product_id'])) {
            $baseParams['ProductId'] = $filters['product_id'];
        }

        if (! empty($filters['brand_id'])) {
            $baseParams['BrandId'] = $filters['brand_id'];
        }

        if (! empty($filters['target_currency'])) {
            $baseParams['TargetCurrency'] = strtoupper($filters['target_currency']);
        }

        $dtos = [];
        $currentPage = $startPage;

        do {
            $params = array_merge($baseParams, [
                'PageIndex' => $currentPage,
                'PageSize' => $pageSize,
            ]);

            // The full catalog response can be large; allow 2 minutes per page.
            $response = $this->get('/api/integration/v2.0/catalog', $params, timeout: 120);

            if ($response->failed()) {
                throw new \RuntimeException("Bamboo fetchProducts failed: HTTP {$response->status()} — {$response->body()}");
            }

            $body = $response->json();
            $items = $body['items'] ?? [];

            // Bamboo returns { pageIndex, pageSize, count, items }
            // where `count` is the total number of *brands* across all pages.
            $totalBrands = (int) ($body['count'] ?? 0);

            // Fire progress callback so callers (e.g. jobs) can track progress.
            if (is_callable($onPage)) {
                $onPage($currentPage, count($items), $totalBrands);
            }

            foreach ($items as $brand) {
                $brandName = (string) ($brand['name'] ?? '');
                $countryCode = (string) ($brand['countryCode'] ?? '');
                $currencyCode = (string) ($brand['currencyCode'] ?? '');
                $description = $brand['description'] ?? null;
                $logoUrl = $brand['logoUrl'] ?? null;

                foreach ($brand['products'] ?? [] as $product) {
                    $productId = (string) ($product['id'] ?? '');

                    if ($productId === '') {
                        continue;
                    }

                    $minPrice = (float) ($product['price']['min'] ?? $product['minFaceValue'] ?? 0);
                    $priceCurrency = (string) ($product['price']['currencyCode'] ?? $currencyCode);

                    $dtos[] = new SupplierProductDTO(
                        supplierProductId: $productId,
                        name: (string) ($product['name'] ?? $brandName),
                        description: $description,
                        category: null,
                        imageUrl: $logoUrl,
                        price: $minPrice,
                        currency: $priceCurrency,
                        stockAvailable: ($product['count'] ?? null) !== null ? (int) $product['count'] : 999,
                        region: $countryCode ?: null,
                        rawData: array_merge($brand, ['_product' => $product]),
                    );
                }
            }

            $currentPage++;

            // Stop when: single-page mode, empty page, or all brands fetched.
        } while ($fetchAll && count($items) >= $pageSize && ($currentPage * $pageSize) < ($totalBrands + $pageSize));

        return $dtos;
    }

    /**
     * Check availability of a single Bamboo product SKU.
     *
     * Bamboo stock is soft — cards are replenished continuously.
     * We query the catalog with the specific ProductId for the freshest price/count.
     */
    public function fetchStock(string $supplierProductId): StockResult
    {
        $response = $this->get('/api/integration/v2.0/catalog', [
            'ProductId' => (int) $supplierProductId,
            'PageSize' => 1,
            'PageIndex' => 0,
        ]);

        if ($response->failed()) {
            throw new \RuntimeException("Bamboo fetchStock failed: HTTP {$response->status()}");
        }

        // Walk items → products to find the matching SKU
        foreach ($response->json('items', []) as $brand) {
            foreach ($brand['products'] ?? [] as $product) {
                if ((string) ($product['id'] ?? '') !== $supplierProductId) {
                    continue;
                }

                $available = ($product['count'] ?? null) !== null ? (int) $product['count'] : 999;
                $price = (float) ($product['price']['min'] ?? $product['minFaceValue'] ?? 0);
                $currency = (string) ($product['price']['currencyCode'] ?? $brand['currencyCode'] ?? 'USD');

                return new StockResult(
                    available: $available,
                    price: $price,
                    currency: $currency,
                    rawData: array_merge($brand, ['_product' => $product]),
                );
            }
        }

        return new StockResult(available: 0, price: 0.0, currency: 'USD', rawData: []);
    }

    /**
     * Place an order with Bamboo using the V1 async API.
     *
     * V1 is fully asynchronous — the response is simply the RequestId string.
     * Codes are delivered later via Bamboo's order-notification webhook.
     *
     * Endpoint: POST /api/integration/v1.0/orders/checkout
     * Body:     { RequestId, AccountId, Products: [{ ProductId, Quantity, Value }] }
     * Response: "<RequestId>"  (plain JSON string)
     */
    public function placeOrder(string $supplierProductId, int $quantity, ?float $unitPrice = null): SupplierOrderResult
    {
        $accountId = $this->resolveAccountId();

        if ($accountId === 0) {
            throw new \RuntimeException('Bamboo placeOrder: could not resolve account_id — set it in credentials or ensure the accounts API is reachable.');
        }

        // V1 Value = the card face value (denomination), NOT the wholesale cost.
        // For customizable/variable products, unitPrice IS the customer-chosen face value.
        // For fixed cards, fetch from catalog to ensure correctness.
        $faceValue = ($unitPrice && $unitPrice > 0) ? $unitPrice : $this->getProductFaceValue($supplierProductId);

        if ($faceValue <= 0) {
            throw new \RuntimeException("Bamboo placeOrder: could not determine face value for product {$supplierProductId} from catalog.");
        }

        $requestId = (string) \Illuminate\Support\Str::uuid();

        $body = [
            'RequestId' => $requestId,
            'AccountId' => $accountId,
            'Products' => [
                [
                    'ProductId' => (int) $supplierProductId,
                    'Quantity' => $quantity,
                    'Value' => $faceValue,
                ],
            ],
        ];

        $response = $this->post('/api/integration/v1.0/orders/checkout', $body);

        if ($response->failed()) {
            throw new \RuntimeException("Bamboo placeOrder failed: HTTP {$response->status()} — {$response->body()}");
        }

        // V1 response body is just the RequestId string: "71ac2817-..."
        $returnedId = trim($response->body(), '" \n\r\t');
        $supplierOrderId = $returnedId !== '' ? $returnedId : $requestId;

        // V1 is always async — codes come via webhook
        return new SupplierOrderResult(
            supplierOrderId: $supplierOrderId,
            status: 'processing',
            codes: [],
            rawResponse: ['request_id' => $supplierOrderId],
        );
    }

    /**
     * Place a direct top-up order with Bamboo using the V1 async API.
     *
     * For Bamboo, a "direct top-up" is the same as a regular order — we purchase
     * gift card products on behalf of the customer. The customer's external account
     * ID ($accountId) is stored in our records for reference but is NOT sent to
     * Bamboo; Bamboo uses its own AccountId for billing resolution.
     *
     * Endpoint: POST /api/integration/v1.0/orders/checkout
     * Body:     { RequestId, AccountId, Products: [{ ProductId, Quantity, Value }] }
     * Response: "<RequestId>"  (plain JSON string)
     */
    public function placeTopUpOrder(
        string $supplierProductId,
        float $quantity,
        string $accountId,
        ?float $unitPrice = null,
    ): SupplierOrderResult {
        $bambooAccountId = $this->resolveAccountId();

        if ($bambooAccountId === 0) {
            throw new \RuntimeException('Bamboo placeTopUpOrder: could not resolve account_id — set it in credentials or ensure the accounts API is reachable.');
        }

        // The face value of the gift card, NOT the wholesale cost
        $faceValue = ($unitPrice && $unitPrice > 0) ? $unitPrice : $this->getProductFaceValue($supplierProductId);

        if ($faceValue <= 0) {
            throw new \RuntimeException("Bamboo placeTopUpOrder: could not determine face value for product {$supplierProductId} from catalog.");
        }

        $requestId = (string) \Illuminate\Support\Str::uuid();

        $body = [
            'RequestId' => $requestId,
            'AccountId' => $bambooAccountId,
            'Products' => [
                [
                    'ProductId' => (int) $supplierProductId,
                    'Quantity' => (int) ceil($quantity),
                    'Value' => $faceValue,
                ],
            ],
        ];

        $response = $this->post('/api/integration/v1.0/orders/checkout', $body);

        if ($response->failed()) {
            throw new \RuntimeException("Bamboo placeTopUpOrder failed: HTTP {$response->status()} — {$response->body()}");
        }

        // V1 response body is just the RequestId string: "71ac2817-..."
        $returnedId = trim($response->body(), '" \n\r\t');
        $supplierOrderId = $returnedId !== '' ? $returnedId : $requestId;

        // V1 is always async — codes come via webhook or polling
        return new SupplierOrderResult(
            supplierOrderId: $supplierOrderId,
            status: 'processing',
            codes: [],
            rawResponse: ['request_id' => $supplierOrderId],
        );
    }

    /**
     * Poll the status of an existing Bamboo V1 order.
     *
     * Endpoint: GET /api/integration/v1.0/orders/{requestId}
     *
     * Response format:
     *   { "items": [{ "cards": [{ "cardCode", "pin", "serialNumber", "expirationDate", "status" }] }], "status": "Succeeded" }
     */
    public function getOrderStatus(string $supplierOrderId): SupplierOrderResult
    {
        $response = $this->get("/api/integration/v1.0/orders/{$supplierOrderId}");

        if ($response->failed()) {
            throw new \RuntimeException("Bamboo getOrderStatus failed: HTTP {$response->status()}");
        }

        $data = $response->json() ?? [];

        $rawStatus = strtolower((string) ($data['Status'] ?? $data['status'] ?? 'pending'));
        $codes = $this->extractCardsFromOrderResponse($data);

        $normalizedStatus = match ($rawStatus) {
            'completed', 'success', 'succeeded' => 'fulfilled',
            'pending', 'processing', 'inprogress' => count($codes) > 0 ? 'partial' : 'processing',
            'failed', 'error', 'cancelled', 'canceled' => 'failed',
            default => count($codes) > 0 ? 'fulfilled' : 'processing',
        };

        return new SupplierOrderResult(
            supplierOrderId: $supplierOrderId,
            status: $normalizedStatus,
            codes: $codes,
            rawResponse: $data,
        );
    }

    /**
     * Parse and verify a Bamboo order notification webhook.
     *
     * Bamboo POSTs to the configured notification URL when an order completes.
     * The secretKey (from GET /api/Integration/v1/notification) is echoed back
     * in the webhook payload's "secretKey" field for basic verification.
     */
    public function parseWebhook(Request $request): WebhookResult
    {
        $payload = $request->all();

        // Verify the secretKey echoed back in the payload
        $expectedSecret = $this->settings['webhook_secret'] ?? null;

        if ($expectedSecret !== null) {
            $receivedSecret = $payload['secretKey'] ?? $request->header('X-Secret-Key');

            if (! hash_equals((string) $expectedSecret, (string) $receivedSecret)) {
                Log::warning('BambooDriver: webhook secretKey mismatch', [
                    'supplier_id' => $this->supplier->id,
                ]);

                return new WebhookResult(type: 'unknown', verified: false, rawPayload: $payload);
            }
        }

        // V1 webhook uses PascalCase keys; V2 uses camelCase — handle both
        $orderId = (string) ($payload['RequestId'] ?? $payload['OrderId'] ?? $payload['orderId'] ?? '');
        $rawStatus = (string) ($payload['Status'] ?? $payload['status'] ?? 'unknown');
        $status = strtolower($rawStatus);
        $codes = $this->extractCodes($payload);

        $type = match ($status) {
            'completed', 'success', 'succeeded' => 'order_fulfilled',
            'failed', 'error', 'cancelled', 'canceled' => 'order_failed',
            default => 'unknown',
        };

        return new WebhookResult(
            type: $type,
            supplierOrderId: $orderId !== '' ? $orderId : null,
            codes: $codes,
            status: $status,
            verified: true,
            rawPayload: $payload,
        );
    }

    /**
     * Health check — calls catalog with minimal response to measure latency.
     */
    public function healthCheck(): HealthResult
    {
        $start = microtime(true);

        try {
            $response = $this->get('/api/integration/v2.0/catalog', [
                'PageSize' => 1,
                'PageIndex' => 0,
            ]);

            $latency = (int) ((microtime(true) - $start) * 1000);

            if ($response->successful()) {
                return new HealthResult(
                    status: $latency > 5000 ? 'degraded' : 'healthy',
                    latencyMs: $latency,
                    message: "HTTP {$response->status()} in {$latency}ms",
                );
            }

            return new HealthResult(
                status: 'down',
                latencyMs: $latency,
                message: "HTTP {$response->status()}: {$response->body()}",
            );
        } catch (\Throwable $e) {
            $latency = (int) ((microtime(true) - $start) * 1000);

            return new HealthResult(status: 'down', latencyMs: $latency, message: $e->getMessage());
        }
    }

    /**
     * Retrieve account balance from Bamboo.
     *
     * Bamboo exposes balance information via the Transactions endpoint.
     * If no balance endpoint is available, returns unsupported.
     */
    public function getBalance(): BalanceResult
    {
        try {
            $response = $this->get('/api/integration/v1.0/accounts');

            if ($response->successful()) {
                $accounts = $response->json('accounts', []);
                $targetId = (int) ($this->credentials['account_id'] ?? 0);

                // Pick the matching account, or the first active one
                $account = null;
                foreach ($accounts as $acc) {
                    if ($targetId > 0 && (int) ($acc['id'] ?? 0) === $targetId) {
                        $account = $acc;
                        break;
                    }
                    if (($acc['isActive'] ?? false) && $account === null) {
                        $account = $acc;
                    }
                }

                if ($account) {
                    return new BalanceResult(
                        supported: true,
                        balance: (float) ($account['balance'] ?? 0),
                        currency: (string) ($account['currency'] ?? 'USD'),
                    );
                }
            }

            return BalanceResult::unsupported();
        } catch (\Throwable) {
            return BalanceResult::unsupported();
        }
    }

    public function getRequiredCredentialFields(): array
    {
        return [
            'client_id' => [
                'label' => 'Client ID',
                'type' => 'text',
                'required' => true,
            ],
            'client_secret' => [
                'label' => 'Client Secret',
                'type' => 'password',
                'required' => true,
            ],
            'account_id' => [
                'label' => 'Account ID (optional — auto-detected from Bamboo accounts API if left empty)',
                'type' => 'number',
                'required' => false,
            ],
        ];
    }

    public function getConfigSchema(): array
    {
        return [
            'face_value' => [
                'label' => 'Card Face Value / Denomination (required — e.g. 10 for a $10 card)',
                'type' => 'number',
                'default' => null,
            ],
            'webhook_secret' => [
                'label' => 'Webhook Secret Key (from Bamboo notification settings)',
                'type' => 'password',
                'default' => null,
            ],
        ];
    }

    // ─── Internal helpers ─────────────────────────────────────────────────────

    /**
     * Extract redemption codes from a Bamboo order response / webhook payload.
     *
     * Bamboo returns codes under "products[].pinCode" and/or "products[].serialNumber".
     * A non-empty pinCode is the primary code; serialNumber is a secondary identifier.
     *
     * @param  array<string, mixed>  $data
     * @return string[]
     */
    /**
     * Extract redemption codes from a Bamboo V1/V2 order response or webhook payload.
     *
     * V1 webhook uses PascalCase: Products[].PinCode / SerialNumber
     * V2 response uses camelCase: products[].pinCode / serialNumber
     *
     * @param  array<string, mixed>  $data
     * @return string[]
     */
    private function extractCodes(array $data): array
    {
        $codes = [];

        // V1 uses 'Products' (PascalCase), V2 uses 'products' (camelCase)
        $products = $data['Products'] ?? $data['products'] ?? [];

        foreach ($products as $product) {
            // V1: PinCode / SerialNumber (PascalCase); V2: pinCode / serialNumber (camelCase)
            $pin = trim((string) ($product['PinCode'] ?? $product['pinCode'] ?? ''));
            $serial = trim((string) ($product['SerialNumber'] ?? $product['serialNumber'] ?? ''));

            if ($pin !== '') {
                // If both exist, combine as "SERIAL:PIN" so nothing is lost
                $codes[] = $serial !== '' && $serial !== $pin
                    ? "{$serial}:{$pin}"
                    : $pin;
            } elseif ($serial !== '') {
                $codes[] = $serial;
            }
        }

        return $codes;
    }

    /**
     * Extract structured card data from a Bamboo GET /orders/{requestId} response.
     *
     * The GET response uses: items[].cards[].{cardCode, pin, serialNumber, expirationDate, status}
     * Returns structured arrays compatible with bulkAddToPool().
     *
     * @param  array<string, mixed>  $data
     * @return array<int, array{code: string, pin?: string|null, serial_number?: string|null, expiry_date?: string|null}>
     */
    private function extractCardsFromOrderResponse(array $data): array
    {
        $codes = [];

        $items = $data['Items'] ?? $data['items'] ?? [];

        foreach ($items as $item) {
            $cards = $item['Cards'] ?? $item['cards'] ?? [];

            foreach ($cards as $card) {
                $cardCode = trim((string) ($card['CardCode'] ?? $card['cardCode'] ?? ''));
                $pin = trim((string) ($card['Pin'] ?? $card['pin'] ?? ''));
                $serial = trim((string) ($card['SerialNumber'] ?? $card['serialNumber'] ?? ''));
                $expiry = $card['ExpirationDate'] ?? $card['expirationDate'] ?? null;

                if ($cardCode === '') {
                    continue;
                }

                $entry = ['code' => $cardCode];

                if ($pin !== '') {
                    $entry['pin'] = $pin;
                }
                if ($serial !== '') {
                    $entry['serial_number'] = $serial;
                }
                if ($expiry !== null) {
                    try {
                        $entry['expiry_date'] = \Carbon\Carbon::parse($expiry)->format('Y-m-d');
                    } catch (\Throwable) {
                        $entry['expiry_date'] = null;
                    }
                }

                $codes[] = $entry;
            }
        }

        return $codes;
    }

    /**
     * Resolve the Bamboo account ID.
     *
     * Priority: credentials → cached → fetched from /api/integration/v1.0/accounts.
     * The first active account is used when multiple exist.
     */
    private function resolveAccountId(): int
    {
        // 1. Explicitly set in credentials
        $fromCreds = (int) ($this->credentials['account_id'] ?? 0);
        if ($fromCreds > 0) {
            return $fromCreds;
        }

        // 2. Cached (per supplier, 24 hours)
        $cacheKey = "bamboo_account_id_{$this->supplier->id}";
        $cached = \Illuminate\Support\Facades\Cache::get($cacheKey);
        if ($cached) {
            return (int) $cached;
        }

        // 3. Fetch from Bamboo accounts API
        try {
            $response = $this->get('/api/integration/v1.0/accounts');

            if ($response->successful()) {
                $accounts = $response->json('accounts', []);

                // Pick the first active account
                foreach ($accounts as $acc) {
                    if ($acc['isActive'] ?? false) {
                        $id = (int) ($acc['id'] ?? 0);
                        if ($id > 0) {
                            \Illuminate\Support\Facades\Cache::put($cacheKey, $id, now()->addHours(24));

                            return $id;
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Bamboo: failed to auto-detect account_id', [
                'supplier_id' => $this->supplier->id,
                'error' => $e->getMessage(),
            ]);
        }

        return 0;
    }

    /**
     * Fetch the face value (denomination) for a Bamboo product from the V2 catalog.
     *
     * The V1 order endpoint requires the card's face value in the Value field,
     * which is NOT the wholesale/cost price. For fixed-denomination cards,
     * minFaceValue == maxFaceValue. For variable cards, use minFaceValue.
     *
     * Results are cached for 24 hours to survive intermittent Bamboo API outages.
     */
    private function getProductFaceValue(string $supplierProductId): float
    {
        $cacheKey = "bamboo_face_value:{$supplierProductId}";

        // Return cached value if available (survives intermittent 403s)
        $cached = Cache::get($cacheKey);
        if ($cached !== null && (float) $cached > 0) {
            return (float) $cached;
        }

        try {
            $response = $this->get('/api/integration/v2.0/catalog', [
                'ProductId' => (int) $supplierProductId,
                'PageSize' => 1,
                'PageIndex' => 0,
            ]);

            if ($response->failed()) {
                Log::warning('Bamboo: catalog lookup for face value failed', [
                    'product_id' => $supplierProductId,
                    'status' => $response->status(),
                ]);

                return (float) ($cached ?? 0);
            }

            foreach ($response->json('items', []) as $brand) {
                foreach ($brand['products'] ?? [] as $product) {
                    if ((string) ($product['id'] ?? '') === $supplierProductId) {
                        $faceValue = (float) ($product['minFaceValue'] ?? $product['maxFaceValue'] ?? 0);

                        if ($faceValue > 0) {
                            Cache::put($cacheKey, $faceValue, now()->addHours(24));
                        }

                        return $faceValue;
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Bamboo: exception fetching face value', [
                'product_id' => $supplierProductId,
                'error' => $e->getMessage(),
            ]);
        }

        return (float) ($cached ?? 0);
    }

    /**
     * Make a GET request with Basic Auth.
     *
     * @param  array<string, mixed>  $query
     */
    private function get(string $path, array $query = [], int $timeout = 30): Response
    {
        $request = Http::withBasicAuth(
            $this->credentials['client_id'] ?? '',
            $this->credentials['client_secret'] ?? ''
        )
            ->acceptJson()
            ->timeout($timeout);

        $url = $this->url($path);

        return empty($query) ? $request->get($url) : $request->get($url, $query);
    }

    /**
     * Make a POST request with Basic Auth.
     *
     * @param  array<string, mixed>  $body
     */
    private function post(string $path, array $body = []): Response
    {
        return Http::withBasicAuth(
            $this->credentials['client_id'] ?? '',
            $this->credentials['client_secret'] ?? ''
        )
            ->acceptJson()
            ->timeout(60)
            ->post($this->url($path), $body);
    }

    private function url(string $path): string
    {
        $base = rtrim($this->supplier->base_url ?: 'https://api.bamboocardportal.com', '/');

        return $base.'/'.ltrim($path, '/');
    }
}
