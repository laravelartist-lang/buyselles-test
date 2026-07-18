<?php

namespace Tests\Feature;

use App\Jobs\SupplierCodeFetchJob;
use App\Jobs\SupplierOrderPollJob;
use App\Models\AdminWallet;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\SupplierOrder;
use App\Models\SupplierProductMapping;
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

        $this->setUpPartnerApiSchema();
        $this->seedActivePartnerApiKey(sellerId: 1, walletBalance: 500);
    }

    public function test_create_order_on_supplier_mapped_product_returns_pending_fulfillment(): void
    {
        Bus::fake([SupplierCodeFetchJob::class, SupplierOrderPollJob::class]);

        $this->seedProduct(id: 20, name: 'Supplier Backed');
        $this->seedSupplierMapping(productId: 20, driver: 'golf_api', costPrice: 7);
        $this->assignProductToPartnerCatalog(productId: 20, partnerPrice: 10);

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
        $response->assertJsonPath('data.total_cost', 10);
        $response->assertJsonPath('data.pricing.admin_margin', 3);
        $response->assertJsonPath('data.pricing.supplier_cost', 7);
        $response->assertJsonPath('data.supplier.driver', 'golf_api');

        $order = Order::query()->find($response->json('data.order_id'));
        $this->assertSame(3.0, (float) $order->admin_commission);
        $this->assertSame('admin', $order->seller_is);

        $adminWallet = AdminWallet::query()->where('admin_id', 1)->first();
        $this->assertSame(3.0, (float) $adminWallet->commission_earned);

        Bus::assertDispatched(SupplierCodeFetchJob::class);
    }

    public function test_create_order_on_local_product_returns_fulfilled_with_codes(): void
    {
        Bus::fake([SupplierCodeFetchJob::class, SupplierOrderPollJob::class]);

        $this->seedProduct(id: 21, name: 'Local Pool');
        $this->assignProductToPartnerCatalog(productId: 21, partnerPrice: 10);
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
        Bus::fake([SupplierCodeFetchJob::class, SupplierOrderPollJob::class]);

        $this->seedProduct(id: 23, name: 'Hybrid Fulfillment');
        $this->seedSupplierMapping(productId: 23, driver: 'bamboo');
        $this->assignProductToPartnerCatalog(productId: 23, partnerPrice: 10);
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

    public function test_create_order_rejects_unassigned_direct_topup_product(): void
    {
        Bus::fake([SupplierCodeFetchJob::class, SupplierOrderPollJob::class]);

        $this->seedProduct(id: 22, name: 'Jawaker Topup');
        $this->seedDirectTopupMapping(productId: 22);

        $response = $this->postJson('/api/v1/partner/orders', [
            'product_id' => 22,
            'quantity' => 1,
            'direct_topup_account_id' => 'player123',
        ], $this->partnerApiHeaders());

        $response->assertNotFound();
        $response->assertJsonPath('error', 'Product is not available in this partner catalog.');
    }

    public function test_supplier_code_fetch_job_is_configured_to_run_after_commit(): void
    {
        $job = new SupplierCodeFetchJob(1);

        $this->assertTrue($job->afterCommit);
    }

    public function test_partner_bamboo_order_dispatches_supplier_job_after_transaction(): void
    {
        Bus::fake([SupplierCodeFetchJob::class, SupplierOrderPollJob::class]);

        $this->seedProduct(id: 24, name: 'Bamboo Product');
        $mappingId = $this->seedSupplierMapping(productId: 24, driver: 'bamboo', costPrice: 7);
        $this->assignProductToPartnerCatalog(productId: 24, partnerPrice: 10);

        $manager = Mockery::mock(SupplierManager::class);
        $manager->shouldReceive('getAvailableStockForMapping')->andReturn(5);
        $this->app->instance(SupplierManager::class, $manager);

        $response = $this->postJson('/api/v1/partner/orders', [
            'product_id' => 24,
            'quantity' => 1,
        ], $this->partnerApiHeaders());

        $response->assertCreated();
        $response->assertJsonPath('data.status', 'pending_fulfillment');

        Bus::assertDispatched(SupplierCodeFetchJob::class, function (SupplierCodeFetchJob $job) use ($response): bool {
            return $job->orderId === (int) $response->json('data.order_id');
        });

        $this->assertDatabaseHas('orders', [
            'id' => $response->json('data.order_id'),
            'payment_status' => 'paid',
        ]);

        $this->assertSame('pending', OrderDetail::query()
            ->where('order_id', $response->json('data.order_id'))
            ->value('delivery_status'));

        $this->assertNotNull($mappingId);
    }

    public function test_partner_bamboo_order_creates_supplier_order_record(): void
    {
        Bus::fake([SupplierOrderPollJob::class]);

        $this->seedProduct(id: 25, name: 'Bamboo Async');
        $mappingId = $this->seedSupplierMapping(
            productId: 25,
            driver: 'bamboo',
            costPrice: 7,
            codeSourcePriority: SupplierProductMapping::CODE_SOURCE_SUPPLIER_FIRST,
        );
        $this->assignProductToPartnerCatalog(productId: 25, partnerPrice: 10);

        $driver = Mockery::mock(\App\Contracts\SupplierDriverInterface::class);
        $driver->shouldReceive('getOrderStatus')
            ->andReturn(new \App\DTOs\Supplier\SupplierOrderResult(
                supplierOrderId: 'bamboo-request-123',
                status: 'fulfilled',
                codes: [['code' => 'BAMBOO-CODE-1']],
            ));

        $this->mock(SupplierManager::class, function ($manager) use ($mappingId, $driver): void {
            $manager->shouldReceive('getAvailableStockForMapping')->andReturn(5);
            $manager->shouldReceive('driver')->andReturn($driver);
            $manager->shouldReceive('fetchAndStockCodes')
                ->once()
                ->andReturnUsing(function () use ($mappingId): array {
                    $supplierOrderId = SupplierOrder::query()->create([
                        'supplier_api_id' => 1,
                        'supplier_product_mapping_id' => $mappingId,
                        'supplier_order_id' => 'bamboo-request-123',
                        'quantity' => 1,
                        'cost_per_unit' => 7,
                        'total_cost' => 7,
                        'cost_currency' => 'USD',
                        'status' => 'processing',
                    ])->id;

                    return [
                        'inserted' => 0,
                        'supplier_id' => 1,
                        'supplier_order_id' => $supplierOrderId,
                    ];
                });
        });

        $response = $this->postJson('/api/v1/partner/orders', [
            'product_id' => 25,
            'quantity' => 1,
        ], $this->partnerApiHeaders());

        $response->assertCreated();
        $response->assertJsonPath('data.status', 'fulfilled');
        $response->assertJsonCount(1, 'data.codes');

        $orderId = (int) $response->json('data.order_id');

        $this->assertDatabaseHas('supplier_orders', [
            'order_id' => $orderId,
            'supplier_order_id' => 'bamboo-request-123',
        ]);
    }

    public function test_partner_denomination_order_passes_denomination_to_supplier_fetch(): void
    {
        Bus::fake([SupplierCodeFetchJob::class, SupplierOrderPollJob::class]);

        [$productId, $denominationId] = $this->seedFixedDenominationProduct();
        $this->assignProductToPartnerCatalog(productId: $productId, partnerPrice: 1);
        $this->seedDenominationPartnerPrice($productId, partnerPrice: 10.5);

        $manager = Mockery::mock(SupplierManager::class);
        $manager->shouldReceive('getAvailableStockForMapping')->andReturn(5);
        $this->app->instance(SupplierManager::class, $manager);

        $response = $this->postJson('/api/v1/partner/orders', [
            'product_id' => $productId,
            'quantity' => 1,
            'supplier_denomination_id' => $denominationId,
            'expected_total' => 10.5,
        ], $this->partnerApiHeaders());

        $response->assertCreated();

        $this->assertDatabaseHas('order_details', [
            'order_id' => $response->json('data.order_id'),
            'supplier_denomination_id' => $denominationId,
        ]);

        Bus::assertDispatched(SupplierCodeFetchJob::class);
    }

    public function test_optional_denomination_order_uses_per_denomination_partner_price(): void
    {
        Bus::fake([SupplierCodeFetchJob::class, SupplierOrderPollJob::class]);

        [$productId, $denominationId] = $this->seedFixedDenominationProduct(isCustomizable: false);
        $this->assignProductToPartnerCatalog(productId: $productId, partnerPrice: 10);
        $this->seedDenominationPartnerPrice($productId, partnerPrice: 10.5);

        $manager = Mockery::mock(SupplierManager::class);
        $manager->shouldReceive('getAvailableStockForMapping')->andReturn(5);
        $this->app->instance(SupplierManager::class, $manager);

        $simpleResponse = $this->postJson('/api/v1/partner/orders', [
            'product_id' => $productId,
            'quantity' => 1,
        ], $this->partnerApiHeaders());

        $simpleResponse->assertCreated();
        $simpleResponse->assertJsonPath('data.total_cost', 10);

        $denomResponse = $this->postJson('/api/v1/partner/orders', [
            'product_id' => $productId,
            'quantity' => 1,
            'supplier_denomination_id' => $denominationId,
            'expected_total' => 10.5,
        ], $this->partnerApiHeaders());

        $denomResponse->assertCreated();
        $denomResponse->assertJsonPath('data.total_cost', 10.5);
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

    private function seedSupplierMapping(
        int $productId,
        string $driver,
        float $costPrice = 0,
        string $codeSourcePriority = SupplierProductMapping::CODE_SOURCE_LOCAL_FIRST,
    ): int {
        $supplierId = $this->app['db']->table('supplier_apis')->insertGetId([
            'name' => ucfirst($driver),
            'driver' => $driver,
            'base_url' => 'https://supplier.test',
            'credentials' => '{}',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (int) $this->app['db']->table('supplier_product_mappings')->insertGetId([
            'product_id' => $productId,
            'supplier_api_id' => $supplierId,
            'supplier_product_id' => 'sku-'.$productId,
            'cost_price' => $costPrice,
            'code_source_priority' => $codeSourcePriority,
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

    /**
     * @return array{0: int, 1: int}
     */
    private function seedFixedDenominationProduct(bool $isCustomizable = true): array
    {
        $productId = 26;

        $this->app['db']->table('products')->insert([
            'id' => $productId,
            'user_id' => 1,
            'added_by' => 'admin',
            'name' => 'Fixed Denom Bamboo',
            'slug' => 'fixed-denom-bamboo',
            'product_type' => 'digital',
            'digital_product_type' => 'ready_product',
            'status' => 1,
            'request_status' => 1,
            'partner_approved' => true,
            'unit_price' => 10,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $supplierId = $this->app['db']->table('supplier_apis')->insertGetId([
            'name' => 'Bamboo',
            'driver' => 'bamboo',
            'base_url' => 'https://supplier.test',
            'credentials' => '{}',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $mappingId = $this->app['db']->table('supplier_product_mappings')->insertGetId([
            'product_id' => $productId,
            'supplier_api_id' => $supplierId,
            'supplier_product_id' => 'denom-sku',
            'cost_price' => 8,
            'is_active' => true,
            'is_customizable' => $isCustomizable,
            'is_direct_topup' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $denominationId = $this->app['db']->table('supplier_product_denominations')->insertGetId([
            'supplier_product_mapping_id' => $mappingId,
            'supplier_product_id' => 'denom-sku',
            'name' => '$10 USD',
            'type' => 'fixed',
            'face_value' => 10,
            'face_value_currency' => 'USD',
            'cost_price' => 8,
            'is_active' => true,
            'sort_order' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$productId, $denominationId];
    }

    private function seedDenominationPartnerPrice(int $productId, float $partnerPrice): void
    {
        $catalogItemId = (int) $this->app['db']->table('partner_catalog_items')
            ->where('product_id', $productId)
            ->value('id');

        $denominationId = (int) $this->app['db']->table('supplier_product_denominations')
            ->join('supplier_product_mappings', 'supplier_product_mappings.id', '=', 'supplier_product_denominations.supplier_product_mapping_id')
            ->where('supplier_product_mappings.product_id', $productId)
            ->value('supplier_product_denominations.id');

        $this->app['db']->table('partner_catalog_denomination_prices')->insert([
            'partner_catalog_item_id' => $catalogItemId,
            'supplier_product_denomination_id' => $denominationId,
            'partner_price' => $partnerPrice,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
