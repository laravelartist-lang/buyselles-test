<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ResellerApiKey;
use App\Services\Partner\GlobalPartnerCatalogQuery;
use App\Services\Partner\PartnerCatalogAssignmentService;
use App\Services\Partner\PartnerIdentityService;
use Tests\Concerns\ManagesTestDatabaseSchema;
use Tests\Concerns\SetsUpPartnerApiTestSchema;
use Tests\TestCase;

class GlobalPartnerCatalogTest extends TestCase
{
    use ManagesTestDatabaseSchema;
    use SetsUpPartnerApiTestSchema;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpPartnerApiSchema();
        $this->seedActivePartnerApiKey();
    }

    public function test_global_catalog_includes_in_house_and_supplier_mapped_products(): void
    {
        $supplierId = $this->seedSupplier();

        $inHouseId = $this->seedStorefrontProduct(name: 'In House Local', partnerApiOnly: false);
        $mappedId = $this->seedStorefrontProduct(name: 'Supplier Mapped', partnerApiOnly: false);

        $this->app['db']->table('supplier_product_mappings')->insert([
            'product_id' => $mappedId,
            'supplier_api_id' => $supplierId,
            'supplier_product_id' => 'mapped-sku',
            'is_active' => true,
            'is_direct_topup' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $query = app(GlobalPartnerCatalogQuery::class);
        $ids = $query->baseQuery()->pluck('id')->all();

        $this->assertContains($inHouseId, $ids);
        $this->assertContains($mappedId, $ids);
    }

    public function test_global_catalog_excludes_vendor_and_partner_only_products(): void
    {
        $vendorId = $this->app['db']->table('products')->insertGetId([
            'user_id' => 2,
            'added_by' => 'seller',
            'name' => 'Vendor Product',
            'slug' => 'vendor-product',
            'product_type' => 'digital',
            'digital_product_type' => 'ready_product',
            'status' => 1,
            'request_status' => 1,
            'partner_approved' => true,
            'partner_api_only' => false,
            'unit_price' => 10,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $partnerOnlyId = $this->seedStorefrontProduct(name: 'Partner Only Hidden', partnerApiOnly: true);

        $ids = app(GlobalPartnerCatalogQuery::class)->baseQuery()->pluck('id')->all();

        $this->assertNotContains($vendorId, $ids);
        $this->assertNotContains($partnerOnlyId, $ids);
    }

    public function test_assign_storefront_product_appears_in_partner_api_with_custom_price(): void
    {
        $productId = $this->seedStorefrontProduct(name: 'Storefront For Partner', unitPrice: 25);
        $this->seedDigitalCode($productId, 'GLOBAL-CODE-1');

        $key = ResellerApiKey::query()->firstOrFail();
        $catalog = app(PartnerIdentityService::class)->resolveOrCreateCatalog($key);

        app(PartnerCatalogAssignmentService::class)->assignExistingProduct(
            catalog: $catalog,
            productId: $productId,
            partnerPrice: 17.5,
        );

        $this->assertFalse((bool) Product::query()->find($productId)?->partner_api_only);

        $response = $this->getJson('/api/v1/partner/products', $this->partnerApiHeaders());

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.id', $productId);
        $response->assertJsonPath('data.0.pricing.unit_price', 17.5);
    }

    public function test_toggle_hides_product_from_partner_api_but_keeps_storefront_visible(): void
    {
        $productId = $this->seedStorefrontProduct(name: 'Toggle Product');
        $this->assignStorefrontProductToPartnerCatalog(productId: $productId, partnerPrice: 9.99);

        $key = ResellerApiKey::query()->firstOrFail();
        $catalog = app(PartnerIdentityService::class)->resolveOrCreateCatalog($key);

        app(PartnerCatalogAssignmentService::class)->toggleVisibility(
            catalog: $catalog,
            productId: $productId,
            isActive: false,
        );

        $this->getJson('/api/v1/partner/products', $this->partnerApiHeaders())
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->assertNotNull(Product::query()->find($productId));
    }

    private function seedSupplier(): int
    {
        return (int) $this->app['db']->table('supplier_apis')->insertGetId([
            'name' => 'Bamboo',
            'driver' => 'bamboo',
            'base_url' => 'https://bamboo.test',
            'credentials' => '{}',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function seedStorefrontProduct(
        string $name,
        float $unitPrice = 10,
        bool $partnerApiOnly = false,
    ): int {
        return (int) $this->app['db']->table('products')->insertGetId([
            'user_id' => 1,
            'added_by' => 'admin',
            'name' => $name,
            'slug' => str($name)->slug(),
            'product_type' => 'digital',
            'digital_product_type' => 'ready_product',
            'status' => 1,
            'request_status' => 1,
            'partner_approved' => true,
            'partner_api_only' => $partnerApiOnly,
            'unit_price' => $unitPrice,
            'purchase_price' => 5,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
