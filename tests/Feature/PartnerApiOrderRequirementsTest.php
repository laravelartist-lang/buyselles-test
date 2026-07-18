<?php

namespace Tests\Feature;

use App\Jobs\SupplierCodeFetchJob;
use App\Services\Supplier\SupplierManager;
use Illuminate\Support\Facades\Bus;
use Mockery;
use Tests\Concerns\ManagesTestDatabaseSchema;
use Tests\Concerns\SetsUpPartnerApiTestSchema;
use Tests\TestCase;

class PartnerApiOrderRequirementsTest extends TestCase
{
    use ManagesTestDatabaseSchema;
    use SetsUpPartnerApiTestSchema;

    protected function setUp(): void
    {
        parent::setUp();

        Bus::fake([SupplierCodeFetchJob::class]);

        $this->setUpPartnerApiSchema();
        $this->seedActivePartnerApiKey(sellerId: 1, walletBalance: 500);
    }

    public function test_bamboo_product_detail_includes_order_requirements_without_direct_topup_account(): void
    {
        $this->seedProduct(id: 40, name: 'Bamboo Product');
        $this->seedSupplierMapping(productId: 40, driver: 'bamboo');
        $this->assignProductToPartnerCatalog(productId: 40, partnerPrice: 10);

        $response = $this->getJson('/api/v1/partner/products/40', $this->partnerApiHeaders());

        $response->assertOk();
        $response->assertJsonPath('data.fulfillment_type', 'supplier_codes');
        $response->assertJsonPath('data.order_requirements.fulfillment_type', 'supplier_codes');
        $response->assertJsonPath('data.order_requirements.pricing_type', 'fixed');
        $response->assertJsonPath('data.order_requirements.required', ['product_id', 'quantity']);
        $this->assertNotContains(
            'direct_topup_account_id',
            $response->json('data.order_requirements.required'),
        );
    }

    public function test_bamboo_order_succeeds_with_product_id_and_quantity_only(): void
    {
        $this->seedProduct(id: 41, name: 'Bamboo Order');
        $this->seedSupplierMapping(productId: 41, driver: 'bamboo', costPrice: 7);
        $this->assignProductToPartnerCatalog(productId: 41, partnerPrice: 10);

        $manager = Mockery::mock(SupplierManager::class);
        $manager->shouldReceive('getAvailableStockForMapping')->andReturn(5);
        $this->app->instance(SupplierManager::class, $manager);

        $response = $this->postJson('/api/v1/partner/orders', [
            'product_id' => 41,
            'quantity' => 1,
        ], $this->partnerApiHeaders());

        $response->assertCreated();
        $response->assertJsonPath('data.status', 'pending_fulfillment');
    }

    public function test_bamboo_order_rejects_non_empty_direct_topup_account_id(): void
    {
        $this->seedProduct(id: 42, name: 'Bamboo Reject Topup Param');
        $this->seedSupplierMapping(productId: 42, driver: 'bamboo');
        $this->assignProductToPartnerCatalog(productId: 42, partnerPrice: 10);

        $response = $this->postJson('/api/v1/partner/orders', [
            'product_id' => 42,
            'quantity' => 1,
            'direct_topup_account_id' => 'player123',
        ], $this->partnerApiHeaders());

        $response->assertUnprocessable();
        $response->assertJsonPath(
            'errors.direct_topup_account_id.0',
            'direct_topup_account_id is not applicable for this product.',
        );
    }

    public function test_direct_topup_order_requires_account_id(): void
    {
        $this->seedProduct(id: 43, name: 'Jawaker Topup');
        $this->seedDirectTopupMapping(productId: 43);
        $this->assignProductToPartnerCatalog(productId: 43, partnerPrice: 10);

        $response = $this->postJson('/api/v1/partner/orders', [
            'product_id' => 43,
            'quantity' => 1,
        ], $this->partnerApiHeaders());

        $response->assertUnprocessable();
        $response->assertJsonStructure(['errors' => ['direct_topup_account_id']]);
    }

