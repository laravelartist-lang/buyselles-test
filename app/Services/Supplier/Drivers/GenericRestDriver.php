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

/**
 * A configurable REST driver for any supplier with a standard REST API.
 *
 * Uses the `settings` JSON on `supplier_apis` to configure:
 * - Authentication (bearer_token, basic, api_key, hmac, login_via)
 * - Response envelope unwrapping (e.g. "result.data")
 * - Auto-pagination with configurable meta paths
 * - Structured code extraction from order responses
 * - Custom headers, webhook signature verification
 *
 * Supported auth types:
 * - bearer_token : Uses `api_key` credential as Bearer token
 * - basic        : Uses `api_key` + `api_secret` for HTTP Basic Auth
 * - api_key      : Sends `api_key` in a custom header
 * - hmac         : HMAC-signed requests with timestamp
 * - login_via    : Auto-login to a creds endpoint, cache token, auto-refresh on 401
 */
class GenericRestDriver implements SupplierDriverInterface
{
    private SupplierApi $supplier;

    /** @var array<string, mixed> */
    private array $credentials = [];

    /** @var array<string, mixed> */
    private array $settings = [];

    /** Cached resolved bearer token for login_via auth. */
    private ?string $cachedToken = null;

    public function configure(SupplierApi $supplier): static
    {
        $this->supplier = $supplier;
        $this->credentials = $supplier->getDecryptedCredentials();
        $this->settings = $supplier->settings ?? [];
        $this->cachedToken = null;

        return $this;
    }

    public function authenticate(): bool
    {
        try {
            $response = $this->makeRequest('GET', $this->getSetting('auth_test_endpoint', '/'));

            return $response->successful();
        } catch (\Throwable) {
            return false;
        }
    }

    public function fetchProducts(array $filters = []): array
    {
        $endpoint = $this->getSetting('products_endpoint', '/products');
        $pageParam = $this->getSetting('pagination_page_param', 'page');
        $perPageParam = $this->getSetting('pagination_per_page_param', 'limit');
        $defaultPerPage = (int) $this->getSetting('pagination_per_page_default', 50);

        $autoPaginate = $this->getSetting('pagination_enabled', false);
        $fetchAll = $filters['fetch_all'] ?? $autoPaginate;

        if (! $fetchAll) {
            unset($filters['fetch_all']);
        }

        $startPage = (int) ($filters[$pageParam] ?? 1);
        $perPage = (int) ($filters[$perPageParam] ?? $defaultPerPage);

        if ($fetchAll) {
            return $this->fetchProductsPaginated($endpoint, $filters, $pageParam, $perPageParam, $startPage, $perPage);
        }

        $response = $this->makeRequest('GET', $endpoint, $filters);
        $data = $this->unwrapResponseData($response->json() ?? []);
        $items = data_get($data, $this->getSetting('products_response_path', 'data'), []);

        return $this->mapProducts($items);
    }

    /**
     * Auto-paginate through all pages of a products endpoint.
     */
    private function fetchProductsPaginated(
        string $endpoint,
        array $filters,
        string $pageParam,
        string $perPageParam,
        int $startPage,
        int $perPage,
    ): array {
        $lastPagePath = $this->getSetting('pagination_last_page_path', 'meta.last_page');
        $dataPath = $this->getSetting('pagination_data_path');
        $totalPath = $this->getSetting('pagination_total_path', 'meta.total');

        $onPage = $filters['on_page'] ?? null;

        $dtos = [];
        $currentPage = $startPage;

        do {
            $params = array_merge($filters, [
                $pageParam => $currentPage,
                $perPageParam => $perPage,
            ]);

            $response = $this->makeRequest('GET', $endpoint, $params);

            if ($response->failed()) {
                throw new \RuntimeException("fetchProducts failed: HTTP {$response->status()} — {$response->body()}");
            }

            $raw = $response->json() ?? [];
            $data = $this->unwrapResponseData($raw);

            $items = $dataPath
                ? data_get($data, $dataPath, [])
                : data_get($data, $this->getSetting('products_response_path', 'data'), []);

            $lastPage = (int) data_get($data, $lastPagePath, $currentPage);
            $total = (int) data_get($data, $totalPath, 0);

            if (is_callable($onPage)) {
                $onPage($currentPage, count($items), $total);
            }

            foreach ($this->mapProducts($items) as $dto) {
                $dtos[] = $dto;
            }

            $currentPage++;

        } while ($currentPage <= $lastPage && count($items) >= $perPage);

        return $dtos;
    }

