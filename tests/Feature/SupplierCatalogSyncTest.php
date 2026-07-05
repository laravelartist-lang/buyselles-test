<?php

namespace Tests\Feature;

use App\DTOs\Supplier\SupplierProductDTO;
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

        Cache::put(SupplierCatalogSyncService::checkpointCacheKey($supplier->id), [
            'next_page' => 2,
            'products' => [['id' => '1', 'name' => 'Existing']],
            'started_at' => now()->toIso8601String(),
        ], now()->addHour());

        $result = $service->syncPage($supplier->id, 2);

        $this->assertFalse($result->hasMorePages);

        $checkpoint = Cache::get(SupplierCatalogSyncService::checkpointCacheKey($supplier->id));
        $this->assertSame(3, $checkpoint['next_page']);
        $this->assertCount(2, $checkpoint['products']);

        $service->markFailed($supplier->id, 3, new \RuntimeException('curl error 28: timed out'));

        $status = Cache::get(SupplierCatalogSyncService::statusCacheKey($supplier->id));
        $this->assertSame('failed', $status['state']);
        $this->assertTrue($status['can_resume']);
        $this->assertSame(3, $status['next_page']);

        $resumePage = $service->beginSync($supplier->id, freshStart: false);
        $this->assertSame(3, $resumePage);
    }
}
