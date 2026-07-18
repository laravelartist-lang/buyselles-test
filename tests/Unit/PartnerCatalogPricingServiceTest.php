<?php

namespace Tests\Unit;

use App\Models\PartnerCatalogItem;
use App\Services\Partner\PartnerCatalogPricingService;
use InvalidArgumentException;
use Tests\Concerns\ManagesTestDatabaseSchema;
use Tests\Concerns\SetsUpPartnerApiTestSchema;
use Tests\TestCase;

class PartnerCatalogPricingServiceTest extends TestCase
{
    use ManagesTestDatabaseSchema;
    use SetsUpPartnerApiTestSchema;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpPartnerApiSchema();
        $this->seedActivePartnerApiKey();
    }

    public function test_quote_uses_fixed_partner_price_when_fixed_denominations_exist_but_selection_not_required(): void
    {
        $productId = 55;

        $this->app['db']->table('products')->insert([
            'id' => $productId,
            'user_id' => 1,
            'added_by' => 'admin',
            'name' => 'Fixed With Synced Denoms',
            'slug' => 'fixed-with-synced-denoms',
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
            'supplier_product_id' => 'bamboo-sku-55',
            'cost_price' => 8,
            'is_active' => true,
            'is_customizable' => false,
            'is_direct_topup' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->app['db']->table('supplier_product_denominations')->insert([
            'supplier_product_mapping_id' => $mappingId,
            'supplier_product_id' => 'bamboo-sku-55-10',
            'name' => '$10 USD',
            'type' => 'fixed',
            'face_value' => 10,
            'face_value_currency' => 'USD',
            'is_active' => true,
            'sort_order' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $catalogItemId = $this->assignProductToPartnerCatalog(productId: $productId, partnerPrice: 12.5);
        $catalogItem = PartnerCatalogItem::query()->findOrFail($catalogItemId);

        $quote = app(PartnerCatalogPricingService::class)->quote($catalogItem, quantity: 2);

        $this->assertSame('fixed', $quote['price_type']);
        $this->assertSame(12.5, $quote['unit_price']);
        $this->assertSame(25.0, $quote['subtotal']);
        $this->assertNull($quote['denomination_id']);
    }

    public function test_quote_requires_denomination_when_mapping_is_customizable(): void
    {
        $productId = 56;

        $this->app['db']->table('products')->insert([
            'id' => $productId,
            'user_id' => 1,
            'added_by' => 'admin',
            'name' => 'Customizable Product',
            'slug' => 'customizable-product',
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
            'supplier_product_id' => 'customizable-sku',
            'cost_price' => 8,
            'is_active' => true,
            'is_customizable' => true,
            'is_direct_topup' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->app['db']->table('supplier_product_denominations')->insert([
            'supplier_product_mapping_id' => $mappingId,
            'supplier_product_id' => 'customizable-sku-10',
            'name' => '$10 USD',
            'type' => 'fixed',
            'face_value' => 10,
            'face_value_currency' => 'USD',
            'is_active' => true,
            'sort_order' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $catalogItemId = $this->assignProductToPartnerCatalog(productId: $productId, partnerPrice: 12.5);
        $catalogItem = PartnerCatalogItem::query()->findOrFail($catalogItemId);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('A supplier denomination is required for this product.');

        app(PartnerCatalogPricingService::class)->quote($catalogItem);
    }
}