    /**
     * Map raw product items to SupplierProductDTO objects.
     */
    private function mapProducts(array $items): array
    {
        $idField = $this->getSetting('product_id_field', 'id');
        $nameField = $this->getSetting('product_name_field', 'name');
        $priceField = $this->getSetting('product_price_field', 'price');
        $stockField = $this->getSetting('product_stock_field', 'stock');
        $categoryField = $this->getSetting('product_category_field', 'category');

        return array_map(function ($item) use ($idField, $nameField, $priceField, $stockField, $categoryField): SupplierProductDTO {
            $catValue = data_get($item, $categoryField);

            return new SupplierProductDTO(
                supplierProductId: (string) data_get($item, $idField, ''),
                name: (string) data_get($item, $nameField, ''),
                description: data_get($item, 'description'),
                category: is_string($catValue) ? $catValue : (is_scalar($catValue) ? (string) $catValue : null),
                imageUrl: data_get($item, 'image'),
                price: (float) data_get($item, $priceField, 0),
                currency: data_get($item, 'currency', 'USD'),
                stockAvailable: (int) data_get($item, $stockField, 0),
                rawData: (array) $item,
            );
        }, $items);
    }

    public function fetchStock(string $supplierProductId): StockResult
    {
        $endpoint = $this->getSetting('stock_endpoint', "/products/{$supplierProductId}/stock");
        $endpoint = str_replace('{product_id}', $supplierProductId, $endpoint);

        $response = $this->makeRequest('GET', $endpoint);
        $data = $this->unwrapResponseData($response->json() ?? []);

        $stockPath = $this->getSetting('stock_response_path', 'stock');
        $pricePath = $this->getSetting('stock_price_path', 'price');

        return new StockResult(
            available: (int) data_get($data, $stockPath, 0),
            price: (float) data_get($data, $pricePath, 0),
            currency: (string) data_get($data, 'currency', 'USD'),
            rawData: $data,
        );
    }

    public function placeOrder(string $supplierProductId, int $quantity, ?float $unitPrice = null): SupplierOrderResult
    {
        $endpoint = $this->getSetting('order_endpoint', '/orders');
        $productIdField = $this->getSetting('order_product_id_field', 'product_id');
        $quantityField = $this->getSetting('order_quantity_field', 'quantity');

        $payload = [
            $productIdField => $supplierProductId,
            $quantityField => $quantity,
        ];

        if ($unitPrice !== null) {
            $priceField = $this->getSetting('order_unit_price_field', 'unit_price');
            $payload[$priceField] = $unitPrice;
        }

        $extraFields = $this->getSetting('order_extra_fields', []);
        $payload = array_merge($payload, $extraFields);

        $response = $this->makeRequest('POST', $endpoint, $payload);
        $data = $this->unwrapResponseData($response->json() ?? []);

        $orderIdPath = $this->getSetting('order_id_response_path', 'order_id');
        $statusPath = $this->getSetting('order_status_response_path', 'status');
        $codesPath = $this->getSetting('order_codes_response_path', 'codes');

        $codes = $this->extractStructuredCodes($data, $codesPath);
        $status = (string) data_get($data, $statusPath, 'pending');
        $status = $this->normalizeStatus($status);

        return new SupplierOrderResult(
            supplierOrderId: (string) data_get($data, $orderIdPath),
            status: $status,
            codes: $codes,
            rawResponse: $data,
        );
    }

