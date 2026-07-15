<?php

namespace Tests\Feature;

use App\DTOs\Supplier\StockResult;
use App\Models\BusinessSetting;
use App\Models\Currency;
use App\Models\SupplierApi;
use App\Models\SupplierProductMapping;
use App\Services\DigitalProductCodeService;
use App\Services\Supplier\Drivers\GenericRestDriver;
use App\Services\Supplier\SupplierApiLogger;
use App\Services\Supplier\SupplierCurrencyConverter;
use App\Services\Supplier\SupplierManager;
use App\Services\Supplier\SupplierOrderEligibilityService;
use App\Services\Supplier\SupplierRateLimiter;
use Illuminate\Database\Schema\Blueprint;
use Mockery;
use Tests\Concerns\ManagesTestDatabaseSchema;
use Tests\TestCase;

class SupplierManagerSyncStockTest extends TestCase
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
            $table->integer('rate_limit_per_minute')->default(60);
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
            $table->string('markup_type')->default('flat');
            $table->decimal('markup_value', 14, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_synced_at')->nullable();
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

    public function test_sync_stock_converts_jod_api_price_to_usd_mapping_cost(): void
    {
        SupplierProductMapping::flushEventListeners();

        $supplier = SupplierApi::query()->create([
            'name' => 'Golf API',
            'driver' => 'generic_rest',
            'base_url' => 'https://golf-test.example/api',
            'health_status' => 'healthy',
            'is_active' => true,
            'rate_limit_per_minute' => 60,
            'settings' => ['source_currency' => 'JOD'],
        ]);

        $mapping = SupplierProductMapping::query()->create([
            'product_id' => 10,
            'supplier_api_id' => $supplier->id,
            'supplier_product_id' => '41',
            'cost_price' => 1.62,
            'cost_currency' => 'JOD',
            'markup_type' => 'flat',
            'markup_value' => 0.03,
            'is_active' => true,
        ]);

        $driver = Mockery::mock(GenericRestDriver::class);
        $driver->shouldReceive('fetchStock')
            ->once()
            ->with('41')
            ->andReturn(new StockResult(
                available: 10,
                price: 1.62,
                currency: 'JOD',
                rawData: [],
            ));

        $logger = Mockery::mock(SupplierApiLogger::class);
        $logger->shouldReceive('logRequest')->once()->andReturn(1);
        $logger->shouldReceive('logResponse')->once();

        $rateLimiter = Mockery::mock(SupplierRateLimiter::class);
        $rateLimiter->shouldReceive('attempt')->once()->andReturn(true);

        $codeService = Mockery::mock(DigitalProductCodeService::class);
        $codeService->shouldReceive('applyApiPriceIfManualDepleted')
            ->once()
            ->with(10);

        $manager = Mockery::mock(
            SupplierManager::class,
            [
                $codeService,
                $logger,
                $rateLimiter,
                Mockery::mock(SupplierOrderEligibilityService::class),
                app(SupplierCurrencyConverter::class),
            ]
        )->makePartial();

        $manager->shouldReceive('driver')->once()->andReturn($driver);

        $manager->syncStock($mapping->load('supplierApi'));

        $mapping->refresh();

        $this->assertEqualsWithDelta(2.28, (float) $mapping->cost_price, 0.01);
        $this->assertSame('USD', $mapping->cost_currency);
    }

    public function test_sync_stock_does_not_overwrite_usd_mapping_with_jod_values(): void
    {
        SupplierProductMapping::flushEventListeners();

        $supplier = SupplierApi::query()->create([
            'name' => 'Golf API',
            'driver' => 'generic_rest',
            'base_url' => 'https://golf-test.example/api',
            'health_status' => 'healthy',
            'is_active' => true,
            'rate_limit_per_minute' => 60,
            'settings' => ['source_currency' => 'JOD'],
        ]);

        $mapping = SupplierProductMapping::query()->create([
            'product_id' => 11,
            'supplier_api_id' => $supplier->id,
            'supplier_product_id' => '86',
            'cost_price' => 1.99,
            'cost_currency' => 'USD',
            'markup_type' => 'flat',
            'markup_value' => 0.10,
            'is_active' => true,
        ]);

        $driver = Mockery::mock(GenericRestDriver::class);
        $driver->shouldReceive('fetchStock')
            ->once()
            ->with('86')
            ->andReturn(new StockResult(
                available: 10,
                price: 1.41,
                currency: 'JOD',
                rawData: [],
            ));

        $logger = Mockery::mock(SupplierApiLogger::class);
        $logger->shouldReceive('logRequest')->once()->andReturn(1);
        $logger->shouldReceive('logResponse')->once();

        $rateLimiter = Mockery::mock(SupplierRateLimiter::class);
        $rateLimiter->shouldReceive('attempt')->once()->andReturn(true);

        $codeService = Mockery::mock(DigitalProductCodeService::class);
        $codeService->shouldReceive('applyApiPriceIfManualDepleted')
            ->once()
            ->with(11);

        $manager = Mockery::mock(
            SupplierManager::class,
            [
                $codeService,
                $logger,
                $rateLimiter,
                Mockery::mock(SupplierOrderEligibilityService::class),
                app(SupplierCurrencyConverter::class),
            ]
        )->makePartial();

        $manager->shouldReceive('driver')->once()->andReturn($driver);

        $manager->syncStock($mapping->load('supplierApi'));

        $mapping->refresh();

        $this->assertEqualsWithDelta(1.99, (float) $mapping->cost_price, 0.01);
        $this->assertSame('USD', $mapping->cost_currency);
    }

    public function test_sync_stock_normalizes_legacy_jod_mapping_when_api_returns_no_price(): void
    {
        SupplierProductMapping::flushEventListeners();

        $supplier = SupplierApi::query()->create([
            'name' => 'Golf API',
            'driver' => 'generic_rest',
            'base_url' => 'https://golf-test.example/api',
            'health_status' => 'healthy',
            'is_active' => true,
            'rate_limit_per_minute' => 60,
            'settings' => ['source_currency' => 'JOD'],
        ]);

        $mapping = SupplierProductMapping::query()->create([
            'product_id' => 12,
            'supplier_api_id' => $supplier->id,
            'supplier_product_id' => '41',
            'cost_price' => 1.62,
            'cost_currency' => 'JOD',
            'markup_type' => 'flat',
            'markup_value' => 0.03,
            'is_active' => true,
        ]);

        $driver = Mockery::mock(GenericRestDriver::class);
        $driver->shouldReceive('fetchStock')
            ->once()
            ->with('41')
            ->andReturn(new StockResult(
                available: 10,
                price: 0.0,
                currency: 'USD',
                rawData: [],
            ));

        $logger = Mockery::mock(SupplierApiLogger::class);
        $logger->shouldReceive('logRequest')->once()->andReturn(1);
        $logger->shouldReceive('logResponse')->once();

        $rateLimiter = Mockery::mock(SupplierRateLimiter::class);
        $rateLimiter->shouldReceive('attempt')->once()->andReturn(true);

        $codeService = Mockery::mock(DigitalProductCodeService::class);
        $codeService->shouldReceive('applyApiPriceIfManualDepleted')
            ->once()
            ->with(12);

        $manager = Mockery::mock(
            SupplierManager::class,
            [
                $codeService,
                $logger,
                $rateLimiter,
                Mockery::mock(SupplierOrderEligibilityService::class),
                app(SupplierCurrencyConverter::class),
            ]
        )->makePartial();

        $manager->shouldReceive('driver')->once()->andReturn($driver);

        $manager->syncStock($mapping->load('supplierApi'));

        $mapping->refresh();

        $this->assertEqualsWithDelta(2.28, (float) $mapping->cost_price, 0.01);
        $this->assertSame('USD', $mapping->cost_currency);
    }
}
