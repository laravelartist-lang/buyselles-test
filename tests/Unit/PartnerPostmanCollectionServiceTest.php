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

        $json = app(PartnerPostmanCollectionService::class)->generate();
        $collection = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        $folderNames = collect($collection['item'])->pluck('name')->all();
        $this->assertContains('6. Examples by product source', $folderNames);

        $variables = collect($collection['variable'])->keyBy('key');
        $this->assertSame('31', $variables->get('example_in_house_local_product_id')['value']);
        $this->assertSame('31', $variables->get('last_product_id')['value']);
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