    public function placeTopUpOrder(
        string $supplierProductId,
        float $quantity,
        string $accountId,
        ?float $unitPrice = null,
    ): SupplierOrderResult {
        $endpoint = $this->getSetting('topup_order_endpoint', $this->getSetting('order_endpoint', '/orders'));
        $productIdField = $this->getSetting('topup_product_id_field', $this->getSetting('order_product_id_field', 'product_id'));
        $quantityField = $this->getSetting('topup_quantity_field', $this->getSetting('order_quantity_field', 'quantity'));
        $accountField = $this->getSetting('topup_account_field', 'account_id');

        $payload = [
            $productIdField => $supplierProductId,
            $quantityField => $quantity,
            $accountField => $accountId,
        ];

        if ($unitPrice !== null) {
            $priceField = $this->getSetting('topup_unit_price_field', $this->getSetting('order_unit_price_field', 'unit_price'));
            $payload[$priceField] = $unitPrice;
        }

        $extraFields = $this->getSetting('topup_extra_fields', $this->getSetting('order_extra_fields', []));
        $payload = array_merge($payload, $extraFields);

        $response = $this->makeRequest('POST', $endpoint, $payload);
        $data = $this->unwrapResponseData($response->json() ?? []);

        $orderIdPath = $this->getSetting('topup_order_id_response_path', $this->getSetting('order_id_response_path', 'order_id'));
        $statusPath = $this->getSetting('topup_status_response_path', $this->getSetting('order_status_response_path', 'status'));
        $successValues = (array) $this->getSetting('topup_success_status_values', ['success', 'completed', 'fulfilled', 'done']);

        $rawStatus = (string) data_get($data, $statusPath, '');
        $status = $this->normalizeStatus($rawStatus);

        if ($response->successful() && ($status === 'fulfilled' || in_array(strtolower($rawStatus), array_map('strtolower', $successValues), true))) {
            $status = 'fulfilled';
        } elseif ($response->failed()) {
            $status = 'failed';
        }

        return new SupplierOrderResult(
            supplierOrderId: (string) data_get($data, $orderIdPath),
            status: $status,
            codes: [],
            rawResponse: $data,
        );
    }

    public function getOrderStatus(string $supplierOrderId): SupplierOrderResult
    {
        $endpoint = $this->getSetting('order_status_endpoint', "/orders/{$supplierOrderId}");
        $endpoint = str_replace('{order_id}', $supplierOrderId, $endpoint);

        $response = $this->makeRequest('GET', $endpoint);
        $data = $this->unwrapResponseData($response->json() ?? []);

        $statusPath = $this->getSetting('order_status_response_path', 'status');
        $codesPath = $this->getSetting('order_codes_response_path', 'codes');

        return new SupplierOrderResult(
            supplierOrderId: $supplierOrderId,
            status: $this->normalizeStatus((string) data_get($data, $statusPath, 'pending')),
            codes: $this->extractStructuredCodes($data, $codesPath),
            rawResponse: $data,
        );
    }

    public function parseWebhook(Request $request): WebhookResult
    {
        $webhookSecret = $this->getSetting('webhook_secret');

        if ($webhookSecret) {
            $signature = $request->header($this->getSetting('webhook_signature_header', 'X-Signature'));
            $payload = $request->getContent();

            $algo = $this->getSetting('webhook_hash_algo', 'sha512');
            $expectedSignature = hash_hmac($algo, $payload, $webhookSecret);

            if (! hash_equals($expectedSignature, $signature ?? '')) {
                return new WebhookResult(type: 'unknown', verified: false, rawPayload: $request->all());
            }
        }

        $data = $request->all();
        $typePath = $this->getSetting('webhook_type_path', 'type');
        $orderIdPath = $this->getSetting('webhook_order_id_path', 'order_id');
        $codesPath = $this->getSetting('webhook_codes_path', 'codes');
        $statusPath = $this->getSetting('webhook_status_path', 'status');

        return new WebhookResult(
            type: (string) data_get($data, $typePath, 'unknown'),
            supplierOrderId: data_get($data, $orderIdPath),
            codes: $this->extractStructuredCodes($data, $codesPath),
            status: $this->normalizeStatus((string) data_get($data, $statusPath, 'unknown')),
            verified: true,
            rawPayload: $data,
        );
    }

