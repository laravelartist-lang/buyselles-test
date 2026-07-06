<?php

namespace Tests\Feature;

use App\DTOs\Supplier\StockResult;
use App\Jobs\SyncSupplierMappingPricesJob;
use App\Models\SupplierApi;
use App\Models\SupplierProductMapping;
use App\Services\DigitalProductCodeService;
use App\Services\Supplier\Drivers\GenericRestDriver;
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

        $this->recreateTable('supplier_apis', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->nullable();
            $table->string('driver')->default('generic_rest');
            $table->string('base_url')->nullable();
            $table->string('health_status')->default('healthy');
            $table->boolean('is_active')->default(true);
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
            $table->timestamps();
        });

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
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_job_updates_mapping_cost_price_when_api_price_changes(): void
    {
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
                price: 2.41,
                currency: 'USD',
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

        (new SyncSupplierMappingPricesJob)->handle($manager, $codeService);

        $mapping->refresh();

        $this->assertSame(2.41, (float) $mapping->cost_price);
        $this->assertSame('USD', $mapping->cost_currency);
    }
}
