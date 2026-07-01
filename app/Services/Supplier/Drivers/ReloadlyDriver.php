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
 * Reloadly API driver for gift cards.
 *
 * Auth flow: OAuth2 client_credentials → bearer token cached for 30 min.
 * Docs: https://developers.reloadly.com/
 */
class ReloadlyDriver implements SupplierDriverInterface
{
    use ProvidesUnsupportedDirectTopUp;

    private SupplierApi $supplier;

    /** @var array<string, mixed> */
    private array $credentials = [];

    /** @var array<string, mixed> */
    private array $settings = [];

    private ?string $accessToken = null;

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
            $this->obtainAccessToken();

            return $this->accessToken !== null;
        } catch (\Throwable) {
            return false;
        }
    }

    public function fetchProducts(array $filters = []): array
    {
        $this->ensureAuthenticated();

        $page = $filters['page'] ?? 1;
        $size = $filters['size'] ?? 50;

        $response = Http::withToken($this->accessToken)
            ->timeout(30)
            ->get($this->url('/giftcards/products'), [
                'page' => $page,
                'size' => $size,
                'countryCode' => $filters['country_code'] ?? null,
            ]);

        if ($response->failed()) {
            throw new \RuntimeException("Reloadly fetchProducts failed: HTTP {$response->status()}");
        }

        $items = $response->json('content', []);

        return array_map(function ($item): SupplierProductDTO {
            return new SupplierProductDTO(
                supplierProductId: (string) ($item['productId'] ?? ''),
                name: (string) ($item['productName'] ?? ''),
                description: $item['redeemInstruction']['concise'] ?? null,
                category: $item['category']['name'] ?? null,
                imageUrl: $item['logoUrls'][0] ?? null,
                price: (float) ($item['fixedRecipientDenominations'][0] ?? $item['minRecipientDenomination'] ?? 0),
                currency: (string) ($item['recipientCurrencyCode'] ?? 'USD'),
                stockAvailable: ($item['available'] ?? true) ? 999 : 0,
                region: $item['country']['isoName'] ?? null,
                rawData: $item,
            );
        }, $items);
    }

    public function fetchStock(string $supplierProductId): StockResult
    {
        $this->ensureAuthenticated();

        $response = Http::withToken($this->accessToken)
            ->timeout(30)
            ->get($this->url("/giftcards/products/{$supplierProductId}"));

        if ($response->failed()) {
            throw new \RuntimeException("Reloadly fetchStock failed: HTTP {$response->status()}");
        }

        $data = $response->json();

        return new StockResult(
            available: ($data['available'] ?? true) ? 999 : 0,
            price: (float) ($data['fixedRecipientDenominations'][0] ?? $data['minRecipientDenomination'] ?? 0),
            currency: (string) ($data['senderCurrencyCode'] ?? 'USD'),
            rawData: $data,
        );
    }

    public function placeOrder(string $supplierProductId, int $quantity, ?float $unitPrice = null): SupplierOrderResult
    {
        $this->ensureAuthenticated();

        $codes = [];

        // Reloadly processes one gift card per order
        for ($i = 0; $i < $quantity; $i++) {
            $response = Http::withToken($this->accessToken)
                ->timeout(60)
                ->post($this->url('/giftcards/order'), [
                    'productId' => (int) $supplierProductId,
                    'quantity' => 1,
                    'unitPrice' => $this->settings['unit_price'] ?? null,
                    'customIdentifier' => 'BS-'.uniqid(),
                    'senderName' => $this->settings['sender_name'] ?? 'Buyselles',
                    'recipientEmail' => $this->settings['recipient_email'] ?? null,
                ]);

            if ($response->successful()) {
                $data = $response->json();
                $redeemCode = $data['redeemCode'] ?? null;

                if ($redeemCode) {
                    $codes[] = $redeemCode;
                }
            }
        }

        return new SupplierOrderResult(
            supplierOrderId: 'reloadly-batch-'.uniqid(),
            status: count($codes) >= $quantity ? 'fulfilled' : (count($codes) > 0 ? 'partial' : 'failed'),
            codes: $codes,
            rawResponse: ['total_requested' => $quantity, 'total_received' => count($codes)],
        );
    }

    public function getOrderStatus(string $supplierOrderId): SupplierOrderResult
    {
        $this->ensureAuthenticated();

        $response = Http::withToken($this->accessToken)
            ->timeout(30)
            ->get($this->url("/giftcards/orders/transactions/{$supplierOrderId}/cards"));

        $data = $response->json();
        $codes = array_map(fn ($card) => $card['cardNumber'] ?? '', $data ?? []);
        $codes = array_filter($codes);

        return new SupplierOrderResult(
            supplierOrderId: $supplierOrderId,
            status: ! empty($codes) ? 'fulfilled' : 'pending',
            codes: array_values($codes),
            rawResponse: $data ?? [],
        );
    }

    public function parseWebhook(Request $request): WebhookResult
    {
        // Reloadly doesn't provide webhooks for gift cards — polling model
        return new WebhookResult(
            type: 'unsupported',
            verified: false,
            rawPayload: $request->all(),
        );
    }

    public function healthCheck(): HealthResult
    {
        $start = microtime(true);

        try {
            $this->obtainAccessToken();
            $latency = (int) ((microtime(true) - $start) * 1000);

            return new HealthResult(
                status: $latency > 5000 ? 'degraded' : 'healthy',
                latencyMs: $latency,
                message: 'OAuth2 token obtained successfully',
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
        // Reloadly provides account balance at GET /accounts/balance
        try {
            $this->ensureAuthenticated();
            $host = $this->supplier->is_sandbox
                ? 'https://giftcards-sandbox.reloadly.com'
                : 'https://giftcards.reloadly.com';

            $response = Http::timeout(15)
                ->withToken($this->accessToken)
                ->acceptJson()
                ->get("{$host}/accounts/balance");

            if ($response->failed()) {
                return new BalanceResult(supported: true, message: "HTTP {$response->status()}");
            }

            $data = $response->json();

            return new BalanceResult(
                supported: true,
                balance: (float) ($data['balance'] ?? 0),
                currency: (string) ($data['currencyCode'] ?? 'USD'),
            );
        } catch (\Throwable $e) {
            return new BalanceResult(supported: true, message: $e->getMessage());
        }
    }

    public function getRequiredCredentialFields(): array
    {
        return [
            'client_id' => ['label' => 'Client ID', 'type' => 'text', 'required' => true],
            'client_secret' => ['label' => 'Client Secret', 'type' => 'password', 'required' => true],
        ];
    }

    public function getConfigSchema(): array
    {
        return [
            'sender_name' => ['label' => 'Sender Name', 'type' => 'text', 'default' => 'Buyselles'],
            'recipient_email' => ['label' => 'Default Recipient Email', 'type' => 'text', 'default' => ''],
            'unit_price' => ['label' => 'Fixed Unit Price (leave empty for default)', 'type' => 'number', 'default' => null],
        ];
    }

    // ─── OAuth2 Token Management ─────────────────────────────────────────

    private function ensureAuthenticated(): void
    {
        if ($this->accessToken) {
            return;
        }

        $cacheKey = "supplier_reloadly_token:{$this->supplier->id}";
        $this->accessToken = cache($cacheKey);

        if (! $this->accessToken) {
            $this->obtainAccessToken();
        }
    }

    private function obtainAccessToken(): void
    {
        $authUrl = $this->supplier->is_sandbox
            ? 'https://auth.reloadly.com/oauth/token'
            : 'https://auth.reloadly.com/oauth/token';

        $audience = $this->supplier->is_sandbox
            ? 'https://giftcards-sandbox.reloadly.com'
            : 'https://giftcards.reloadly.com';

        $response = Http::post($authUrl, [
            'client_id' => $this->credentials['client_id'] ?? '',
            'client_secret' => $this->credentials['client_secret'] ?? '',
            'grant_type' => 'client_credentials',
            'audience' => $audience,
        ]);

        if ($response->failed()) {
            throw new \RuntimeException('Reloadly OAuth2 authentication failed: HTTP '.$response->status());
        }

        $this->accessToken = $response->json('access_token');
        $expiresIn = (int) $response->json('expires_in', 1800);

        // Cache token for slightly less than its full lifetime
        cache(["supplier_reloadly_token:{$this->supplier->id}" => $this->accessToken], now()->addSeconds($expiresIn - 60));
    }

    private function url(string $path): string
    {
        return rtrim($this->supplier->base_url, '/').'/'.ltrim($path, '/');
    }
}