    public function healthCheck(): HealthResult
    {
        $endpoint = $this->getSetting('health_endpoint', '/');
        $start = microtime(true);

        try {
            $response = $this->makeRequest('GET', $endpoint);
            $latency = (int) ((microtime(true) - $start) * 1000);

            if ($response->successful()) {
                $status = $latency > 5000 ? 'degraded' : 'healthy';

                return new HealthResult(
                    status: $status,
                    latencyMs: $latency,
                    message: "HTTP {$response->status()} in {$latency}ms",
                );
            }

            return new HealthResult(
                status: 'down',
                latencyMs: $latency,
                message: "HTTP {$response->status()}",
            );
        } catch (\Throwable $e) {
            $latency = (int) ((microtime(true) - $start) * 1000);

            return new HealthResult(
                status: 'down',
                latencyMs: $latency,
                message: $e->getMessage(),
            );
        }
    }

    public function getBalance(): BalanceResult
    {
        $endpoint = $this->getSetting('balance_endpoint');

        if (! $endpoint) {
            return BalanceResult::unsupported();
        }

        try {
            $response = $this->makeRequest('GET', $endpoint);

            if ($response->failed()) {
                return new BalanceResult(
                    supported: true,
                    message: "HTTP {$response->status()}",
                );
            }

            $data = $this->unwrapResponseData($response->json() ?? []);
            $balancePath = $this->getSetting('balance_response_path', 'balance');
            $currencyPath = $this->getSetting('balance_currency_path', 'currency');

            return new BalanceResult(
                supported: true,
                balance: (float) data_get($data, $balancePath, 0),
                currency: (string) data_get($data, $currencyPath, 'USD'),
            );
        } catch (\Throwable $e) {
            return new BalanceResult(supported: true, message: $e->getMessage());
        }
    }

    public function getRequiredCredentialFields(): array
    {
        return [
            'api_key' => ['label' => 'API Key', 'type' => 'text', 'required' => false],
            'api_secret' => ['label' => 'API Secret', 'type' => 'password', 'required' => false],
            'email' => ['label' => 'Email (for login_via auth)', 'type' => 'email', 'required' => false],
            'password' => ['label' => 'Password (for login_via auth)', 'type' => 'password', 'required' => false],
        ];
    }

