<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\Supplier\SupplierController;
use App\Models\SupplierApi;
use App\Models\SupplierProductMapping;
use App\Services\Supplier\Presets\SecretOrcaPreset;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\ManagesTestDatabaseSchema;
use Tests\TestCase;

class AdminSupplierDirectTopUpTest extends TestCase
{
    use ManagesTestDatabaseSchema;

    protected function setUp(): void
    {
        parent::setUp();

        $this->recreateTable('business_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('type');
            $table->text('value')->nullable();
            $table->timestamps();
        });

        $this->app['db']->table('business_settings')->insert([
            'type' => 'language',
            'value' => json_encode([['code' => 'en', 'default' => true, 'direction' => 'ltr']]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->recreateTable('supplier_api_logs', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('supplier_api_id');
            $table->string('action', 50)->nullable();
            $table->string('endpoint', 500)->nullable();
            $table->string('method', 10)->default('GET');
            $table->json('request_payload')->nullable();
            $table->json('response_payload')->nullable();
            $table->unsignedSmallInteger('http_status_code')->nullable();
            $table->unsignedInteger('response_time_ms')->nullable();
            $table->string('status')->default('success');
            $table->text('error_message')->nullable();
            $table->unsignedBigInteger('order_id')->nullable();
            $table->timestamp('created_at')->nullable();
        });

        $this->recreateTable('supplier_apis', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->nullable();
            $table->string('driver')->default('generic_rest');
            $table->string('base_url')->nullable();
            $table->string('auth_type')->default('api_key');
            $table->text('credentials')->nullable();
            $table->json('settings')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('supports_direct_top_up')->default(true);
            $table->boolean('is_sandbox')->default(true);
            $table->string('health_status')->default('healthy');
            $table->integer('rate_limit_per_minute')->default(120);
            $table->timestamps();
        });

        $this->recreateTable('products', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->nullable();
            $table->string('product_type')->default('digital');
            $table->decimal('unit_price', 24, 10)->default(0);
            $table->decimal('purchase_price', 24, 10)->default(0);
            $table->integer('minimum_order_qty')->default(900);
            $table->boolean('status')->default(true);
            $table->timestamps();
        });

        $this->recreateTable('supplier_product_mappings', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('supplier_api_id');
            $table->string('supplier_product_id');
            $table->string('supplier_product_name')->nullable();
            $table->decimal('cost_price', 24, 10)->default(0);
            $table->string('cost_currency', 3)->default('USD');
            $table->string('markup_type')->default('percent');
            $table->decimal('markup_value', 10, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_direct_topup')->default(true);
            $table->string('direct_topup_account_label')->nullable();
            $table->string('direct_topup_region', 2)->nullable();
            $table->decimal('direct_topup_bundle_quantity', 20, 4)->nullable();
            $table->timestamps();
        });

