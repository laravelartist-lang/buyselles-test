<?php

namespace Tests\Feature;

use App\Models\PartnerCatalogItem;
use App\Models\Product;
use App\Services\Partner\PartnerOrderRequirementsResolver;
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

        $this->setUpPartnerApiSchema();
    }

    public function test_local_code_product_requires_quantity_only(): void
    {
        $this->app['db']->table('products')->insert([
            'id' => 50,
            'user_id' => 1,
            'added_by' => 'admin',
            'name' => 'Local Req Product',
            'slug' => 'local-req-product',
            'product_type' => 'digital',
            'digital_product_type' => 'ready_product',
            'status' => 1,
            'request_status' => 1,
            'partner_approved' => true,
            'unit_price' => 10,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $catalogItemId = $this->assignProductToPartnerCatalog(productId: 50, partnerPrice: 10);
        $catalogItem = PartnerCatalogItem::query()->findOrFail($catalogItemId);
        $catalogItem->setRelation('product', Product::withoutGlobalScopes()->findOrFail(50));

        $requirements = app(PartnerOrderRequirementsResolver::class)->resolve($catalogItem);

        $this->assertSame('local_codes', $requirements['fulfillment_type']);
        $this->assertSame('fixed', $requirements['pricing_type']);
        $this->assertContains('product_id', $requirements['required']);
        $this->assertContains('quantity', $requirements['required']);
        $this->assertNotContains('direct_topup_account_id', $requirements['required']);
    }
}
