<?php

namespace Tests\Unit;

use App\Services\Partner\PartnerApiDocumentationExamplesService;
use Tests\Concerns\ManagesTestDatabaseSchema;
use Tests\Concerns\SetsUpPartnerApiTestSchema;
use Tests\TestCase;

class PartnerApiDocumentationExamplesServiceTest extends TestCase
{
    use ManagesTestDatabaseSchema;
    use SetsUpPartnerApiTestSchema;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpPartnerApiSchema();
        $this->seedActivePartnerApiKey(walletBalance: 99999.99);
    }

    public function test_build_returns_sanitized_examples_with_live_response_shapes(): void
    {
        $this->app['db']->table('products')->insert([
            'id' => 30,
            'user_id' => 1,
            'added_by' => 'admin',
            'name' => 'Secret Real Product Name',
            'slug' => 'secret-real-product',
            'product_type' => 'digital',
            'digital_product_type' => 'ready_product',
            'status' => 1,
            'request_status' => 1,
            'partner_approved' => true,
            'unit_price' => 99.99,
            'purchase_price' => 88.88,
            'category_id' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->assignProductToPartnerCatalog(productId: 30, partnerPrice: 12.5);

        $examples = app(PartnerApiDocumentationExamplesService::class)->build();
        $encoded = json_encode($examples);

        $this->assertSame(14, $examples['sample_product_id']);
        $this->assertSame(100053, $examples['sample_order_id']);
        $this->assertArrayHasKey('data', $examples['products_list']);
        $this->assertArrayHasKey('meta', $examples['products_list']);
        $this->assertArrayHasKey('wallet_source', $examples['balance']['data']);
        $this->assertSame(1250.00, $examples['balance']['data']['balance']);
        $this->assertSame('My Integration Key', $examples['balance']['data']['key_name']);
        $this->assertSame('Example Digital Product', $examples['catalog_field_sample']['name']);
        $this->assertSame('XXXX-1234-YYYY-5678', $examples['create_order_fulfilled']['data']['codes'][0]['code']);
        $this->assertStringNotContainsString('Secret Real Product Name', (string) $encoded);
        $this->assertStringNotContainsString('99999.99', (string) $encoded);
        $this->assertStringNotContainsString('secret-real-product', (string) $encoded);
    }

    public function test_format_json_returns_pretty_printed_json(): void
    {
        $formatted = app(PartnerApiDocumentationExamplesService::class)->formatJson(['ok' => true]);

        $this->assertStringContainsString('"ok": true', $formatted);
        $this->assertStringContainsString("\n", $formatted);
    }
}