    public function test_denomination_order_requires_supplier_denomination_id_when_customizable(): void
    {
        [$productId] = $this->seedFixedDenominationProduct(isCustomizable: true);
        $this->assignProductToPartnerCatalog(productId: $productId, partnerPrice: 1);
        $this->seedDenominationPartnerPrice($productId, partnerPrice: 10.5);

        $response = $this->postJson('/api/v1/partner/orders', [
            'product_id' => $productId,
            'quantity' => 1,
        ], $this->partnerApiHeaders());

        $response->assertUnprocessable();
        $response->assertJsonPath(
            'errors.supplier_denomination_id.0',
            'A supplier denomination is required for this product.',
        );
    }

    public function test_fixed_synced_denominations_allow_simple_order_without_denomination_id(): void
    {
        Bus::fake([SupplierCodeFetchJob::class]);

        [$productId] = $this->seedFixedDenominationProduct(isCustomizable: false);
        $this->assignProductToPartnerCatalog(productId: $productId, partnerPrice: 10);

        $manager = Mockery::mock(SupplierManager::class);
        $manager->shouldReceive('getAvailableStockForMapping')->andReturn(5);
        $this->app->instance(SupplierManager::class, $manager);

        $detailResponse = $this->getJson('/api/v1/partner/products/'.$productId, $this->partnerApiHeaders());
        $detailResponse->assertOk();
        $detailResponse->assertJsonPath('data.pricing.type', 'fixed');
        $detailResponse->assertJsonPath('data.order_requirements.pricing_type', 'fixed');
        $detailResponse->assertJsonCount(1, 'data.pricing.denominations');
        $this->assertContains('supplier_denomination_id', $detailResponse->json('data.order_requirements.optional'));

        $response = $this->postJson('/api/v1/partner/orders', [
            'product_id' => $productId,
            'quantity' => 1,
        ], $this->partnerApiHeaders());

        $response->assertCreated();
        $response->assertJsonPath('data.total_cost', 10);
    }

    public function test_empty_denomination_query_param_is_normalized_on_fixed_price_product(): void
    {
        $this->seedProduct(id: 44, name: 'Fixed Bamboo');
        $this->seedSupplierMapping(productId: 44, driver: 'bamboo', costPrice: 7);
        $this->assignProductToPartnerCatalog(productId: 44, partnerPrice: 10);

        $manager = Mockery::mock(SupplierManager::class);
        $manager->shouldReceive('getAvailableStockForMapping')->andReturn(5);
        $this->app->instance(SupplierManager::class, $manager);

        $response = $this->call(
            'POST',
            '/api/v1/partner/orders?product_id=44&quantity=1&supplier_denomination_id=',
            [],
            [],
            [],
            $this->transformHeadersToServerVars($this->partnerApiHeaders()),
        );

        $this->assertSame(201, $response->getStatusCode());
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

    private function seedSupplierMapping(int $productId, string $driver, float $costPrice = 0): void
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
            'direct_topup_account_label' => 'Player ID',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function seedFixedDenominationProduct(bool $isCustomizable = false): array
    {
        $productId = 45;

        $this->app['db']->table('products')->insert([
            'id' => $productId,
            'user_id' => 1,
            'added_by' => 'admin',
            'name' => 'Fixed Denom Product',
            'slug' => 'fixed-denom-product',
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
            'supplier_product_id' => 'fixed-denom-sku',
            'cost_price' => 8,
            'is_active' => true,
            'is_customizable' => $isCustomizable,
            'is_direct_topup' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $denominationId = $this->app['db']->table('supplier_product_denominations')->insertGetId([
            'supplier_product_mapping_id' => $mappingId,
            'supplier_product_id' => 'fixed-denom-sku',
            'name' => '$10 USD',
            'type' => 'fixed',
            'face_value' => 10,
            'face_value_currency' => 'USD',
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
