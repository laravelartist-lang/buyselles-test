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
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

/**
 * A configurable REST driver for any supplier with a standard REST API.
 *
 * Uses the `settings` JSON on `supplier_apis` to configure:
 * - response path mappings (where in JSON response to find products/codes)
 * - custom headers
 * - auth header placement
 * - webhook secret for HMAC verification
 */
class GenericRestDriver implements SupplierDriverInterface
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
        $response = $this->makeRequest('GET', $endpoint, $filters);

        $productsPath = $this->getSetting('products_response_path', 'data');
        $items = data_get($response->json(), $productsPath, []);

        $idField = $this->getSetting('product_id_field', 'id');
        $nameField = $this->getSetting('product_name_field', 'name');
        $priceField = $this->getSetting('product_price_field', 'price');
        $stockField = $this->getSetting('product_stock_field', 'stock');

        return array_map(function ($item) use ($idField, $nameField, $priceField, $stockField): SupplierProductDTO {
            return new SupplierProductDTO(
                supplierProductId: (string) data_get($item, $idField, ''),
                name: (string) data_get($item, $nameField, ''),
                description: data_get($item, 'description'),
                category: data_get($item, 'category'),
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
        $data = $response->json();

        $stockPath = $this->getSetting('stock_response_path', 'stock');
        $pricePath = $this->getSetting('stock_price_path', 'price');

        return new StockResult(
            available: (int) data_get($data, $stockPath, 0),
            price: (float) data_get($data, $pricePath, 0),
            currency: (string) data_get($data, 'currency', 'USD'),
            rawData: $data ?? [],
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
        $data = $response->json();

        $orderIdPath = $this->getSetting('order_id_response_path', 'order_id');
        $statusPath = $this->getSetting('order_status_response_path', 'status');
        $codesPath = $this->getSetting('order_codes_response_path', 'codes');

        $codes = (array) data_get($data, $codesPath, []);
        $status = (string) data_get($data, $statusPath, 'pending');

        // Normalize status to our enum values
        $status = $this->normalizeStatus($status);

        return new SupplierOrderResult(
            supplierOrderId: (string) data_get($data, $orderIdPath),
            status: $status,
            codes: $codes,
            rawResponse: $data ?? [],
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
        $data = $response->json() ?? [];

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
        $data = $response->json();

        $statusPath = $this->getSetting('order_status_response_path', 'status');
        $codesPath = $this->getSetting('order_codes_response_path', 'codes');

        return new SupplierOrderResult(
            supplierOrderId: $supplierOrderId,
            status: $this->normalizeStatus((string) data_get($data, $statusPath, 'pending')),
            codes: (array) data_get($data, $codesPath, []),
            rawResponse: $data ?? [],
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
            codes: (array) data_get($data, $codesPath, []),
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

            $balancePath = $this->getSetting('balance_response_path', 'balance');
            $currencyPath = $this->getSetting('balance_currency_path', 'currency');

            return new BalanceResult(
                supported: true,
                balance: (float) data_get($response->json(), $balancePath, 0),
                currency: (string) data_get($response->json(), $currencyPath, 'USD'),
            );
        } catch (\Throwable $e) {
            return new BalanceResult(supported: true, message: $e->getMessage());
        }
    }

    public function getRequiredCredentialFields(): array
    {
        return [
            'api_key' => ['label' => 'API Key', 'type' => 'text', 'required' => true],
            'api_secret' => ['label' => 'API Secret', 'type' => 'password', 'required' => false],
        ];
    }

    public function getConfigSchema(): array
    {
        return [
            'auth_test_endpoint' => ['label' => 'Auth Test Endpoint', 'type' => 'text', 'default' => '/'],
            'products_endpoint' => ['label' => 'Products Endpoint', 'type' => 'text', 'default' => '/products'],
            'products_response_path' => ['label' => 'Products Response Path', 'type' => 'text', 'default' => 'data'],
            'product_id_field' => ['label' => 'Product ID Field', 'type' => 'text', 'default' => 'id'],
            'product_name_field' => ['label' => 'Product Name Field', 'type' => 'text', 'default' => 'name'],
            'product_price_field' => ['label' => 'Product Price Field', 'type' => 'text', 'default' => 'price'],
            'product_stock_field' => ['label' => 'Product Stock Field', 'type' => 'text', 'default' => 'stock'],
            'stock_endpoint' => ['label' => 'Stock Endpoint', 'type' => 'text', 'default' => '/products/{product_id}/stock'],
            'order_endpoint' => ['label' => 'Order Endpoint', 'type' => 'text', 'default' => '/orders'],
            'order_product_id_field' => ['label' => 'Order Product ID Field', 'type' => 'text', 'default' => 'product_id'],
            'order_quantity_field' => ['label' => 'Order Quantity Field', 'type' => 'text', 'default' => 'quantity'],
            'order_id_response_path' => ['label' => 'Order ID Response Path', 'type' => 'text', 'default' => 'order_id'],
            'order_status_response_path' => ['label' => 'Order Status Response Path', 'type' => 'text', 'default' => 'status'],
            'order_codes_response_path' => ['label' => 'Order Codes Response Path', 'type' => 'text', 'default' => 'codes'],
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
        ];
    }

    // ─── HTTP Client ─────────────────────────────────────────────────────

    /**
     * Make an HTTP request to the supplier API with proper auth headers.
     */
    private function makeRequest(string $method, string $endpoint, array $data = []): \Illuminate\Http\Client\Response
    {
        $url = rtrim($this->supplier->base_url, '/').'/'.ltrim($endpoint, '/');

        $request = Http::timeout(30)
            ->acceptJson();

        // Apply authentication
        $request = $this->applyAuth($request);

        // Apply custom headers
        $customHeaders = $this->getSetting('custom_headers', []);
        if (! empty($customHeaders)) {
            $request = $request->withHeaders($customHeaders);
        }

        $response = match (strtoupper($method)) {
            'POST' => $request->post($url, $data),
            'PUT' => $request->put($url, $data),
            'DELETE' => $request->delete($url, $data),
            default => $request->get($url, $data),
        };

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
