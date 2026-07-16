<?php

namespace Tests\Feature;

use App\Jobs\SyncDenominationsJob;
use App\Models\Product;
use App\Models\ResellerApiKey;
use App\Models\SupplierProductMapping;
use App\Services\Partner\PartnerCatalogAssignmentService;
use App\Services\Partner\PartnerIdentityService;
use Illuminate\Support\Facades\Bus;
use Tests\Concerns\ManagesTestDatabaseSchema;
use Tests\Concerns\SetsUpPartnerApiTestSchema;
use Tests\TestCase;

class PartnerApiCatalogStorefrontIsolationTest extends TestCase
{
    use ManagesTestDatabaseSchema;
    use SetsUpPartnerApiTestSchema;

    protected function setUp(): void
    {
        parent::setUp();

        Bus::fake([SyncDenominationsJob::class]);

        $this->setUpPartnerApiSchema();
        $this->seedActivePartnerApiKey();
    }

    public function test_assigning_supplier_sku_creates_partner_only_product_hidden_from_storefront(): void
    {
        $supplierId = $this->app['db']->table('supplier_apis')->insertGetId([
            'name' => 'Bamboo',
            'driver' => 'bamboo',
            'base_url' => 'https://bamboo.test',
            'credentials' => '{}',
            'is_active' => true,
            'supports_direct_top_up' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $key = ResellerApiKey::query()->firstOrFail();
        $catalog = app(PartnerIdentityService::class)->resolveOrCreateCatalog($key);

        $item = app(PartnerCatalogAssignmentService::class)->assignFromSupplierCatalog($catalog, [
            'supplier_api_id' => $supplierId,
            'supplier_product_id' => 'bamboo-sku-99',
            'supplier_product_name' => 'Steam Wallet 10',
            'cost_price' => 8.5,
            'cost_currency' => 'USD',
            'partner_price' => 11.25,
            'currency' => 'USD',
            'is_direct_topup' => false,
        ]);

        $this->assertTrue((bool) Product::withoutGlobalScope(Product::STOREFRONT_SCOPE)->find($item->product_id)?->partner_api_only);
        $this->assertNull(Product::query()->find($item->product_id), 'Partner API products must not appear in storefront queries');
        $this->assertSame(0, Product::query()->where('name', 'Steam Wallet 10')->count());
    }

    public function test_assignment_does_not_reuse_storefront_supplier_mapping(): void
    {
        $supplierId = $this->app['db']->table('supplier_apis')->insertGetId([
            'name' => 'Bamboo',
            'driver' => 'bamboo',
            'base_url' => 'https://bamboo.test',
            'credentials' => '{}',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $storefrontProductId = $this->app['db']->table('products')->insertGetId([
            'user_id' => 1,
            'added_by' => 'admin',
            'name' => 'Storefront Steam 10',
            'slug' => 'storefront-steam-10',
            'product_type' => 'digital',
            'digital_product_type' => 'ready_after_sell',
            'status' => 1,
            'request_status' => 1,
            'partner_approved' => true,
            'partner_api_only' => false,
            'unit_price' => 10,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->app['db']->table('supplier_product_mappings')->insert([
            'product_id' => $storefrontProductId,
            'supplier_api_id' => $supplierId,
            'supplier_product_id' => 'shared-sku',
            'is_active' => true,
            'is_direct_topup' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $key = ResellerApiKey::query()->firstOrFail();
        $catalog = app(PartnerIdentityService::class)->resolveOrCreateCatalog($key);

        $item = app(PartnerCatalogAssignmentService::class)->assignFromSupplierCatalog($catalog, [
            'supplier_api_id' => $supplierId,
            'supplier_product_id' => 'shared-sku',
            'supplier_product_name' => 'Partner Steam 10',
            'cost_price' => 8,
            'partner_price' => 12,
            'currency' => 'USD',
        ]);

        $this->assertNotSame($storefrontProductId, $item->product_id);
        $this->assertTrue((bool) Product::withoutGlobalScope(Product::STOREFRONT_SCOPE)->find($item->product_id)?->partner_api_only);
        $this->assertNotNull(Product::query()->find($storefrontProductId));
        $this->assertNull(Product::query()->find($item->product_id));
    }

    public function test_partner_catalog_mappings_are_excluded_from_admin_product_mappings_list(): void
    {
        $supplierId = $this->app['db']->table('supplier_apis')->insertGetId([
            'name' => 'Bamboo',
            'driver' => 'bamboo',
            'base_url' => 'https://bamboo.test',
            'credentials' => '{}',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $storefrontProductId = $this->app['db']->table('products')->insertGetId([
            'user_id' => 1,
            'added_by' => 'admin',
            'name' => 'Storefront Mapping Product',
            'slug' => 'storefront-mapping-product',
            'product_type' => 'digital',
            'digital_product_type' => 'ready_after_sell',
            'status' => 1,
            'request_status' => 1,
            'partner_api_only' => false,
            'unit_price' => 10,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $storefrontMappingId = $this->app['db']->table('supplier_product_mappings')->insertGetId([
            'product_id' => $storefrontProductId,
            'supplier_api_id' => $supplierId,
            'supplier_product_id' => 'storefront-sku',
            'is_active' => true,
            'is_direct_topup' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $key = ResellerApiKey::query()->firstOrFail();
        $catalog = app(PartnerIdentityService::class)->resolveOrCreateCatalog($key);

        $item = app(PartnerCatalogAssignmentService::class)->assignFromSupplierCatalog($catalog, [
            'supplier_api_id' => $supplierId,
            'supplier_product_id' => 'partner-only-sku',
            'supplier_product_name' => 'Partner Only Mapping Product',
            'cost_price' => 8,
            'partner_price' => 12,
            'currency' => 'USD',
        ]);

        $partnerMappingId = SupplierProductMapping::query()
            ->where('product_id', $item->product_id)
            ->value('id');

        $this->assertNotNull($partnerMappingId);

        $listedIds = SupplierProductMapping::query()
            ->storefrontOnly()
            ->pluck('id')
            ->all();

        $this->assertContains($storefrontMappingId, $listedIds);
        $this->assertNotContains($partnerMappingId, $listedIds);
    }
}
