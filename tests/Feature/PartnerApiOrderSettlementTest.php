<?php

namespace Tests\Feature;

use App\Jobs\ReleasePartnerEscrowJob;
use App\Jobs\SupplierCodeFetchJob;
use App\Models\AdminWallet;
use App\Models\Order;
use App\Services\Supplier\SupplierFulfillmentFailureService;
use App\Services\Supplier\SupplierManager;
use Illuminate\Support\Facades\Bus;
use Mockery;
use Tests\Concerns\ManagesTestDatabaseSchema;
use Tests\Concerns\SetsUpPartnerApiTestSchema;
use Tests\TestCase;

class PartnerApiOrderSettlementTest extends TestCase
{
    use ManagesTestDatabaseSchema;
    use SetsUpPartnerApiTestSchema;

    protected function setUp(): void
    {
        parent::setUp();

        Bus::fake([SupplierCodeFetchJob::class, ReleasePartnerEscrowJob::class]);

        $this->setUpPartnerApiSchema();
        $this->seedActivePartnerApiKey(sellerId: 1, walletBalance: 500);
    }

    public function test_quote_includes_service_fee_and_settlement_breakdown_when_enabled(): void
    {
        $this->enableCustomerServiceFee(percent: 2);

        $this->seedProduct(id: 30, name: 'Fee Product');
        $this->seedSupplierMapping(productId: 30, driver: 'bamboo', costPrice: 8.125);
        $this->assignProductToPartnerCatalog(productId: 30, partnerPrice: 10.375);

        $response = $this->postJson('/api/v1/partner/products/30/quote', [
            'quantity' => 1,
        ], $this->partnerApiHeaders());

        $response->assertOk();
        $response->assertJsonPath('data.catalog_subtotal', 10.375);
        $response->assertJsonPath('data.service_fee', 0.21);
        $response->assertJsonPath('data.total', 10.585);
        $response->assertJsonPath('data.supplier_cost_total', 8.125);
        $response->assertJsonPath('data.admin_margin', 2.25);
        $response->assertJsonPath('data.supplier.driver', 'bamboo');
    }

    public function test_create_order_applies_settlement_and_credits_admin_wallet(): void
    {
        $this->enableCustomerServiceFee(percent: 2);

        $this->seedProduct(id: 31, name: 'Settlement Product');
        $this->seedSupplierMapping(productId: 31, driver: 'golf_api', costPrice: 8.125);
        $this->assignProductToPartnerCatalog(productId: 31, partnerPrice: 10.375);

        $manager = Mockery::mock(SupplierManager::class);
        $manager->shouldReceive('getAvailableStockForMapping')->andReturn(5);
        $this->app->instance(SupplierManager::class, $manager);

        $response = $this->postJson('/api/v1/partner/orders', [
            'product_id' => 31,
            'quantity' => 1,
            'expected_total' => 10.585,
        ], $this->partnerApiHeaders());

        $response->assertCreated();
        $response->assertJsonPath('data.total_cost', 10.585);
        $response->assertJsonPath('data.pricing.admin_margin', 2.25);
        $response->assertJsonPath('data.pricing.service_fee', 0.21);
        $response->assertJsonPath('data.supplier.driver', 'golf_api');

        $order = Order::query()->find($response->json('data.order_id'));
        $this->assertNotNull($order);
        $this->assertSame(10.585, (float) $order->order_amount);
        $this->assertSame(2.25, (float) $order->admin_commission);
        $this->assertSame(0.21, (float) $order->customer_service_fee);
        $this->assertSame('admin', $order->seller_is);

        $adminWallet = AdminWallet::query()->where('admin_id', 1)->first();
        $this->assertNotNull($adminWallet);
        $this->assertSame(2.46, (float) $adminWallet->commission_earned);

        $this->assertDatabaseHas('order_transactions', [
            'order_id' => $order->id,
            'payment_method' => 'partner_wallet',
            'status' => 'disburse',
        ]);

        Bus::assertNotDispatched(ReleasePartnerEscrowJob::class);
        Bus::assertDispatched(SupplierCodeFetchJob::class);
    }

    public function test_local_code_order_has_zero_supplier_cost_and_full_admin_margin(): void
    {
        $this->seedProduct(id: 32, name: 'Local Product');
        $this->assignProductToPartnerCatalog(productId: 32, partnerPrice: 10);
        $this->seedDigitalCode(productId: 32, plainCode: 'LOCAL-SETTLE-CODE');

        $response = $this->postJson('/api/v1/partner/orders', [
            'product_id' => 32,
            'quantity' => 1,
        ], $this->partnerApiHeaders());

        $response->assertCreated();
        $response->assertJsonPath('data.total_cost', 10);
        $response->assertJsonPath('data.pricing.supplier_cost', 0);
        $response->assertJsonPath('data.pricing.admin_margin', 10);
        $response->assertJsonPath('data.supplier', null);

        $order = Order::query()->find($response->json('data.order_id'));
        $this->assertSame(10.0, (float) $order->admin_commission);
    }

    public function test_supplier_failure_refunds_partner_wallet_and_reverses_admin_settlement(): void
    {
        $this->enableCustomerServiceFee(percent: 2);

        $this->seedProduct(id: 33, name: 'Refund Product');
        $this->seedSupplierMapping(productId: 33, driver: 'bamboo', costPrice: 8);
        $this->assignProductToPartnerCatalog(productId: 33, partnerPrice: 10);

        $manager = Mockery::mock(SupplierManager::class);
        $manager->shouldReceive('getAvailableStockForMapping')->andReturn(5);
        $this->app->instance(SupplierManager::class, $manager);

        $createResponse = $this->postJson('/api/v1/partner/orders', [
            'product_id' => 33,
            'quantity' => 1,
        ], $this->partnerApiHeaders());

        $createResponse->assertCreated();
        $orderId = (int) $createResponse->json('data.order_id');
        $order = Order::query()->findOrFail($orderId);

        app(SupplierFulfillmentFailureService::class)->markOrderFailed($order, 'Supplier API timeout');

        $order->refresh();
        $this->assertSame('failed', $order->order_status);
        $this->assertSame('unpaid', $order->payment_status);

        $this->assertSame(500.0, (float) $this->app['db']->table('users')->where('id', $order->customer_id)->value('wallet_balance'));

        $adminWallet = AdminWallet::query()->where('admin_id', 1)->first();
        $this->assertSame(0.0, (float) $adminWallet->commission_earned);

        $this->assertDatabaseHas('order_transactions', [
            'order_id' => $orderId,
            'status' => 'refunded',
        ]);
    }

    private function enableCustomerServiceFee(float $percent): void
    {
        $this->app['db']->table('business_settings')->insert([
            ['type' => 'customer_service_fee_status', 'value' => '1'],
            ['type' => 'customer_service_fee', 'value' => (string) $percent],
            ['type' => 'customer_service_fee_type', 'value' => 'percent'],
        ]);
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

    private function seedSupplierMapping(int $productId, string $driver, float $costPrice = 8): void
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
            'cost_price' => $costPrice,
            'is_active' => true,
            'is_direct_topup' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
