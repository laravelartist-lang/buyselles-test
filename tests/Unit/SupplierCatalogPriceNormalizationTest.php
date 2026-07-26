<?php

namespace Tests\Unit;

use App\Models\BusinessSetting;
use App\Models\Currency;
use App\Models\SupplierApi;
use App\Services\Supplier\SupplierCatalogSyncService;
use Illuminate\Database\Schema\Blueprint;
use Tests\Concerns\ManagesTestDatabaseSchema;
use Tests\TestCase;

class SupplierCatalogPriceNormalizationTest extends TestCase
{
    use ManagesTestDatabaseSchema;

    private SupplierCatalogSyncService $service;

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

        $this->recreateTable('business_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('type');
            $table->text('value')->nullable();
            $table->timestamps();
        });

        $this->recreateTable('supplier_apis', function (Blueprint $table): void {
            $table->id();
            $table->string('driver')->default('generic_rest');
            $table->json('settings')->nullable();
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

        $this->service = app(SupplierCatalogSyncService::class);
    }

    public function test_normalizes_legacy_catalog_row_that_stored_jod_as_usd(): void
    {
        $supplier = SupplierApi::query()->create([
            'driver' => 'generic_rest',
            'settings' => ['source_currency' => 'JOD'],
        ]);

        $items = [[
            'id' => '41',
            'name' => 'zain gsm1 JD',
            'price' => 1.62,
            'currency' => 'USD',
            'stock' => 16,
        ]];

        $normalized = $this->service->normalizeCatalogPrices($items, $supplier);

        $this->assertSame(1.62, $normalized[0]['source_price']);
        $this->assertSame('JOD', $normalized[0]['source_currency']);
        $this->assertEqualsWithDelta(2.28, $normalized[0]['price'], 0.01);
        $this->assertSame('USD', $normalized[0]['currency']);
        $this->assertTrue($normalized[0]['price_converted']);
    }

    public function test_keeps_already_converted_catalog_row_in_sync(): void
    {
        $supplier = SupplierApi::query()->create([
            'driver' => 'generic_rest',
            'settings' => ['source_currency' => 'JOD'],
        ]);

        $items = [[
            'id' => '41',
            'name' => 'zain gsm1 JD',
            'price' => 2.28,
            'currency' => 'USD',
            'source_price' => 1.62,
            'source_currency' => 'JOD',
            'price_converted' => true,
            'stock' => 16,
        ]];

        $normalized = $this->service->normalizeCatalogPrices($items, $supplier);

        $this->assertSame(1.62, $normalized[0]['source_price']);
        $this->assertEqualsWithDelta(2.28, $normalized[0]['price'], 0.01);
    }

    public function test_repairs_rows_that_stored_uncoverted_usd_copy(): void
    {
        $supplier = SupplierApi::query()->create([
            'driver' => 'generic_rest',
            'settings' => ['source_currency' => 'JOD'],
        ]);

        $items = [[
            'id' => '41',
            'name' => 'zain gsm1 JD',
            'price' => 1.62,
            'currency' => 'USD',
            'source_price' => 1.62,
            'source_currency' => 'JOD',
            'price_converted' => true,
            'stock' => 16,
        ]];

        $normalized = $this->service->normalizeCatalogPrices($items, $supplier);

        $this->assertSame(1.62, $normalized[0]['source_price']);
        $this->assertEqualsWithDelta(2.28, $normalized[0]['price'], 0.01);
        $this->assertTrue($normalized[0]['price_converted']);
    }

    public function test_catalog_prices_need_repair_when_cached_jod_differs_from_live_api(): void
    {
        $supplier = SupplierApi::query()->create([
            'driver' => 'generic_rest',
            'settings' => ['source_currency' => 'JOD'],
        ]);

        $items = [[
            'id' => '41',
            'name' => 'zain gsm1 JD',
            'price' => 3.22,
            'currency' => 'USD',
            'source_price' => 2.28,
            'source_currency' => 'JOD',
            'price_converted' => true,
        ]];

        $manager = \Mockery::mock(\App\Services\Supplier\SupplierManager::class);
        $driver = \Mockery::mock(\App\Contracts\SupplierDriverInterface::class);
        $driver->shouldReceive('fetchStock')
            ->once()
            ->with('41')
            ->andReturn(new \App\DTOs\Supplier\StockResult(
                available: 16,
                price: 1.62,
                currency: 'JOD',
                rawData: ['price' => 1.62],
            ));
        $manager->shouldReceive('driver')->andReturn($driver);
        $this->app->instance(\App\Services\Supplier\SupplierManager::class, $manager);

        $service = app(SupplierCatalogSyncService::class);

        $method = new \ReflectionMethod($service, 'catalogPricesNeedRepair');
        $method->setAccessible(true);

        $this->assertTrue($method->invoke($service, $items, $supplier));
    }

    public function test_catalog_prices_need_repair_when_usd_is_uncoverted_copy(): void
    {
        $supplier = SupplierApi::query()->create([
            'driver' => 'generic_rest',
            'settings' => ['source_currency' => 'JOD'],
        ]);

        $items = [[
            'id' => '41',
            'name' => 'zain gsm1 JD',
            'price' => 1.62,
            'currency' => 'USD',
            'source_price' => 1.62,
            'source_currency' => 'JOD',
            'price_converted' => true,
        ]];

        $service = app(SupplierCatalogSyncService::class);

        $method = new \ReflectionMethod($service, 'catalogPricesNeedRepair');
        $method->setAccessible(true);

        $this->assertTrue($method->invoke($service, $items, $supplier));
    }

    public function test_repair_catalog_prices_from_driver_uses_supplier_decimal_settings(): void
    {
        $supplier = SupplierApi::query()->create([
            'driver' => 'golf_api',
            'settings' => [
                'source_currency' => 'JOD',
                'product_price_field' => 'price',
                'price_decimal_places' => 2,
            ],
        ]);

        $items = [[
            'id' => '99',
            'name' => 'Test product',
            'price' => 1.0,
            'currency' => 'USD',
            'source_price' => 1.0,
            'source_currency' => 'JOD',
            'price_converted' => true,
        ]];

        $manager = \Mockery::mock(\App\Services\Supplier\SupplierManager::class);
        $driver = \Mockery::mock(\App\Contracts\SupplierDriverInterface::class);
        $driver->shouldReceive('fetchProducts')
            ->once()
            ->with(['fetch_all' => true])
            ->andReturn([
                \App\DTOs\Supplier\SupplierProductDTO::fromArray([
                    'id' => '99',
                    'name' => 'Test product',
                    'price' => 1.62,
                    'currency' => 'JOD',
                    'stock' => 10,
                ]),
            ]);
        $manager->shouldReceive('driver')->andReturn($driver);
        $this->app->instance(\App\Services\Supplier\SupplierManager::class, $manager);

        $service = app(SupplierCatalogSyncService::class);
        $repaired = $service->repairCatalogPricesFromDriver($supplier, $items);

        $this->assertSame(1.62, $repaired[0]['source_price']);
        $this->assertEqualsWithDelta(2.28, $repaired[0]['price'], 0.02);
        $this->assertTrue($repaired[0]['price_converted']);
    }
}
