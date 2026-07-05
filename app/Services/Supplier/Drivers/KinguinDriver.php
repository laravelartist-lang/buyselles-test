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
use App\Services\Supplier\Concerns\ProvidesUnsupportedDirectTopUp;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

/**
 * Kinguin API driver for game keys.
 *
 * Auth: API key via X-Api-Key header.
 * Docs: https://api.kinguin.net/
 */
class KinguinDriver implements SupplierDriverInterface
{
    use ProvidesUnsupportedDirectTopUp;

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
            $response = $this->makeRequest('GET', '/v2/products', ['page' => 0, 'size' => 1]);

            return $response->successful();
        } catch (\Throwable) {
            return false;
        }
    }

    public function fetchProducts(array $filters = []): array
    {
        $params = [
            'page' => $filters['page'] ?? 0,
            'size' => $filters['size'] ?? 50,
        ];

        if (isset($filters['name'])) {
            $params['name'] = $filters['name'];
        }

        if (isset($filters['region'])) {
            $params['regionId'] = $filters['region'];
        }

        $response = $this->makeRequest('GET', '/v2/products', $params);

        if ($response->failed()) {
            throw new \RuntimeException("Kinguin fetchProducts failed: HTTP {$response->status()}");
        }

        $items = $response->json('results', []);
        $total = (int) ($response->json('item_count') ?? count($items));

        if (is_callable($filters['on_page'] ?? null)) {
            $filters['on_page']((int) ($params['page'] ?? 0), count($items), $total);
        }

        return array_map(function ($item): SupplierProductDTO {
            return new SupplierProductDTO(
                supplierProductId: (string) ($item['productId'] ?? $item['kinguinId'] ?? ''),
                name: (string) ($item['name'] ?? ''),
                description: $item['description'] ?? null,
                category: $item['platform'] ?? null,
                imageUrl: $item['coverImage'] ?? ($item['images']['cover']['url'] ?? null),
                price: (float) ($item['price'] ?? 0),
                currency: 'EUR',
                stockAvailable: (int) ($item['qty'] ?? 0),
                region: $item['regionId'] ?? null,
                rawData: $item,
            );
        }, $items);
    }

    public function fetchStock(string $supplierProductId): StockResult
    {
        $response = $this->makeRequest('GET', "/v2/products/{$supplierProductId}");

        if ($response->failed()) {
            throw new \RuntimeException("Kinguin fetchStock failed: HTTP {$response->status()}");
        }

        $data = $response->json();

        return new StockResult(
            available: (int) ($data['qty'] ?? 0),
            price: (float) ($data['price'] ?? 0),
            currency: 'EUR',
            rawData: $data ?? [],
        );
    }

    public function placeOrder(string $supplierProductId, int $quantity, ?float $unitPrice = null): SupplierOrderResult
    {
        $response = $this->makeRequest('POST', '/v2/order', [
            'products' => [
                [
                    'productId' => $supplierProductId,
                    'qty' => $quantity,
                ],
            ],
        ]);

        if ($response->failed()) {
            throw new \RuntimeException("Kinguin placeOrder failed: HTTP {$response->status()}");
        }

        $data = $response->json();
        $orderId = (string) ($data['orderId'] ?? '');

        // Kinguin orders are async — dispatch immediately, codes come later
        return new SupplierOrderResult(
            supplierOrderId: $orderId,
            status: 'processing',
            codes: [],
            rawResponse: $data ?? [],
        );
    }

    public function getOrderStatus(string $supplierOrderId): SupplierOrderResult
    {
        $response = $this->makeRequest('GET', "/v2/order/{$supplierOrderId}");

        if ($response->failed()) {
            throw new \RuntimeException("Kinguin getOrderStatus failed: HTTP {$response->status()}");
        }

        $data = $response->json();

        $keys = [];
        if (isset($data['products'])) {
            foreach ($data['products'] as $product) {
                foreach ($product['keys'] ?? [] as $key) {
                    if (isset($key['serial'])) {
                        $keys[] = $key['serial'];
                    }
                }
            }
        }

        $status = strtolower($data['status'] ?? 'pending');
        $normalizedStatus = match ($status) {
            'completed', 'delivered' => 'fulfilled',
            'processing', 'in_progress' => 'processing',
            'pending' => 'pending',
            'canceled', 'refunded' => 'failed',
            default => $status,
        };

        return new SupplierOrderResult(
            supplierOrderId: $supplierOrderId,
            status: $normalizedStatus,
            codes: $keys,
            rawResponse: $data ?? [],
        );
    }

    public function parseWebhook(Request $request): WebhookResult
    {
        $webhookSecret = $this->settings['webhook_secret'] ?? null;

        if ($webhookSecret) {
            $signature = $request->header('X-Kinguin-Signature');
            $payload = $request->getContent();
            $expected = hash_hmac('sha256', $payload, $webhookSecret);

            if (! hash_equals($expected, $signature ?? '')) {
                return new WebhookResult(type: 'unknown', verified: false, rawPayload: $request->all());
            }
        }

        $data = $request->all();
        $orderId = (string) ($data['orderId'] ?? '');
        $status = strtolower($data['status'] ?? 'unknown');

        $keys = [];
        if (isset($data['products'])) {
            foreach ($data['products'] as $product) {
                foreach ($product['keys'] ?? [] as $key) {
                    if (isset($key['serial'])) {
                        $keys[] = $key['serial'];
                    }
                }
            }
        }

        $type = match ($status) {
            'completed', 'delivered' => 'order_fulfilled',
            'canceled', 'refunded' => 'order_failed',
            default => 'unknown',
        };

        return new WebhookResult(
            type: $type,
            supplierOrderId: $orderId,
            codes: $keys,
            status: $status,
            verified: true,
            rawPayload: $data,
        );
    }

    public function healthCheck(): HealthResult
    {
        $start = microtime(true);

        try {
            $response = $this->makeRequest('GET', '/v2/products', ['page' => 0, 'size' => 1]);
            $latency = (int) ((microtime(true) - $start) * 1000);

            if ($response->successful()) {
                return new HealthResult(
                    status: $latency > 5000 ? 'degraded' : 'healthy',
                    latencyMs: $latency,
                    message: "HTTP {$response->status()} in {$latency}ms",
                );
            }

            return new HealthResult(status: 'down', latencyMs: $latency, message: "HTTP {$response->status()}");
        } catch (\Throwable $e) {
            $latency = (int) ((microtime(true) - $start) * 1000);

            return new HealthResult(status: 'down', latencyMs: $latency, message: $e->getMessage());
        }
    }

    public function getBalance(): BalanceResult
    {
        // Kinguin does not expose an account balance / credit endpoint.
        return BalanceResult::unsupported();
    }

    public function getRequiredCredentialFields(): array
    {
        return [
            'api_key' => ['label' => 'API Key', 'type' => 'text', 'required' => true],
        ];
    }

    public function getConfigSchema(): array
    {
        return [
            'webhook_secret' => ['label' => 'Webhook Secret', 'type' => 'password', 'default' => ''],
        ];
    }

    // ─── HTTP Client ─────────────────────────────────────────────────────

    private function makeRequest(string $method, string $endpoint, array $data = []): \Illuminate\Http\Client\Response
    {
        $url = rtrim($this->supplier->base_url, '/').'/'.ltrim($endpoint, '/');

        $request = Http::timeout(30)
            ->acceptJson()
            ->withHeaders([
                'X-Api-Key' => $this->credentials['api_key'] ?? '',
            ]);

        return match (strtoupper($method)) {
            'POST' => $request->post($url, $data),
            'PUT' => $request->put($url, $data),
            default => $request->get($url, $data),
        };
    }
}