    public function getConfigSchema(): array
    {
        return [
            'auth_test_endpoint' => ['label' => 'Auth Test Endpoint', 'type' => 'text', 'default' => '/'],
            'response_unwrap_path' => ['label' => 'Response Unwrap Path (dot notation, e.g. "result.data")', 'type' => 'text', 'default' => ''],
            'login_endpoint' => ['label' => 'Login Endpoint (for login_via auth)', 'type' => 'text', 'default' => '/login'],
            'login_method' => ['label' => 'Login HTTP Method', 'type' => 'text', 'default' => 'POST'],
            'login_token_response_path' => ['label' => 'Login Token Response Path (dot notation, e.g. "accessToken" or "data.accessToken")', 'type' => 'text', 'default' => 'accessToken'],
            'token_cache_minutes' => ['label' => 'Token Cache Duration (minutes, for login_via auth)', 'type' => 'number', 'default' => 60],
            'pagination_enabled' => ['label' => 'Enable Auto-Pagination', 'type' => 'text', 'default' => ''],
            'pagination_page_param' => ['label' => 'Pagination Page Param', 'type' => 'text', 'default' => 'page'],
            'pagination_per_page_param' => ['label' => 'Pagination Per-Page Param', 'type' => 'text', 'default' => 'limit'],
            'pagination_per_page_default' => ['label' => 'Pagination Per-Page Default', 'type' => 'number', 'default' => 50],
            'pagination_data_path' => ['label' => 'Pagination Data Path (dot notation, e.g. "data")', 'type' => 'text', 'default' => ''],
            'pagination_last_page_path' => ['label' => 'Pagination Last Page Path (dot notation, e.g. "meta.last_page")', 'type' => 'text', 'default' => 'meta.last_page'],
            'pagination_total_path' => ['label' => 'Pagination Total Path (dot notation, e.g. "meta.total")', 'type' => 'text', 'default' => 'meta.total'],
            'products_endpoint' => ['label' => 'Products Endpoint', 'type' => 'text', 'default' => '/products'],
            'products_response_path' => ['label' => 'Products Response Path', 'type' => 'text', 'default' => 'data'],
            'product_id_field' => ['label' => 'Product ID Field', 'type' => 'text', 'default' => 'id'],
            'product_name_field' => ['label' => 'Product Name Field', 'type' => 'text', 'default' => 'name'],
            'product_price_field' => ['label' => 'Product Price Field', 'type' => 'text', 'default' => 'price'],
            'product_stock_field' => ['label' => 'Product Stock Field', 'type' => 'text', 'default' => 'stock'],
            'product_category_field' => ['label' => 'Product Category Field (dot notation, e.g. "category.title")', 'type' => 'text', 'default' => 'category'],
            'stock_endpoint' => ['label' => 'Stock Endpoint', 'type' => 'text', 'default' => '/products/{product_id}/stock'],
            'order_endpoint' => ['label' => 'Order Endpoint', 'type' => 'text', 'default' => '/orders'],
            'order_product_id_field' => ['label' => 'Order Product ID Field', 'type' => 'text', 'default' => 'product_id'],
            'order_quantity_field' => ['label' => 'Order Quantity Field', 'type' => 'text', 'default' => 'quantity'],
            'order_id_response_path' => ['label' => 'Order ID Response Path', 'type' => 'text', 'default' => 'order_id'],
            'order_status_response_path' => ['label' => 'Order Status Response Path', 'type' => 'text', 'default' => 'status'],
            'order_codes_response_path' => ['label' => 'Order Codes Response Path', 'type' => 'text', 'default' => 'codes'],
            'order_codes_value_field' => ['label' => 'Order Codes Value Field (dot path within each code object, e.g. "card")', 'type' => 'text', 'default' => ''],
            'order_codes_serial_field' => ['label' => 'Order Codes Serial Field (optional, dot path to serial_number within each code object)', 'type' => 'text', 'default' => ''],
            'order_unit_price_field' => ['label' => 'Order Unit Price Field', 'type' => 'text', 'default' => 'unit_price'],
            'topup_order_endpoint' => ['label' => 'Top-up Order Endpoint', 'type' => 'text', 'default' => '/orders'],
            'topup_product_id_field' => ['label' => 'Top-up Product ID Field', 'type' => 'text', 'default' => 'product_id'],
            'topup_quantity_field' => ['label' => 'Top-up Quantity Field', 'type' => 'text', 'default' => 'quantity'],
            'topup_account_field' => ['label' => 'Top-up Account ID Field', 'type' => 'text', 'default' => 'account_id'],
            'topup_unit_price_field' => ['label' => 'Top-up Unit Price Field', 'type' => 'text', 'default' => 'unit_price'],
            'topup_status_response_path' => ['label' => 'Top-up Status Response Path', 'type' => 'text', 'default' => 'status'],
            'topup_order_id_response_path' => ['label' => 'Top-up Order ID Response Path', 'type' => 'text', 'default' => 'order_id'],
            'webhook_secret' => ['label' => 'Webhook Secret', 'type' => 'password', 'default' => ''],
            'webhook_signature_header' => ['label' => 'Webhook Signature Header', 'type' => 'text', 'default' => 'X-Signature'],
            'webhook_hash_algo' => ['label' => 'Webhook Hash Algorithm', 'type' => 'text', 'default' => 'sha512'],
            'health_endpoint' => ['label' => 'Health Check Endpoint', 'type' => 'text', 'default' => '/'],
            'custom_headers' => ['label' => 'Custom Headers (JSON)', 'type' => 'text', 'default' => ''],
            'status_map' => ['label' => 'Status Map (JSON: source_status => normalized_status)', 'type' => 'text', 'default' => ''],
        ];
    }

    // ─── HTTP Client ─────────────────────────────────────────────────────

    /**
     * Make an HTTP request to the supplier API with proper auth headers.
     *
     * @param  array<string, mixed>  $data
     */
    private function makeRequest(string $method, string $endpoint, array $data = [], bool $retried = false): Response
    {
        $url = rtrim($this->supplier->base_url, '/').'/'.ltrim($endpoint, '/');

        $request = Http::timeout(30)
            ->acceptJson();

        $request = $this->applyAuth($request);

        $customHeaders = $this->getSetting('custom_headers', []);

        if (is_string($customHeaders)) {
            $decoded = json_decode($customHeaders, true);
            $customHeaders = is_array($decoded) ? $decoded : [];
        }

        if (! empty($customHeaders)) {
            $request = $request->withHeaders($customHeaders);
        }

        $response = match (strtoupper($method)) {
            'POST' => $request->post($url, $data),
            'PUT' => $request->put($url, $data),
            'DELETE' => $request->delete($url, $data),
            default => $request->get($url, $data),
        };

        // Auto-retry once on 401 for login_via (expired token → re-login)
        if ($response->status() === 401 && $this->supplier->auth_type === 'login_via' && ! $retried) {
            $this->invalidateLoginToken();

            return $this->makeRequest($method, $endpoint, $data, retried: true);
        }

        if ($response->failed() && $response->status() >= 500) {
            throw new \RuntimeException("Supplier API error: HTTP {$response->status()}");
        }

        return $response;
    }

