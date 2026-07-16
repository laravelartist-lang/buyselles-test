<?php

namespace Tests\Feature;

use Tests\Concerns\ManagesTestDatabaseSchema;
use Tests\Concerns\SetsUpPartnerApiTestSchema;
use Tests\TestCase;

class PartnerApiCuratedCatalogTest extends TestCase
{
    use ManagesTestDatabaseSchema;
    use SetsUpPartnerApiTestSchema;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpPartnerApiSchema();
        $this->seedActivePartnerApiKey(walletBalance: 1000);
    }

    public function test_unassigned_products_are_hidden_from_partner_catalog(): void
    {
        $this->seedProduct(id: 1, name: 'Hidden Product');

        $response = $this->getJson('/api/v1/partner/products', $this->partnerApiHeaders());

        $response->assertOk();
        $response->assertJsonCount(0, 'data');
    }

    public function test_assigned_product_uses_exact_partner_price(): void
    {
        $this->seedProduct(id: 2, name: 'Assigned Product', unitPrice: 99);
        $this->assignProductToPartnerCatalog(productId: 2, partnerPrice: 15.25);

        $response = $this->getJson('/api/v1/partner/products', $this->partnerApiHeaders());

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.id', 2);
        $response->assertJsonPath('data.0.pricing.type', 'fixed');
        $response->assertJsonPath('data.0.pricing.unit_price', 15.25);
        $response->assertJsonMissingPath('data.0.purchase_price');
    }

    public function test_order_charges_partner_catalog_price_not_product_unit_price(): void
    {
        $this->seedProduct(id: 3, name: 'Priced Product', unitPrice: 50);
        $this->assignProductToPartnerCatalog(productId: 3, partnerPrice: 11);
        $this->seedDigitalCode(productId: 3, plainCode: 'PARTNER-CODE-1');
        $this->seedDigitalCode(productId: 3, plainCode: 'PARTNER-CODE-2');

        $response = $this->postJson('/api/v1/partner/orders', [
            'product_id' => 3,
            'quantity' => 2,
        ], $this->partnerApiHeaders());

        $response->assertCreated();
        $response->assertJsonPath('data.total_cost', 22);
        $response->assertJsonPath('data.pricing.unit_price', 11);
        $response->assertJsonPath('data.status', 'fulfilled');
    }

    public function test_order_rejects_product_not_in_partner_catalog(): void
    {
        $this->seedProduct(id: 4, name: 'Not Allowed');
        $this->seedDigitalCode(productId: 4, plainCode: 'X');

        $response = $this->postJson('/api/v1/partner/orders', [
            'product_id' => 4,
            'quantity' => 1,
        ], $this->partnerApiHeaders());

        $response->assertNotFound();
        $response->assertJsonPath('error', 'Product is not available in this partner catalog.');
    }

    private function seedProduct(int $id, string $name, float $unitPrice = 10): void
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
            'unit_price' => $unitPrice,
            'purchase_price' => 5,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
