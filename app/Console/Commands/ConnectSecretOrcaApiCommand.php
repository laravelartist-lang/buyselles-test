<?php

namespace App\Console\Commands;

use App\Jobs\SyncSupplierCatalogJob;
use App\Models\SupplierApi;
use App\Services\Supplier\SupplierManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class ConnectSecretOrcaApiCommand extends Command
{
    protected $signature = 'secretorca:connect
                            {--base-url= : SecretOrca API base URL (default: https://api.secretorca.com)}
                            {--api-key= : API key (defaults to SECRETORCA_API_KEY env var)}
                            {--rate-limit=120 : Rate limit per minute}
                            {--priority=0 : Supplier priority (lower = higher)}
                            {--sandbox : Mark supplier as sandbox/test key}
                            {--force : Force re-create the supplier even if one exists}';

    protected $description = 'Connect and configure the SecretOrca supplier via the Generic REST driver';

    private const SUPPLIER_NAME = 'SecretOrca';

    /**
     * @return array<string, mixed>
     */
    protected function secretOrcaSettings(): array
    {
        return [
            'api_key_header' => 'X-API-Key',

            'auth_test_endpoint' => '/api/v1/external/catalog/products/',
            'health_endpoint' => '/api/v1/external/catalog/products/',

            // Django REST Framework pagination: { count, next, previous, results[] }
            'pagination_page_param' => 'page',
            'pagination_per_page_param' => 'page_size',
            'pagination_per_page_default' => '100',
            'pagination_page_base' => '1',
            'pagination_data_path' => 'results',
            'pagination_total_path' => 'count',

            'products_endpoint' => '/api/v1/external/catalog/products/',
            'products_response_path' => 'results',
            'product_id_field' => 'id',
            'product_name_field' => 'name',
            'product_price_field' => 'unit_price',
            'product_category_field' => 'bot_name',
            'product_region_field' => 'code',
            'product_stock_default' => '999999',

            'topup_order_endpoint' => '/api/v1/external/orders/create/',
            'topup_product_id_field' => 'product_id',
            'topup_quantity_field' => 'quantity',
            'topup_account_field' => 'target_account',
            'topup_order_id_response_path' => 'order_number',
            'topup_status_response_path' => 'status',
            'topup_success_status_values' => ['completed'],
            'order_status_endpoint' => '/api/v1/external/orders/{order_id}/',

            'webhook_signature_header' => 'X-Webhook-Signature',
            'webhook_hash_algo' => 'sha256',

            'status_map' => json_encode([
                'pending' => 'processing',
                'queued' => 'processing',
                'processing' => 'processing',
                'completed' => 'fulfilled',
                'failed' => 'failed',
                'cancelled' => 'failed',
                'refunded' => 'failed',
            ]),

            'source_currency' => 'USD',
            'price_decimal_places' => '10',
        ];
    }

    public function handle(): int
    {
        $baseUrl = rtrim($this->option('base-url') ?: 'https://api.secretorca.com', '/');
        $apiKey = $this->option('api-key') ?: env('SECRETORCA_API_KEY');

        if (! is_string($apiKey) || $apiKey === '') {
            $this->error('Missing API key. Pass --api-key= or set SECRETORCA_API_KEY in .env');

            return self::FAILURE;
        }

        $this->info('SecretOrca Supplier Setup');
        $this->line('  Base URL : '.$baseUrl);
        $this->line('  Driver   : generic_rest');
        $this->line('  Auth     : api_key (X-API-Key header)');

        $supplier = SupplierApi::query()
            ->where('driver', 'generic_rest')
            ->where('base_url', $baseUrl)
            ->where('name', self::SUPPLIER_NAME)
            ->first();

        if ($supplier && ! $this->option('force')) {
            $supplier->settings = array_merge($this->secretOrcaSettings(), $supplier->settings ?? []);
            $supplier->auth_type = 'api_key';
            $supplier->supports_direct_top_up = true;
            $supplier->setEncryptedCredentials(['api_key' => $apiKey]);
            $supplier->save();

            $this->clearCatalogCache($supplier);
            $this->info('Updated existing supplier settings (ID: '.$supplier->id.').');
            $this->newLine();

            return $this->testConnection($supplier);
        }

        if ($supplier && $this->option('force')) {
            $supplier->delete();
            $this->line('Deleted existing supplier.');
        }

        $supplier = new SupplierApi;
        $supplier->name = self::SUPPLIER_NAME;
        $supplier->driver = 'generic_rest';
        $supplier->base_url = $baseUrl;
        $supplier->auth_type = 'api_key';
        $supplier->rate_limit_per_minute = (int) $this->option('rate-limit');
        $supplier->priority = (int) $this->option('priority');
        $supplier->is_active = true;
        $supplier->is_sandbox = (bool) $this->option('sandbox');
        $supplier->supports_direct_top_up = true;
        $supplier->health_status = 'unknown';
        $supplier->setEncryptedCredentials(['api_key' => $apiKey]);
        $supplier->settings = $this->secretOrcaSettings();
        $supplier->save();

        $this->info('Supplier created (ID: '.$supplier->id.').');
        $this->newLine();

        return $this->testConnection($supplier);
    }

    private function clearCatalogCache(SupplierApi $supplier): void
    {
        Cache::forget(SyncSupplierCatalogJob::catalogCacheKey($supplier->id));
        Cache::forget(SyncSupplierCatalogJob::statusCacheKey($supplier->id));
    }

    private function testConnection(SupplierApi $supplier): int
    {
        $this->info('Testing connection...');

        try {
            $driver = app(SupplierManager::class)->driver($supplier);

            $health = $driver->healthCheck();
            $this->line('  Health    : '.$health->status.' — '.$health->message);

            if ($health->status === 'down') {
                $this->error('Health check failed.');

                return self::FAILURE;
            }

            $products = $driver->fetchProducts(['page' => 1, 'page_size' => 5, 'fetch_all' => false]);
            $this->line('  Products  : found '.count($products).' on first page');

            if ($products !== []) {
                $this->line('    Example : ['.$products[0]->supplierProductId.'] '.$products[0]->name.' @ '.$products[0]->price.' '.$products[0]->currency);
            }

            $this->newLine();
            $this->info('SecretOrca connected successfully!');
            $this->newLine();
            $this->comment('Catalog sync runs on the "catalog" queue.');
            $this->comment('Local dev: set QUEUE_CONNECTION=sync in .env, or run: php artisan queue:work redis --queue=catalog');
        } catch (\Throwable $e) {
            $this->error('Connection test failed: '.$e->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