    /**
     * Apply authentication headers based on the supplier's auth_type.
     */
    private function applyAuth(\Illuminate\Http\Client\PendingRequest $request): \Illuminate\Http\Client\PendingRequest
    {
        $apiKey = $this->credentials['api_key'] ?? $this->credentials['client_id'] ?? '';
        $apiSecret = $this->credentials['api_secret'] ?? $this->credentials['client_secret'] ?? '';

        return match ($this->supplier->auth_type) {
            'bearer_token' => $request->withToken($apiKey),
            'login_via' => $request->withToken($this->resolveLoginToken()),
            'basic' => $request->withBasicAuth($apiKey, $apiSecret),
            'api_key' => $request->withHeaders([
                $this->getSetting('api_key_header', 'X-API-KEY') => $apiKey,
            ]),
            'hmac' => $request->withHeaders([
                'X-API-KEY' => $apiKey,
                'X-Signature' => hash_hmac('sha256', $apiKey.time(), $apiSecret),
                'X-Timestamp' => (string) time(),
            ]),
            default => $request,
        };
    }

    // ─── Login-then-use-token Flow ────────────────────────────────────────

    /**
     * Resolve the bearer token for login_via auth.
     *
     * Checks cached token first, then performs a fresh login if needed.
     */
    private function resolveLoginToken(): string
    {
        if ($this->cachedToken !== null) {
            return $this->cachedToken;
        }

        $cacheKey = $this->loginTokenCacheKey();
        $cached = Cache::get($cacheKey);

        if (is_string($cached) && $cached !== '') {
            $this->cachedToken = $cached;

            return $cached;
        }

        return $this->performLogin();
    }

    /**
     * Invalidate the cached login token (e.g. on 401).
     */
    private function invalidateLoginToken(): void
    {
        Cache::forget($this->loginTokenCacheKey());
        $this->cachedToken = null;
    }

    /**
     * POST credentials to the login endpoint and extract a token from the response.
     */
    private function performLogin(): string
    {
        $endpoint = $this->getSetting('login_endpoint', '/login');
        $method = strtoupper($this->getSetting('login_method', 'POST'));
        $tokenPath = $this->getSetting('login_token_response_path', 'accessToken');
        $payloadFields = $this->getSetting('login_payload_fields', []);

        if (is_string($payloadFields)) {
            $decoded = json_decode($payloadFields, true);
            $payloadFields = is_array($decoded) ? $decoded : [];
        }

        $payload = [];

        // If explicit payload field mapping is configured, use it
        if (! empty($payloadFields)) {
            foreach ($payloadFields as $credentialKey => $requestKey) {
                if (isset($this->credentials[$credentialKey])) {
                    $payload[$requestKey] = $this->credentials[$credentialKey];
                }
            }
        } else {
            // Default: send all credentials except api_key/api_secret/client_id/client_secret
            foreach ($this->credentials as $key => $value) {
                if (! in_array($key, ['api_key', 'api_secret', 'client_id', 'client_secret'], true) && $value !== '' && $value !== null) {
                    $payload[$key] = $value;
                }
            }
        }

        $url = rtrim($this->supplier->base_url, '/').'/'.ltrim($endpoint, '/');

        $http = Http::acceptJson()->timeout(15);

        $response = match ($method) {
            'GET' => $http->get($url, $payload),
            'PUT' => $http->put($url, $payload),
            default => $http->post($url, $payload),
        };

        if ($response->failed()) {
            throw new \RuntimeException(
                "Login to {$this->supplier->name} failed: HTTP {$response->status()} — {$response->body()}"
            );
        }

        $data = $response->json() ?? [];

        // First unwrap envelope if configured, then extract the token
        $data = $this->unwrapResponseData($data);
        $token = data_get($data, $tokenPath);

        if (empty($token) || ! is_string($token)) {
            throw new \RuntimeException(
                "Login response for {$this->supplier->name} did not contain a token at path: {$tokenPath}"
            );
        }

        $ttl = (int) $this->getSetting('token_cache_minutes', 60);

        Cache::put($this->loginTokenCacheKey(), $token, now()->addMinutes($ttl));
        $this->cachedToken = $token;

        return $token;
    }

