<?php

namespace Tests\Feature;

use App\Jobs\ReleasePartnerEscrowJob;
use App\Jobs\SupplierCodeFetchJob;
use App\Services\Supplier\SupplierManager;
use Illuminate\Support\Facades\Bus;
use Mockery;
use Tests\Concerns\ManagesTestDatabaseSchema;
use Tests\Concerns\SetsUpPartnerApiTestSchema;
use Tests\TestCase;

class PartnerApiSupplierOrderTest extends TestCase
{
    use ManagesTestDatabaseSchema;
    use SetsUpPartnerApiTestSchema;

    protected function setUp(): void
    {
        parent::setUp();

        Bus::fake([SupplierCodeFetchJob::class, ReleasePartnerEscrowJob::class]);

        $this->setUpPartnerApiSchema();
        $this->seedActivePartnerApiKey(sellerId: 1, walletBalance: 500);

        $this->app['db']->table('seller_wallets')->insert([
            'seller_id' => 1,
            'total_earning' => 0,
            'pending_balance' => 0,
            'pending_withdraw' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_create_order_on_supplier_mapped_product_returns_pending_fulfillment(): void
    {
        $this->seedProduct(id: 20, name: 'Supplier Backed');
        $this->seedSupplierMapping(productId: 20, driver: 'golf_api');

        $manager = Mockery::mock(SupplierManager::class);
        $manager->shouldReceive('getAvailableStockForMapping')->andReturn(5);
        $this->app->instance(SupplierManager::class, $manager);

        $response = $this->postJson('/api/v1/partner/orders', [
            'product_id' => 20,
            'quantity' => 1,
            'reference' => 'supplier-order-test',
        ], $this->partnerApiHeaders());

        $response->assertCreated();
        $response->assertJsonPath('data.status', 'pending_fulfillment');
        $response->assertJsonPath('data.quantity_fulfilled', 0);
        $response->assertJsonPath('data.codes', []);

        Bus::assertDispatched(SupplierCodeFetchJob::class);
    }

    public function test_create_order_on_local_product_returns_fulfilled_with_codes(): void
    {
        $this->seedProduct(id: 21, name: 'Local Pool');
        $this->seedDigitalCode(productId: 21, plainCode: 'FULFILL-CODE-1');

        $response = $this->postJson('/api/v1/partner/orders', [
            'product_id' => 21,
            'quantity' => 1,
        ], $this->partnerApiHeaders());

        $response->assertCreated();
        $response->assertJsonPath('data.status', 'fulfilled');
        $response->assertJsonPath('data.quantity_fulfilled', 1);
        $response->assertJsonCount(1, 'data.codes');

        Bus::assertNotDispatched(SupplierCodeFetchJob::class);
    }

    public function test_create_order_on_supplier_mapped_product_uses_local_codes_when_supplier_stock_is_zero(): void
    {
        $this->seedProduct(id: 23, name: 'Hybrid Fulfillment');
        $this->seedSupplierMapping(productId: 23, driver: 'bamboo');
        $this->seedDigitalCode(productId: 23, plainCode: 'LOCAL-FIRST-CODE');

        $manager = Mockery::mock(SupplierManager::class);
        $manager->shouldReceive('getAvailableStockForMapping')->andReturn(0);
        $this->app->instance(SupplierManager::class, $manager);

        $response = $this->postJson('/api/v1/partner/orders', [
            'product_id' => 23,
            'quantity' => 1,
        ], $this->partnerApiHeaders());

        $response->assertCreated();
        $response->assertJsonPath('data.status', 'fulfilled');
        $response->assertJsonPath('data.quantity_fulfilled', 1);
        $response->assertJsonCount(1, 'data.codes');

        Bus::assertNotDispatched(SupplierCodeFetchJob::class);
    }

    public function test_create_order_rejects_direct_topup_product(): void
    {
        $this->seedProduct(id: 22, name: 'Jawaker Topup');
        $this->seedDirectTopupMapping(productId: 22);

        $response = $this->postJson('/api/v1/partner/orders', [
            'product_id' => 22,
            'quantity' => 1,
        ], $this->partnerApiHeaders());

        $response->assertStatus(422);
        $response->assertJsonPath('error', 'Direct top-up products are not supported via Partner API.');
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

    private function seedDirectTopupMapping(int $productId): void
    {
        $supplierId = $this->app['db']->table('supplier_apis')->insertGetId([
            'name' => 'Golf API',
            'driver' => 'golf_api',
            'base_url' => 'https://golf.test',
            'credentials' => '{}',
            'is_active' => true,
            'supports_direct_top_up' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->app['db']->table('supplier_product_mappings')->insert([
            'product_id' => $productId,
            'supplier_api_id' => $supplierId,
            'supplier_product_id' => 'jawaker-1',
            'is_active' => true,
            'is_direct_topup' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
