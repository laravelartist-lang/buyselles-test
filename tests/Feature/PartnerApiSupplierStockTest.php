<?php

namespace Tests\Feature;

use App\Models\SupplierProductMapping;
use App\Services\Supplier\SupplierManager;
use Mockery;
use Tests\Concerns\ManagesTestDatabaseSchema;
use Tests\Concerns\SetsUpPartnerApiTestSchema;
use Tests\TestCase;

class PartnerApiSupplierStockTest extends TestCase
{
    use ManagesTestDatabaseSchema;
    use SetsUpPartnerApiTestSchema;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpPartnerApiSchema();
        $this->seedActivePartnerApiKey();
    }

    public function test_supplier_mapped_product_uses_supplier_stock_in_listing(): void
    {
        $this->seedProduct(id: 10, name: 'Bamboo Mapped');
        $this->seedSupplierMapping(productId: 10, driver: 'bamboo');

        $manager = Mockery::mock(SupplierManager::class);
        $manager->shouldReceive('getAvailableStockForMapping')
            ->once()
            ->with(Mockery::type(SupplierProductMapping::class))
            ->andReturn(42);

        $this->app->instance(SupplierManager::class, $manager);

        $response = $this->getJson('/api/v1/partner/products', $this->partnerApiHeaders());

        $response->assertOk();
        $response->assertJsonPath('data.0.available_stock', 42);
        $response->assertJsonPath('data.0.fulfillment_type', 'supplier_codes');
    }

    public function test_supplier_mapped_product_includes_local_code_pool_in_stock(): void
    {
        $this->seedProduct(id: 12, name: 'Hybrid Stock');
        $this->seedSupplierMapping(productId: 12, driver: 'bamboo');
        $this->seedDigitalCode(productId: 12, plainCode: 'HYBRID-LOCAL-1');
        $this->seedDigitalCode(productId: 12, plainCode: 'HYBRID-LOCAL-2');

        $manager = Mockery::mock(SupplierManager::class);
        $manager->shouldReceive('getAvailableStockForMapping')
            ->once()
            ->with(Mockery::type(SupplierProductMapping::class))
            ->andReturn(5);

        $this->app->instance(SupplierManager::class, $manager);

        $response = $this->getJson('/api/v1/partner/products', $this->partnerApiHeaders());

        $response->assertOk();
        $response->assertJsonPath('data.0.available_stock', 7);
    }

    public function test_local_product_uses_local_code_pool_stock(): void
    {
        $this->seedProduct(id: 11, name: 'Local Codes');
        $this->seedDigitalCode(productId: 11, plainCode: 'LOCAL-001');
        $this->seedDigitalCode(productId: 11, plainCode: 'LOCAL-002');

        $response = $this->getJson('/api/v1/partner/products', $this->partnerApiHeaders());

        $response->assertOk();
        $response->assertJsonPath('data.0.available_stock', 2);
        $response->assertJsonPath('data.0.fulfillment_type', 'local_codes');
        $response->assertJsonPath('data.0.supplier', null);
    }

    private function seedProduct(int $id, string $name): void
    {
        $this->app['db']->table('products')->insert([
            'id' => $id,
            'user_id' => 1,
            'added_by' => 'admin',
            'name' => $name,
            'slug' => 'product-'.$id,
            'product_type' => 'digital',
            'digital_product_type' => 'ready_product',
            'status' => 1,
            'request_status' => 1,
            'partner_approved' => true,
            'unit_price' => 10,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function seedSupplierMapping(int $productId, string $driver): void
    {
        $supplierId = $this->app['db']->table('supplier_apis')->insertGetId([
            'name' => ucfirst($driver),
            'driver' => $driver,
            'base_url' => 'https://supplier.test',
            'credentials' => '{}',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->app['db']->table('supplier_product_mappings')->insert([
            'product_id' => $productId,
            'supplier_api_id' => $supplierId,
            'supplier_product_id' => 'sku-'.$productId,
            'is_active' => true,
            'is_direct_topup' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