        $this->recreateTable('translations', function (Blueprint $table): void {
            $table->id();
            $table->string('translationable_type')->nullable();
            $table->unsignedBigInteger('translationable_id')->nullable();
            $table->string('locale')->nullable();
            $table->string('key')->nullable();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        $this->recreateTable('reviews', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('product_id')->nullable();
            $table->unsignedBigInteger('delivery_man_id')->nullable();
            $table->boolean('status')->default(true);
            $table->timestamps();
        });

        $this->recreateTable('digital_product_codes', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('product_id')->index();
            $table->text('code');
            $table->string('status')->default('available')->index();
            $table->boolean('is_active')->default(true);
            $table->date('expiry_date')->nullable();
            $table->timestamps();
        });
    }

    public function test_admin_test_topup_uses_mapping_defaults_and_secret_orca_payload(): void
    {
        Http::fake([
            'secretorca.test/api/v1/external/orders/create/' => Http::response([
                'order_number' => 'ORD-ADMIN-1',
                'status' => 'pending',
                'total_cost' => '0.95',
            ], 201),
        ]);

        $supplier = $this->makeSecretOrcaSupplier();
        $mapping = $this->makeDirectTopUpMapping($supplier);

        $controller = app(SupplierController::class);
        $response = $controller->testTopUpOrder($supplier->id, Request::create('/admin/supplier/'.$supplier->id.'/test-topup', 'POST', [
            'mapping_id' => $mapping->id,
            'target_account' => '108594930',
            'quantity' => 900,
        ]));

        $payload = $response->getData(true);

        $this->assertTrue($payload['success']);
        $this->assertSame('ORD-ADMIN-1', $payload['order_number']);
        $this->assertSame('processing', $payload['status']);
        $this->assertSame($mapping->id, $payload['mapping_id']);
        $this->assertTrue($payload['sandbox']);

        Http::assertSent(function ($request): bool {
            $body = $request->data();

            return $request->url() === 'https://secretorca.test/api/v1/external/orders/create/'
                && $body['product_id'] === 'product-uuid-1'
                && $body['quantity'] === '900'
                && $body['target_account'] === '108594930'
                && $body['region'] === 'EG'
                && str_starts_with((string) $body['client_order_id'], 'admin-test-')
                && str_starts_with((string) $body['idempotency_key'], 'admin-test-');
        });
    }

    public function test_admin_test_topup_requires_region_for_secret_orca_when_mapping_has_none(): void
    {
        $supplier = $this->makeSecretOrcaSupplier();
        $mapping = $this->makeDirectTopUpMapping($supplier, region: null);

        $controller = app(SupplierController::class);
        $response = $controller->testTopUpOrder($supplier->id, Request::create('/admin/supplier/'.$supplier->id.'/test-topup', 'POST', [
            'mapping_id' => $mapping->id,
            'target_account' => '108594930',
            'quantity' => 900,
        ]));

        $payload = $response->getData(true);

        $this->assertFalse($payload['success']);
        $this->assertStringContainsString('region', strtolower((string) $payload['message']));
    }

    public function test_repair_secret_orca_settings_persists_preset_endpoints(): void
    {
        $supplier = $this->makeSecretOrcaSupplier();
        $supplier->settings = ['api_key_header' => 'X-API-Key'];
        $supplier->save();

        $controller = app(SupplierController::class);
        $response = $controller->repairSecretOrcaSettings($supplier->id);

        $payload = $response->getData(true);

        $this->assertTrue($payload['success']);

        $supplier->refresh();

        $this->assertSame(
            SecretOrcaPreset::settings()['order_status_endpoint'],
            $supplier->settings['order_status_endpoint'] ?? null,
        );
    }

    private function makeSecretOrcaSupplier(): SupplierApi
    {
        $supplier = SupplierApi::query()->create([
            'name' => SecretOrcaPreset::SUPPLIER_NAME,
            'driver' => 'generic_rest',
            'base_url' => 'https://secretorca.test',
            'auth_type' => 'api_key',
            'settings' => SecretOrcaPreset::settings(),
            'is_active' => true,
            'supports_direct_top_up' => true,
            'is_sandbox' => true,
            'health_status' => 'healthy',
            'rate_limit_per_minute' => 120,
        ]);
        $supplier->setEncryptedCredentials(['api_key' => 'sk_test_example']);
        $supplier->save();

        return $supplier;
    }

    private function makeDirectTopUpMapping(SupplierApi $supplier, ?string $region = 'EG'): SupplierProductMapping
    {
        $this->app['db']->table('products')->insert([
            'id' => 3,
            'name' => 'Secret Orca Test Product',
            'product_type' => 'digital',
            'minimum_order_qty' => 900,
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return SupplierProductMapping::withoutEvents(function () use ($supplier, $region): SupplierProductMapping {
            return SupplierProductMapping::query()->create([
                'product_id' => 3,
                'supplier_api_id' => $supplier->id,
                'supplier_product_id' => 'product-uuid-1',
                'supplier_product_name' => 'Bigo Diamonds',
                'cost_price' => 0.001062834,
                'cost_currency' => 'USD',
                'markup_type' => 'percent',
                'markup_value' => 0,
                'is_active' => true,
                'is_direct_topup' => true,
                'direct_topup_account_label' => 'Player ID',
                'direct_topup_region' => $region,
            ]);
        });
    }
}
