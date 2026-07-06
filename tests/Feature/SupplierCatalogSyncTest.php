<?php

namespace Tests\Feature;

use App\DTOs\Supplier\SupplierProductDTO;
use App\Jobs\SyncSupplierCatalogPageJob;
use App\Models\SupplierApi;
use App\Services\Supplier\Drivers\BambooDriver;
use App\Services\Supplier\SupplierCatalogSyncService;
use App\Services\Supplier\SupplierManager;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Mockery;
use Tests\Concerns\ManagesTestDatabaseSchema;
use Tests\TestCase;

class SupplierCatalogSyncTest extends TestCase
{
    use ManagesTestDatabaseSchema;

    protected function setUp(): void
    {
        parent::setUp();

        $this->recreateTable('supplier_apis', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->nullable();
            $table->string('driver')->default('bamboo');
            $table->string('base_url')->nullable();
            $table->json('settings')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function test_failed_page_is_saved_and_sync_can_resume_from_checkpoint(): void
    {
        Bus::fake();

        $supplier = SupplierApi::create([
            'name' => 'Bamboo',
            'driver' => 'bamboo',
            'base_url' => 'https://api.bamboocardportal.com',
            'settings' => [],
            'is_active' => true,
        ]);

        $dto = new SupplierProductDTO(
            supplierProductId: '123',
            name: 'Test Card',
            description: null,
            category: null,
            imageUrl: null,
            price: 10.0,
            currency: 'USD',
            stockAvailable: 5,
            region: 'US',
            rawData: [],
        );

        $driver = Mockery::mock(BambooDriver::class);
        $driver->shouldReceive('fetchProducts')
            ->once()
            ->andReturnUsing(function (array $filters) use ($dto) {
                if (is_callable($filters['on_page'] ?? null)) {
                    $filters['on_page'](2, 1, 250);
                }

                return [$dto];
            });

        $manager = Mockery::mock(SupplierManager::class);
        $manager->shouldReceive('driver')->andReturn($driver);
        $this->app->instance(SupplierManager::class, $manager);

        $service = app(SupplierCatalogSyncService::class);
        $service->beginSync($supplier->id, freshStart: true);

        Cache::put(SupplierCatalogSyncService::checkpointPageCacheKey($supplier->id, 0), [
            ['id' => '1', 'name' => 'Existing'],
        ], now()->addHour());

        Cache::put(SupplierCatalogSyncService::checkpointCacheKey($supplier->id), [
            'next_page' => 2,
            'pages_stored' => [0],
            'total_products' => 1,
            'started_at' => now()->toIso8601String(),
        ], now()->addHour());

        $result = $service->syncPage($supplier->id, 2);

        $this->assertFalse($result->hasMorePages);

        $checkpoint = Cache::get(SupplierCatalogSyncService::checkpointCacheKey($supplier->id));
        $this->assertSame(3, $checkpoint['next_page']);
        $this->assertContains(2, $checkpoint['pages_stored']);
        $this->assertSame(2, count($checkpoint['pages_stored']));

        $service->markFailed($supplier->id, 3, new \RuntimeException('curl error 28: timed out'));

        $status = Cache::get(SupplierCatalogSyncService::statusCacheKey($supplier->id));
        $this->assertSame('failed', $status['state']);
        $this->assertTrue($status['can_resume']);
        $this->assertSame(3, $status['next_page']);

        $resumePage = $service->beginSync($supplier->id, freshStart: false);
        $this->assertSame(3, $resumePage);
    }

    public function test_mark_complete_merges_page_chunks_into_catalog_cache(): void
    {
        $supplier = SupplierApi::create([
            'name' => 'Bamboo',
            'driver' => 'bamboo',
            'base_url' => 'https://api.bamboocardportal.com',
            'settings' => [],
            'is_active' => true,
        ]);

        $service = app(SupplierCatalogSyncService::class);

        Cache::put(SupplierCatalogSyncService::checkpointPageCacheKey($supplier->id, 0), [
            ['id' => '1', 'name' => 'One'],
        ], now()->addHour());
        Cache::put(SupplierCatalogSyncService::checkpointPageCacheKey($supplier->id, 1), [
            ['id' => '2', 'name' => 'Two'],
        ], now()->addHour());
        Cache::put(SupplierCatalogSyncService::checkpointCacheKey($supplier->id), [
            'next_page' => 2,
            'pages_stored' => [0, 1],
            'total_pages' => 2,
            'last_successful_page' => 1,
        ], now()->addHour());

        $service->markComplete($supplier->id);

        $catalog = Cache::get(SupplierCatalogSyncService::catalogCacheKey($supplier->id));
        $this->assertCount(2, $catalog);
        $this->assertSame('done', Cache::get(SupplierCatalogSyncService::statusCacheKey($supplier->id))['state']);
        $this->assertNull(Cache::get(SupplierCatalogSyncService::checkpointCacheKey($supplier->id)));
    }

    public function test_pause_prevents_next_page_dispatch(): void
    {
        Bus::fake();

        $supplier = SupplierApi::create([
            'name' => 'Bamboo',
            'driver' => 'bamboo',
            'base_url' => 'https://api.bamboocardportal.com',
            'settings' => [],
            'is_active' => true,
        ]);

        $dto = new SupplierProductDTO(
            supplierProductId: '123',
            name: 'Test Card',
            description: null,
            category: null,
            imageUrl: null,
            price: 10.0,
            currency: 'USD',
            stockAvailable: 5,
            region: 'US',
            rawData: [],
        );

        $driver = Mockery::mock(BambooDriver::class);
        $driver->shouldReceive('fetchProducts')
            ->once()
            ->andReturnUsing(function (array $filters) use ($dto) {
                if (is_callable($filters['on_page'] ?? null)) {
                    $filters['on_page'](0, 100, 250);
                }

                return [$dto];
            });

        $manager = Mockery::mock(SupplierManager::class);
        $manager->shouldReceive('driver')->andReturn($driver);
        $this->app->instance(SupplierManager::class, $manager);

        $service = app(SupplierCatalogSyncService::class);
        $service->beginSync($supplier->id, freshStart: true);

        $result = $service->syncPage($supplier->id, 0);
        $this->assertTrue($result->hasMorePages);

        $service->pauseSync($supplier->id);
        $this->assertSame('paused', $service->stopReasonAfterPage($supplier->id));

        (new SyncSupplierCatalogPageJob($supplier->id, 1))->handle($service);

        Bus::assertNotDispatched(SyncSupplierCatalogPageJob::class);

        $status = $service->getStatus($supplier->id);
        $this->assertSame('paused', $status['state']);
        $this->assertTrue($status['can_resume']);
    }

    public function test_cancelled_job_does_not_chain_or_complete(): void
    {
        Bus::fake();

        $supplier = SupplierApi::create([
            'name' => 'Bamboo',
            'driver' => 'bamboo',
            'base_url' => 'https://api.bamboocardportal.com',
            'settings' => [],
            'is_active' => true,
        ]);

        $service = app(SupplierCatalogSyncService::class);
        $service->beginSync($supplier->id, freshStart: true);
        $service->cancelSync($supplier->id);

        (new SyncSupplierCatalogPageJob($supplier->id, 0))->handle($service);

        Bus::assertNotDispatched(SyncSupplierCatalogPageJob::class);
        $this->assertSame('cancelled', $service->getStatus($supplier->id)['state']);
        $this->assertNull(Cache::get(SupplierCatalogSyncService::catalogCacheKey($supplier->id)));
    }

    public function test_fetch_scoped_products_uses_paged_fetch_not_full_catalog(): void
    {
        $supplier = SupplierApi::create([
            'name' => 'Bamboo',
            'driver' => 'bamboo',
            'base_url' => 'https://api.bamboocardportal.com',
            'settings' => [],
            'is_active' => true,
        ]);

        $dto = new SupplierProductDTO(
            supplierProductId: '999',
            name: 'Brand SKU',
            description: null,
            category: null,
            imageUrl: null,
            price: 5.0,
            currency: 'USD',
            stockAvailable: 1,
            region: 'US',
            rawData: ['internalId' => 'brand-1'],
        );

        $driver = Mockery::mock(BambooDriver::class);
        $driver->shouldReceive('fetchProducts')
            ->once()
            ->with(Mockery::on(function (array $filters): bool {
                return ($filters['fetch_all'] ?? null) === false
                    && ($filters['page'] ?? null) === 0
                    && ($filters['brand_id'] ?? null) === 'brand-1';
            }))
            ->andReturnUsing(function (array $filters) use ($dto) {
                if (is_callable($filters['on_page'] ?? null)) {
                    $filters['on_page'](0, 1, 1);
                }

                return [$dto];
            });

        $manager = Mockery::mock(SupplierManager::class);
        $manager->shouldReceive('driver')->andReturn($driver);
        $this->app->instance(SupplierManager::class, $manager);

        $products = app(SupplierCatalogSyncService::class)->fetchScopedProducts($supplier, [
            'brand_id' => 'brand-1',
        ]);

        $this->assertCount(1, $products);
    }
}
