<?php

namespace Tests\Unit;

use App\Services\Partner\PartnerPostmanCollectionService;
use Tests\Concerns\ManagesTestDatabaseSchema;
use Tests\Concerns\SetsUpPartnerApiTestSchema;
use Tests\TestCase;

class PartnerPostmanCollectionServiceTest extends TestCase
{
    use ManagesTestDatabaseSchema;
    use SetsUpPartnerApiTestSchema;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpPartnerApiSchema();
    }

    public function test_generated_collection_has_no_hardcoded_secrets(): void
    {
        $this->seedProduct(id: 30, addedBy: 'admin', partnerApproved: true);

        $json = app(PartnerPostmanCollectionService::class)->generate();
        $collection = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame('Buyselles Partner API v1', $collection['info']['name']);

        $variables = collect($collection['variable'])->keyBy('key');

        $this->assertSame('', $variables->get('api_key')['value']);
        $this->assertSame('', $variables->get('api_secret')['value']);
        $this->assertStringNotContainsString('rslr_2FHJGiQCWS5jVmwrqswbZ85jL5wGlTGa8zIqKkha', $json);
        $this->assertStringNotContainsString('RtTp73iHnv1Pd4h5LupVpcRlPKHTfi86GHhClxsYP5IcrNpn', $json);
    }

    public function test_generated_collection_includes_examples_folder_and_sample_product_variables(): void
    {
        $this->seedProduct(id: 31, addedBy: 'admin', partnerApproved: true);
        $this->assignStorefrontProductToPartnerCatalog(productId: 31);

        $json = app(PartnerPostmanCollectionService::class)->generate();
        $collection = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        $folderNames = collect($collection['item'])->pluck('name')->all();
        $this->assertContains('6. Examples by product source', $folderNames);

        $variables = collect($collection['variable'])->keyBy('key');
        $this->assertSame('31', $variables->get('product_id')['value']);
        $this->assertSame('31', $variables->get('example_in_house_local_product_id')['value']);
        $this->assertArrayHasKey('supplier_denomination_id', $variables->all());
        $this->assertArrayHasKey('expected_total', $variables->all());
        $this->assertArrayHasKey('direct_topup_account_id', $variables->all());
        $this->assertSame('1', $variables->get('quantity')['value']);

        $examplesFolder = collect($collection['item'])->firstWhere('name', '6. Examples by product source');
        $exampleNames = collect($examplesFolder['item'])->pluck('name')->all();
        $this->assertContains('Quote Product — supplier-mapped (simple)', $exampleNames);
        $this->assertContains('Quote Product — denomination example', $exampleNames);
        $this->assertContains('Create Order — denomination example', $exampleNames);

        $this->assertStringContainsString(
            'required only when pricing.type is denominations',
            $variables->get('supplier_denomination_id')['description'],
        );

        $orderExample = collect($examplesFolder['item'])->firstWhere('name', 'Create Order — in-house local product');
        $this->assertArrayHasKey('query', $orderExample['request']['url']);
        $this->assertArrayNotHasKey('body', $orderExample['request']);
    }

    private function seedProduct(int $id, string $addedBy, bool $partnerApproved): void
    {
        $this->app['db']->table('products')->insert([
            'id' => $id,
            'user_id' => 1,
            'added_by' => $addedBy,
            'name' => 'Postman Sample',
            'slug' => 'postman-sample-'.$id,
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
}