    private function loginTokenCacheKey(): string
    {
        return "generic_rest_login_token_{$this->supplier->id}";
    }

    // ─── Helpers ──────────────────────────────────────────────────────────

    /**
     * Unwrap a JSON response body according to response_unwrap_path setting.
     *
     * Example: "result.data" unwraps { result: { data: {...} } } → {...}
     */
    private function unwrapResponseData(array $data): array
    {
        $unwrapPath = $this->getSetting('response_unwrap_path');

        if (empty($unwrapPath)) {
            return $data;
        }

        $unwrapped = data_get($data, $unwrapPath);

        return is_array($unwrapped) ? $unwrapped : $data;
    }

    /**
     * Extract structured codes from response data.
     *
     * When order_codes_value_field is set, each code item is treated as an
     * object/array and the actual code value is extracted from the configured
     * field path. Otherwise, items are used directly as code strings.
     *
     * @return array<int, array{code: string, serial_number?: string}>
     */
    private function extractStructuredCodes(array $data, string $codesPath): array
    {
        $raw = data_get($data, $codesPath, []);

        if (! is_array($raw)) {
            return [];
        }

        $valueField = $this->getSetting('order_codes_value_field');
        $serialField = $this->getSetting('order_codes_serial_field');
        $hasValueField = ! empty($valueField);

        $codes = [];

        foreach ($raw as $item) {
            if ($hasValueField) {
                $codeValue = data_get($item, $valueField);
                $serial = $serialField ? data_get($item, $serialField) : null;

                if ($codeValue !== null && (string) $codeValue !== '') {
                    $entry = ['code' => (string) $codeValue];

                    if ($serial !== null && (string) $serial !== '' && (string) $serial !== (string) $codeValue) {
                        $entry['serial_number'] = (string) $serial;
                    }

                    $codes[] = $entry;
                }

                continue;
            }

            // Scalar codes: treat each item as a plain code string
            if (is_scalar($item) && (string) $item !== '') {
                $codes[] = ['code' => (string) $item];
            } elseif (is_array($item)) {
                // Fallback for arrays when no value_field set: use 'code' key if present
                if (isset($item['code'])) {
                    $entry = ['code' => (string) $item['code']];
                    if (! empty($item['serial_number'])) {
                        $entry['serial_number'] = (string) $item['serial_number'];
                    }
                    $codes[] = $entry;
                } elseif (isset($item['serial'])) {
                    $codes[] = ['code' => (string) ($item['card'] ?? $item['serial'])];
                }
            }
        }

        return $codes;
    }

    /**
     * Get a setting value with a fallback default.
     */
    private function getSetting(string $key, mixed $default = null): mixed
    {
        return $this->settings[$key] ?? $default;
    }

    /**
     * Normalize supplier status strings to our standard enum values.
     */
    private function normalizeStatus(string $status): string
    {
        $statusMap = $this->getSetting('status_map', []);

        if (is_string($statusMap)) {
            $decoded = json_decode($statusMap, true);
            $statusMap = is_array($decoded) ? $decoded : [];
        }

        if (isset($statusMap[$status])) {
            return $statusMap[$status];
        }

        return match (strtolower($status)) {
            'complete', 'completed', 'delivered', 'done', 'fulfilled' => 'fulfilled',
            'processing', 'in_progress', 'in-progress' => 'processing',
            'pending', 'waiting', 'queued' => 'pending',
            'failed', 'error', 'cancelled', 'canceled' => 'failed',
            'partial', 'partially_fulfilled' => 'partial',
            'refunded', 'reversed' => 'refunded',
            default => $status,
        };
    }
}
