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
 * Golf API supplier driver (Sanctum Bearer token auth).
 *
 * Auth: Laravel Sanctum Bearer token. The driver supports two modes:
 *   - Provide an `api_token` directly (long-lived token).
 *   - Provide `email` + `password` and the driver auto-logins, caching
 *     the token for 90 minutes (refreshed on 401).
 *
 * Base URL: Set in supplier (e.g. https://api.your-domain.com/api).
 *           The /api prefix is already included in the base URL.
 *
 * Rate limits: 60 req/min (per user), configurable on the API server.
 *
 * Docs: See golf-api/Golf API.postman_collection.json
 */
class GolfApiDriver implements SupplierDriverInterface
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
            $response = $this->get('/balance');

            return $this->isSuccessResponse($response);
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Fetch the Golf API product catalog.
     *
     * Products are paginated using standard Laravel pagination (data, links, meta).
     * The driver auto-paginates by default unless an explicit page filter is given.
     *
     * @param  array<string, mixed>  $filters  Accepts: category_id, term (search),
     *                                         sort_by, sort_direction, page, limit,
     *                                         fetch_all (bool, default true),
     *                                         on_page (callable progress callback)
     * @return SupplierProductDTO[]
     */
    public function fetchProducts(array $filters = []): array
    {
        $fetchAll = $filters['fetch_all'] ?? ! isset($filters['page']);
        $limit = min((int) ($filters['limit'] ?? 50), 100);
        $startPage = (int) ($filters['page'] ?? 1);
        $onPage = $filters['on_page'] ?? null;

        $baseParams = ['limit' => $limit];

        if (! empty($filters['sort_by'])) {
            $baseParams['sort_by'] = $filters['sort_by'];
        }

        if (! empty($filters['sort_direction'])) {
            $baseParams['sort_direction'] = $filters['sort_direction'];
        }

        if (! empty($filters['term'])) {
            $baseParams['term'] = $filters['term'];
        }

        if (! empty($filters['category_id'])) {
            $baseParams['filter[category_id]'] = $filters['category_id'];
        }

        $dtos = [];
        $currentPage = $startPage;

        do {
            $params = array_merge($baseParams, ['page' => $currentPage]);

            $response = $this->get('/products', $params);

            if ($response->failed()) {
                throw new \RuntimeException("GolfApi fetchProducts failed: HTTP {$response->status()} — {$response->body()}");
            }

            $result = $this->unwrapResponse($response);
            $items = $result['data'] ?? [];
            $meta = $result['meta'] ?? [];
            $total = (int) ($meta['total'] ?? 0);
            $lastPage = (int) ($meta['last_page'] ?? $currentPage);

            if (is_callable($onPage)) {
                $onPage($currentPage, count($items), $total);
            }

            foreach ($items as $product) {
                $productId = (string) ($product['id'] ?? '');

                if ($productId === '') {
                    continue;
                }

                $category = $product['category'] ?? null;
                $categoryTitle = $category['title'] ?? null;

                $dtos[] = new SupplierProductDTO(
                    supplierProductId: $productId,
                    name: (string) ($product['title'] ?? ''),
                    description: $product['description'] ?? null,
                    category: $categoryTitle,
                    imageUrl: $product['main_image'] ?? null,
                    price: (float) ($product['price'] ?? 0),
                    currency: 'USD',
                    stockAvailable: isset($product['stock']) ? (int) $product['stock'] : 999,
                    region: null,
                    rawData: $product,
                );
            }

            $currentPage++;

        } while ($fetchAll && $currentPage <= $lastPage && count($items) >= $limit);

        return $dtos;
    }

    /**
     * Check stock availability for a specific Golf API product.
     *
     * Queries the products list filtered by the given product ID.
     */
    public function fetchStock(string $supplierProductId): StockResult
    {
        $response = $this->get('/products', [
            'filter[id]' => (int) $supplierProductId,
            'limit' => 1,
        ]);

        if ($response->failed()) {
            throw new \RuntimeException("GolfApi fetchStock failed: HTTP {$response->status()}");
        }

        $result = $this->unwrapResponse($response);
        $items = $result['data'] ?? [];

        foreach ($items as $product) {
            if ((string) ($product['id'] ?? '') === $supplierProductId) {
                $available = isset($product['stock']) ? (int) $product['stock'] : 999;

                return new StockResult(
                    available: $available,
                    price: (float) ($product['price'] ?? 0),
                    currency: 'USD',
                    rawData: $product,
                );
            }
        }

        // Fallback: try the single-product endpoint
        try {
            $singleResponse = $this->get("/products/{$supplierProductId}");

            if ($singleResponse->successful()) {
                $result = $this->unwrapResponse($singleResponse);
                $product = $result['data'] ?? [];

                $available = isset($product['stock']) ? (int) $product['stock'] : 999;

                return new StockResult(
                    available: $available,
                    price: (float) ($product['price'] ?? 0),
                    currency: 'USD',
                    rawData: $product,
                );
            }
        } catch (\Throwable) {
            // Fall through to the default
        }

        return new StockResult(available: 0, price: 0.0, currency: 'USD', rawData: []);
    }

    /**
     * Place an order with the Golf API.
     *
     * Endpoint: POST /order
     * Body: { product_id, quantity, custom_fields }
     *
     * For "code" type products, the response includes cards inline.
     * For "charge" type products, the response indicates direct top-up status.
     */
    public function placeOrder(string $supplierProductId, int $quantity, ?float $unitPrice = null): SupplierOrderResult
    {
        $body = [
            'product_id' => (int) $supplierProductId,
            'quantity' => $quantity,
        ];

        $response = $this->post('/order', $body);

        if ($response->failed()) {
            throw new \RuntimeException("GolfApi placeOrder failed: HTTP {$response->status()} — {$response->body()}");
        }

        return $this->parseOrderResponse($response);
    }

    /**
     * Place a direct top-up order (e.g. Jawaker charge, or other "charge" products).
     *
     * Endpoint: POST /order (same as placeOrder — the Golf API handles
     * both "code" and "charge" product types via the same endpoint).
     */
    public function placeTopUpOrder(
        string $supplierProductId,
        float $quantity,
        string $accountId,
        ?float $unitPrice = null,
    ): SupplierOrderResult {
        $body = [
            'product_id' => (int) $supplierProductId,
            'quantity' => (int) ceil($quantity),
        ];

        $response = $this->post('/order', $body);

        if ($response->failed()) {
            throw new \RuntimeException("GolfApi placeTopUpOrder failed: HTTP {$response->status()} — {$response->body()}");
        }

        return $this->parseOrderResponse($response);
    }

    /**
     * Get the current status of an order.
     *
     * Endpoint: GET /orders/{id}
     */
    public function getOrderStatus(string $supplierOrderId): SupplierOrderResult
    {
        $response = $this->get("/orders/{$supplierOrderId}");

        if ($response->failed()) {
            throw new \RuntimeException("GolfApi getOrderStatus failed: HTTP {$response->status()}");
        }

        $result = $this->unwrapResponse($response);
        $order = $result['data'] ?? [];

        $rawStatus = strtolower((string) ($order['status'] ?? 'pending'));
        $codes = $this->extractCodesFromOrder($order);

        $normalizedStatus = match ($rawStatus) {
            'completed' => count($codes) > 0 ? 'fulfilled' : 'fulfilled',
            'wait' => 'processing',
            'canceled' => 'failed',
            default => count($codes) > 0 ? 'fulfilled' : 'processing',
        };

        return new SupplierOrderResult(
            supplierOrderId: $supplierOrderId,
            status: $normalizedStatus,
            codes: $codes,
            rawResponse: $order,
        );
    }

    /**
     * The Golf API does not currently support webhook callbacks.
     */
    public function parseWebhook(Request $request): WebhookResult
    {
        return new WebhookResult(
            type: 'unknown',
            verified: false,
            rawPayload: $request->all(),
        );
    }

    /**
     * Health check — calls the balance endpoint to measure latency.
     */
    public function healthCheck(): HealthResult
    {
        $start = microtime(true);

        try {
            $response = $this->get('/balance');
            $latency = (int) ((microtime(true) - $start) * 1000);

            if ($this->isSuccessResponse($response)) {
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
     * Retrieve account balance from the Golf API.
     *
     * Endpoint: GET /balance
     * Response: { result: { data: { balance: 14.72 } } }
     */
    public function getBalance(): BalanceResult
    {
        try {
            $response = $this->get('/balance');

            if ($this->isSuccessResponse($response)) {
                $result = $this->unwrapResponse($response);
                $data = $result['data'] ?? $result;

                $balance = (float) ($data['balance'] ?? 0);

                return new BalanceResult(
                    supported: true,
                    balance: $balance,
                    currency: 'USD',
                );
            }

            return BalanceResult::unsupported();
        } catch (\Throwable) {
            return BalanceResult::unsupported();
        }
    }

    public function getRequiredCredentialFields(): array
    {
        return [
            'api_token' => [
                'label' => 'API Token (Sanctum Bearer token — preferred)',
                'type' => 'password',
                'required' => false,
            ],
            'email' => [
                'label' => 'Email (for auto-login — only needed if no api_token provided)',
                'type' => 'email',
                'required' => false,
            ],
            'password' => [
                'label' => 'Password (for auto-login — only needed if no api_token provided)',
                'type' => 'password',
                'required' => false,
            ],
        ];
    }

    public function getConfigSchema(): array
    {
        return [
            'token_cache_minutes' => [
                'label' => 'Token cache duration in minutes (used for auto-login tokens)',
                'type' => 'number',
                'default' => 90,
            ],
        ];
    }

    // ─── Internal helpers ─────────────────────────────────────────────────────

    /**
     * Resolve the Sanctum Bearer token to use for API requests.
     *
     * Priority: credentials.api_token → cached auto-login token → fresh login.
     */
    private function resolveToken(): string
    {
        $apiToken = trim((string) ($this->credentials['api_token'] ?? ''));

        if ($apiToken !== '') {
            return $apiToken;
        }

        $cacheKey = "golf_api_token_{$this->supplier->id}";
        $cached = Cache::get($cacheKey);

        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $token = $this->performLogin();

        $ttl = (int) ($this->settings['token_cache_minutes'] ?? 90);

        Cache::put($cacheKey, $token, now()->addMinutes($ttl));

        return $token;
    }

    /**
     * Invalidate the cached token (called on 401 so the next request re-authenticates).
     */
    private function invalidateToken(): void
    {
        $cacheKey = "golf_api_token_{$this->supplier->id}";

        Cache::forget($cacheKey);
    }

    /**
     * Log in with email/password to obtain a fresh Sanctum token.
     */
    private function performLogin(): string
    {
        $email = trim((string) ($this->credentials['email'] ?? ''));
        $password = trim((string) ($this->credentials['password'] ?? ''));

        if ($email === '' || $password === '') {
            throw new \RuntimeException(
                'GolfApi: no api_token provided and no email/password set. '.
                'Provide either a long-lived api_token or email + password credentials.'
            );
        }

        $response = Http::acceptJson()
            ->timeout(15)
            ->post($this->url('/login'), [
                'email' => $email,
                'password' => $password,
            ]);

        if ($response->failed()) {
            throw new \RuntimeException(
                "GolfApi login failed: HTTP {$response->status()} — {$response->body()}"
            );
        }

        $body = $response->json();

        $status = (string) ($body['status'] ?? '');

        if ($status !== 'success') {
            $message = (string) ($body['message'] ?? 'Unknown error');

            throw new \RuntimeException("GolfApi login failed: {$message}");
        }

        $result = $body['result'] ?? [];
        $user = $result['data'] ?? $result;

        $token = (string) ($user['accessToken'] ?? '');

        if ($token === '') {
            throw new \RuntimeException('GolfApi login response did not contain an accessToken.');
        }

        return $token;
    }

    /**
     * Parse a Golf API order response into a SupplierOrderResult.
     *
     * Response shape for code products:
     *   { result: { data: { id, ordernumber, status, order: {...}, cards: [{serial, card}], card_data: {serial, card} } } }
     *
     * Response shape for charge products (top-up):
     *   { result: { data: { id, ordernumber, status, order: {...} } } }
     */
    private function parseOrderResponse(Response $response): SupplierOrderResult
    {
        $result = $this->unwrapResponse($response);
        $order = $result['data'] ?? [];

        $orderId = (string) ($order['id'] ?? '');
        $rawStatus = strtolower((string) ($order['status'] ?? 'wait'));
        $codes = $this->extractCodesFromOrder($order);

        $normalizedStatus = match ($rawStatus) {
            'completed' => count($codes) > 0 ? 'fulfilled' : 'fulfilled',
            'wait' => 'processing',
            'canceled' => 'failed',
            default => 'processing',
        };

        return new SupplierOrderResult(
            supplierOrderId: $orderId,
            status: $normalizedStatus,
            codes: $codes,
            rawResponse: $order,
        );
    }

    /**
     * Extract codes from an order response.
     *
     * Code products return cards in the order response:
     *   - cards: [{serial: "...", card: "..."}]
     *   - card_data: {serial: "...", card: "..."}
     *
     * Charge products (top-ups) have no cards — empty array returned.
     */
    private function extractCodesFromOrder(array $order): array
    {
        $codes = [];

        // Primary: cards array (multi-quantity code products)
        $cards = $order['cards'] ?? [];
        foreach ($cards as $card) {
            $serial = trim((string) ($card['serial'] ?? ''));
            $cardCode = trim((string) ($card['card'] ?? ''));

            if ($cardCode !== '') {
                $entry = ['code' => $cardCode];
                if ($serial !== '' && $serial !== $cardCode) {
                    $entry['serial_number'] = $serial;
                }
                $codes[] = $entry;
            } elseif ($serial !== '') {
                $codes[] = ['code' => $serial];
            }
        }

        // Fallback: card_data (single code product)
        if (empty($codes)) {
            $cardData = $order['card_data'] ?? [];
            $serial = trim((string) ($cardData['serial'] ?? ''));
            $card = trim((string) ($cardData['card'] ?? ''));

            if ($card !== '') {
                $entry = ['code' => $card];
                if ($serial !== '' && $serial !== $card) {
                    $entry['serial_number'] = $serial;
                }
                $codes[] = $entry;
            } elseif ($serial !== '') {
                $codes[] = ['code' => $serial];
            }
        }

        return $codes;
    }

    // ─── HTTP helpers ─────────────────────────────────────────────────────────

    /**
     * Make an authenticated GET request.
     *
     * @param  array<string, mixed>  $query
     */
    private function get(string $path, array $query = [], bool $retried = false): Response
    {
        $token = $this->resolveToken();

        $response = Http::withToken($token)
            ->acceptJson()
            ->timeout(30)
            ->get($this->url($path), $query);

        // Auto-retry once on 401 (expired token)
        if ($response->status() === 401 && ! $retried) {
            $this->invalidateToken();

            return $this->get($path, $query, retried: true);
        }

        return $response;
    }

    /**
     * Make an authenticated POST request.
     *
     * @param  array<string, mixed>  $body
     */
    private function post(string $path, array $body = [], bool $retried = false): Response
    {
        $token = $this->resolveToken();

        $response = Http::withToken($token)
            ->acceptJson()
            ->timeout(60)
            ->post($this->url($path), $body);

        // Auto-retry once on 401 (expired token)
        if ($response->status() === 401 && ! $retried) {
            $this->invalidateToken();

            return $this->post($path, $body, retried: true);
        }

        return $response;
    }

    /**
     * Build the full URL for an API endpoint path.
     *
     * The base_url typically already includes the /api prefix
     * (e.g. https://api.your-domain.com/api). Paths should NOT
     * include the /api prefix.
     */
    private function url(string $path): string
    {
        $base = rtrim($this->supplier->base_url, '/');

        return $base.'/'.ltrim($path, '/');
    }

    /**
     * Check whether the response indicates API success.
     */
    private function isSuccessResponse(Response $response): bool
    {
        if (! $response->successful()) {
            return false;
        }

        $body = $response->json();

        return ($body['status'] ?? '') === 'success';
    }

    /**
     * Unwrap the "result" key from a Golf API success response.
     *
     * @return array<string, mixed>
     */
    private function unwrapResponse(Response $response): array
    {
        $body = $response->json() ?? [];

        return $body['result'] ?? $body;
    }
}
