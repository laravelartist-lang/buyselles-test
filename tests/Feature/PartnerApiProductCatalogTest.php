<?php

namespace Tests\Feature;

use Tests\Concerns\ManagesTestDatabaseSchema;
use Tests\Concerns\SetsUpPartnerApiTestSchema;
use Tests\TestCase;

class PartnerApiProductCatalogTest extends TestCase
{
    use ManagesTestDatabaseSchema;
    use SetsUpPartnerApiTestSchema;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpPartnerApiSchema();
        $this->seedActivePartnerApiKey();
    }

    public function test_list_products_returns_in_house_only_by_default(): void
    {
        $this->seedProduct(id: 1, addedBy: 'admin', partnerApproved: true, name: 'In House Product');
        $this->seedProduct(id: 2, addedBy: 'seller', partnerApproved: true, name: 'Vendor Product');

        $response = $this->getJson('/api/v1/partner/products', $this->partnerApiHeaders());

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.id', 1);
        $response->assertJsonPath('data.0.seller_type', 'in_house');
    }

    public function test_list_products_include_vendor_param_includes_vendor_products(): void
    {
        $this->seedProduct(id: 1, addedBy: 'admin', partnerApproved: true, name: 'In House Product');
        $this->seedProduct(id: 2, addedBy: 'seller', partnerApproved: true, name: 'Vendor Product');

        $response = $this->getJson('/api/v1/partner/products?include_vendor=1', $this->partnerApiHeaders());

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
    }

    public function test_direct_topup_products_are_excluded_from_catalog(): void
    {
        $this->seedProduct(id: 3, addedBy: 'admin', partnerApproved: true, name: 'Direct Topup Product');
        $this->seedDirectTopupMapping(productId: 3);

        $response = $this->getJson('/api/v1/partner/products', $this->partnerApiHeaders());

        $response->assertOk();
        $response->assertJsonCount(0, 'data');
    }

    public function test_fulfillment_type_filter_returns_supplier_mapped_products(): void
    {
        $this->seedProduct(id: 4, addedBy: 'admin', partnerApproved: true, name: 'Local Product');
        $this->seedProduct(id: 5, addedBy: 'admin', partnerApproved: true, name: 'Supplier Product');
        $this->seedSupplierMapping(productId: 5, driver: 'bamboo');

        $response = $this->getJson('/api/v1/partner/products?fulfillment_type=supplier_codes', $this->partnerApiHeaders());

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.id', 5);
        $response->assertJsonPath('data.0.fulfillment_type', 'supplier_codes');
        $response->assertJsonPath('data.0.supplier', 'bamboo');
    }

    public function test_vendor_product_detail_returns_404_without_include_vendor(): void
    {
        $this->seedProduct(id: 6, addedBy: 'seller', partnerApproved: true, name: 'Vendor Only');

        $response = $this->getJson('/api/v1/partner/products/6', $this->partnerApiHeaders());

        $response->assertNotFound();
    }

    public function test_vendor_product_detail_is_available_with_include_vendor(): void
    {
        $this->seedProduct(id: 7, addedBy: 'seller', partnerApproved: true, name: 'Vendor Visible');

        $response = $this->getJson('/api/v1/partner/products/7?include_vendor=1', $this->partnerApiHeaders());

        $response->assertOk();
        $response->assertJsonPath('data.seller_type', 'vendor');
    }

    private function seedProduct(int $id, string $addedBy, bool $partnerApproved, string $name): void
    {
        $this->app['db']->table('products')->insert([
            'id' => $id,
            'user_id' => $addedBy === 'seller' ? 99 : 1,
            'added_by' => $addedBy,
            'name' => $name,
            'slug' => 'product-'.$id,
            'product_type' => 'digital',
            'digital_product_type' => 'ready_product',
            'status' => 1,
            'request_status' => 1,
            'partner_approved' => $partnerApproved,
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
