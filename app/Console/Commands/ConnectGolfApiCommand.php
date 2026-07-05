<?php

namespace App\Console\Commands;

use App\Models\SupplierApi;
use App\Services\Supplier\SupplierManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ConnectGolfApiCommand extends Command
{
    protected $signature = 'golf:api
                            {--base-url= : Golf API base URL (default: https://api.golf-cards.store/api)}
                            {--email= : Login email for the API user}
                            {--password= : Login password for the API user}
                            {--rate-limit=60 : Rate limit per minute}
                            {--priority=0 : Supplier priority (lower = higher)}
                            {--force : Force re-create the supplier even if one exists}';

    protected $description = 'Connect and configure the Golf API supplier via the Generic REST driver';

    private const SUPPLIER_KEY = 'golf_api_generic_rest';

    protected function golfSettings(): array
    {
        return [
            // Auth
            'auth_test_endpoint' => '/balance',
            'login_endpoint' => '/login',
            'login_method' => 'POST',
            'login_token_response_path' => 'data.accessToken',
            'token_cache_minutes' => '90',

            // Response envelope ({ status, result, message })
            'response_unwrap_path' => 'result',

            // Pagination (Laravel-style: result.data[], result.meta{})
            'pagination_enabled' => '1',
            'pagination_page_param' => 'page',
            'pagination_per_page_param' => 'limit',
            'pagination_per_page_default' => '50',
            'pagination_data_path' => 'data',
            'pagination_last_page_path' => 'meta.last_page',
            'pagination_total_path' => 'meta.total',

            // Products
            'products_endpoint' => '/products',
            'products_response_path' => 'data',
            'product_id_field' => 'id',
            'product_name_field' => 'title',
            'product_price_field' => 'price',
            'product_stock_field' => 'stock',
            'product_category_field' => 'category.title',

            // Stock (GET /products/{id})
            'stock_endpoint' => '/products/{product_id}',
            'stock_response_path' => 'data.stock',
            'stock_price_path' => 'data.price',

            // Order (POST /order)
            'order_endpoint' => '/order',
            'order_product_id_field' => 'product_id',
            'order_quantity_field' => 'quantity',
            'order_id_response_path' => 'data.id',
            'order_status_response_path' => 'data.status',
            'order_codes_response_path' => 'data.cards',
            'order_codes_value_field' => 'card',
            'order_codes_serial_field' => 'serial',
            'order_unit_price_field' => 'unit_price',
            'order_status_endpoint' => '/orders/{order_id}',

            // Top-up (same endpoint)
            'topup_order_endpoint' => '/order',
            'topup_product_id_field' => 'product_id',
            'topup_quantity_field' => 'quantity',
            'topup_account_field' => 'account_id',
            'topup_unit_price_field' => 'unit_price',
            'topup_status_response_path' => 'data.status',
            'topup_order_id_response_path' => 'data.id',
            'topup_success_status_values' => ['completed'],

            // Golf API top-up uses product custom_fields (Player ID), not account_id
            'topup_use_product_custom_fields' => '1',
            'topup_custom_fields_path' => 'data.custom_fields',

            // Balance
            'balance_endpoint' => '/balance',
            'balance_response_path' => 'data.balance',

            // Webhook (not supported)
            'webhook_secret' => '',

            // Status mapping
            'status_map' => json_encode([
                'wait' => 'processing',
                'completed' => 'fulfilled',
                'canceled' => 'failed',
            ]),

            // Health
            'health_endpoint' => '/balance',
        ];
    }

    public function handle(): int
    {
        $this->ensureAuthTypeEnum();

        $baseUrl = rtrim($this->option('base-url') ?: 'https://api.golf-cards.store/api', '/');
        $email = $this->option('email') ?: 'api-1@golf-cards.store';
        $password = $this->option('password') ?: 'Api-1@2026';

        $this->info('Golf API Supplier Setup');
        $this->line('  Base URL : '.$baseUrl);
        $this->line('  Email    : '.$email);
        $this->line('  Driver   : generic_rest');

        $supplier = SupplierApi::where('driver', 'generic_rest')
            ->where('base_url', $baseUrl)
            ->first();

        if ($supplier && ! $this->option('force')) {
            $this->warn('Supplier already exists (ID: '.$supplier->id.'). Use --force to re-create.');
            $this->line('');

            return $this->testConnection($supplier);
        }

        if ($supplier && $this->option('force')) {
            $supplier->delete();
            $this->line('Deleted existing supplier.');
        }

        $supplier = new SupplierApi;
        $supplier->name = 'Golf API';
        $supplier->driver = 'generic_rest';
        $supplier->base_url = $baseUrl;
        $supplier->auth_type = 'login_via';
        $supplier->rate_limit_per_minute = (int) $this->option('rate-limit');
        $supplier->priority = (int) $this->option('priority');
        $supplier->is_active = true;
        $supplier->is_sandbox = true;
        $supplier->supports_direct_top_up = true;
        $supplier->health_status = 'unknown';

        $supplier->setEncryptedCredentials([
            'email' => $email,
            'password' => $password,
        ]);

        $supplier->settings = $this->golfSettings();
        $supplier->save();

        $this->info('Supplier created (ID: '.$supplier->id.').');
        $this->line('');

        return $this->testConnection($supplier);
    }

    /**
     * Ensure the auth_type enum includes 'login_via'.
     */
    private function ensureAuthTypeEnum(): void
    {
        $column = DB::selectOne("SHOW COLUMNS FROM supplier_apis WHERE Field = 'auth_type'");

        if (! $column) {
            return;
        }

        $type = $column->Type;

        if (str_contains($type, 'login_via')) {
            return;
        }

        $this->line('Adding login_via to auth_type enum...');

        DB::statement("ALTER TABLE supplier_apis MODIFY COLUMN auth_type ENUM('api_key','bearer_token','oauth2','basic','hmac','login_via') DEFAULT 'api_key'");

        $this->line('Enum updated.');
    }

    private function testConnection(SupplierApi $supplier): int
    {
        $this->info('Testing connection...');

        Cache::forget("generic_rest_login_token_{$supplier->id}");

        try {
            $driver = app(SupplierManager::class)->driver($supplier);

            if (! $driver->authenticate()) {
                $this->error('Authentication failed. Check credentials.');

                return self::FAILURE;
            }

            $this->line('  Auth      : <fg=green>PASS</>');

            $balance = $driver->getBalance();

            if ($balance->supported) {
                $this->line('  Balance   : '.number_format($balance->balance, 2));
            } else {
                $this->line('  Balance   : unsupported');
            }

            $products = $driver->fetchProducts(['limit' => 3, 'page' => 1, 'fetch_all' => false]);
            $this->line('  Products  : found '.count($products).' on first page');

            if (! empty($products)) {
                $this->line('    Example : ['.$products[0]->supplierProductId.'] '.$products[0]->name.' @ '.$products[0]->price);
            }

            $this->newLine();
            $this->info('Golf API connected successfully!');
        } catch (\Throwable $e) {
            $this->error('Connection test failed: '.$e->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
