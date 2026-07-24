<?php

namespace Tests\Feature;

use App\DTOs\Supplier\StockResult;
use App\Jobs\SyncSupplierMappingPricesJob;
use App\Models\BusinessSetting;
use App\Models\Currency;
use App\Models\SupplierApi;
use App\Models\SupplierProductMapping;
use App\Services\DigitalProductCodeService;
use App\Services\Supplier\Drivers\GenericRestDriver;
use App\Services\Supplier\SupplierCurrencyConverter;
use App\Services\Supplier\SupplierManager;
use Illuminate\Database\Schema\Blueprint;
use Mockery;
use Tests\Concerns\ManagesTestDatabaseSchema;
use Tests\TestCase;

class SyncSupplierMappingPricesJobTest extends TestCase
{
    use ManagesTestDatabaseSchema;

    protected function setUp(): void
    {
        parent::setUp();

        $this->recreateTable('currencies', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->nullable();
            $table->string('symbol')->nullable();
            $table->string('code', 10);
            $table->decimal('exchange_rate', 14, 6)->default(1);
            $table->boolean('status')->default(true);
            $table->timestamps();
        });

        $this->recreateTable('supplier_apis', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->nullable();
            $table->string('driver')->default('generic_rest');
            $table->string('base_url')->nullable();
            $table->string('health_status')->default('healthy');
            $table->boolean('is_active')->default(true);
            $table->json('settings')->nullable();
            $table->timestamps();
        });

        $this->recreateTable('supplier_product_mappings', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('supplier_api_id');
            $table->string('supplier_product_id');
            $table->decimal('cost_price', 14, 2)->default(0);
            $table->string('cost_currency', 3)->default('USD');
            $table->string('markup_type')->default('percent');
            $table->decimal('markup_value', 14, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        $this->recreateTable('products', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->nullable();
            $table->decimal('unit_price', 14, 2)->default(0);
            $table->boolean('partner_api_only')->default(false);
            $table->timestamps();
        });

        $this->recreateTable('business_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('type');
            $table->text('value')->nullable();
            $table->timestamps();
        });

        $usd = Currency::query()->create([
            'name' => 'USD',
            'symbol' => '$',
            'code' => 'USD',
            'exchange_rate' => 1,
            'status' => true,
        ]);

        Currency::query()->create([
            'name' => 'Jordanian Dinar',
            'symbol' => 'JOD',
            'code' => 'JOD',
            'exchange_rate' => 0.709,
            'status' => true,
        ]);

        BusinessSetting::query()->create(['type' => 'currency_model', 'value' => 'multi_currency']);
        BusinessSetting::query()->create(['type' => 'system_default_currency', 'value' => (string) $usd->id]);
        BusinessSetting::query()->create(['type' => 'decimal_point_settings', 'value' => '2']);
        BusinessSetting::query()->create(['type' => 'language', 'value' => json_encode([['code' => 'en', 'default' => true, 'direction' => 'ltr']])]);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_job_updates_mapping_cost_price_when_api_price_changes(): void
    {
        SupplierProductMapping::flushEventListeners();

        $this->app['db']->table('products')->insert([
            'id' => 10,
            'name' => 'Test Product',
            'unit_price' => 5.00,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $supplier = SupplierApi::query()->create([
            'name' => 'Golf API',
            'driver' => 'generic_rest',
            'base_url' => 'https://golf-test.example/api',
            'health_status' => 'healthy',
            'is_active' => true,
            'settings' => ['source_currency' => 'JOD'],
        ]);

        $mapping = SupplierProductMapping::query()->create([
            'product_id' => 10,
            'supplier_api_id' => $supplier->id,
            'supplier_product_id' => '42',
            'cost_price' => 2.00,
            'cost_currency' => 'USD',
            'markup_type' => 'percent',
            'markup_value' => 0,
            'is_active' => true,
        ]);

        $driver = Mockery::mock(GenericRestDriver::class);
        $driver->shouldReceive('fetchStock')
            ->once()
            ->with('42')
            ->andReturn(new StockResult(
                available: 10,
                price: 1.709,
                currency: 'JOD',
                rawData: [],
            ));

        $manager = Mockery::mock(SupplierManager::class);
        $manager->shouldReceive('driver')->andReturn($driver);
        $this->app->instance(SupplierManager::class, $manager);

        $codeService = Mockery::mock(DigitalProductCodeService::class);
        $codeService->shouldReceive('applyApiPriceIfManualDepleted')
            ->once()
            ->with(10);
        $this->app->instance(DigitalProductCodeService::class, $codeService);

        $converter = app(SupplierCurrencyConverter::class);

        (new SyncSupplierMappingPricesJob)->handle($manager, $codeService, $converter);

        $mapping->refresh();

        $this->assertEqualsWithDelta(2.41, (float) $mapping->cost_price, 0.01);
        $this->assertSame('USD', $mapping->cost_currency);
    }
}
