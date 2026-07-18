<?php

namespace Tests\Feature;

use App\Jobs\ReleasePartnerEscrowJob;
use App\Jobs\SupplierCodeFetchJob;
use App\Models\PartnerCatalogItem;
use App\Services\Supplier\SupplierManager;
use Illuminate\Support\Facades\Bus;
use Mockery;
use Tests\Concerns\ManagesTestDatabaseSchema;
use Tests\Concerns\SetsUpPartnerApiTestSchema;
use Tests\TestCase;

class PartnerApiVariableDenominationTest extends TestCase
{
    use ManagesTestDatabaseSchema;
    use SetsUpPartnerApiTestSchema;

    protected function setUp(): void
    {
        parent::setUp();

        Bus::fake([SupplierCodeFetchJob::class, ReleasePartnerEscrowJob::class]);

        $this->setUpPartnerApiSchema();
        $this->seedActivePartnerApiKey(sellerId: 1, walletBalance: 500);
    }

    public function test_product_detail_marks_variable_denomination_available_with_supplier_markup(): void
    {
        [$productId, $denominationId] = $this->seedVariableDenominationProduct(
            markupType: 'percent',
            markupValue: 5,
        );
        $this->assignProductToPartnerCatalog(productId: $productId, partnerPrice: 1);

        $response = $this->getJson('/api/v1/partner/products/'.$productId, $this->partnerApiHeaders());

        $response->assertOk();
        $response->assertJsonPath('data.pricing.type', 'denominations');
        $response->assertJsonPath('data.pricing.denominations.0.id', $denominationId);
        $response->assertJsonPath('data.pricing.denominations.0.type', 'variable');
        $response->assertJsonPath('data.pricing.denominations.0.available', true);
        $response->assertJsonPath('data.pricing.denominations.0.price_source', 'supplier_markup');
        $response->assertJsonPath('data.pricing.denominations.0.supplier_markup.type', 'percent');
        $response->assertJsonPath('data.pricing.denominations.0.supplier_markup.value', 5);
        $response->assertJsonPath('data.pricing.denominations.0.min_face_value', 10);
        $response->assertJsonPath('data.pricing.denominations.0.max_face_value', 500);
    }

    public function test_quote_uses_supplier_markup_when_no_partner_formula(): void
    {
        [$productId, $denominationId] = $this->seedVariableDenominationProduct(
            markupType: 'percent',
            markupValue: 5,
        );
        $this->assignProductToPartnerCatalog(productId: $productId, partnerPrice: 1);

        $response = $this->postJson('/api/v1/partner/products/'.$productId.'/quote', [
            'quantity' => 1,
            'supplier_denomination_id' => $denominationId,
            'custom_amount' => 100,
        ], $this->partnerApiHeaders());

        $response->assertOk();
        $response->assertJsonPath('data.catalog_subtotal', 105);
        $response->assertJsonPath('data.total', 105);
        $response->assertJsonPath('data.custom_amount', 100);
    }

    public function test_quote_rejects_custom_amount_outside_allowed_range(): void
    {
        [$productId, $denominationId] = $this->seedVariableDenominationProduct(
            markupType: 'percent',
            markupValue: 5,
        );
        $this->assignProductToPartnerCatalog(productId: $productId, partnerPrice: 1);

        $response = $this->postJson('/api/v1/partner/products/'.$productId.'/quote', [
            'quantity' => 1,
            'supplier_denomination_id' => $denominationId,
            'custom_amount' => 5,
        ], $this->partnerApiHeaders());

        $response->assertUnprocessable();
        $response->assertJsonPath('error', 'The custom amount is outside the allowed range.');
    }

    public function test_quote_uses_partner_formula_override_when_configured(): void
    {
        [$productId, $denominationId] = $this->seedVariableDenominationProduct(
            markupType: 'percent',
            markupValue: 5,
        );
        $this->assignProductToPartnerCatalog(productId: $productId, partnerPrice: 1);
        $this->setPartnerVariableFormula($productId, 'percent', 10);

        $response = $this->postJson('/api/v1/partner/products/'.$productId.'/quote', [
            'quantity' => 1,
            'supplier_denomination_id' => $denominationId,
            'custom_amount' => 100,
        ], $this->partnerApiHeaders());

        $response->assertOk();
        $response->assertJsonPath('data.catalog_subtotal', 110);
        $response->assertJsonPath('data.total', 110);
    }

    public function test_create_order_succeeds_for_variable_denomination_without_partner_formula(): void
    {
        [$productId, $denominationId] = $this->seedVariableDenominationProduct(
            markupType: 'percent',
            markupValue: 5,
        );
        $this->assignProductToPartnerCatalog(productId: $productId, partnerPrice: 1);

        $manager = Mockery::mock(SupplierManager::class);
        $manager->shouldReceive('getAvailableStockForMapping')->andReturn(5);
        $this->app->instance(SupplierManager::class, $manager);

        $response = $this->postJson('/api/v1/partner/orders', [
            'product_id' => $productId,
            'quantity' => 1,
            'supplier_denomination_id' => $denominationId,
            'custom_amount' => 100,
            'expected_total' => 105,
        ], $this->partnerApiHeaders());

        $response->assertCreated();
        $response->assertJsonPath('data.total_cost', 105);
        $response->assertJsonPath('data.pricing.custom_amount', 100);
    }

    public function test_quote_uses_mapping_min_max_when_denomination_range_is_empty(): void
    {
        [$productId, $denominationId] = $this->seedVariableDenominationProduct(
            markupType: 'flat',
            markupValue: 2,
            minFaceValue: null,
            maxFaceValue: null,
            mappingMinAmount: 20,
            mappingMaxAmount: 200,
        );
        $this->assignProductToPartnerCatalog(productId: $productId, partnerPrice: 1);

        $response = $this->postJson('/api/v1/partner/products/'.$productId.'/quote', [
            'quantity' => 1,
            'supplier_denomination_id' => $denominationId,
            'custom_amount' => 50,
        ], $this->partnerApiHeaders());

        $response->assertOk();
        $response->assertJsonPath('data.catalog_subtotal', 52);
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function seedVariableDenominationProduct(
        string $markupType = 'percent',
        float $markupValue = 0,
        ?float $minFaceValue = 10,
        ?float $maxFaceValue = 500,
        ?float $mappingMinAmount = null,
        ?float $mappingMaxAmount = null,
    ): array {
        $productId = 80;

        $this->app['db']->table('products')->insert([
            'id' => $productId,
            'user_id' => 1,
            'added_by' => 'admin',
            'name' => 'Variable Denom Product',
            'slug' => 'variable-denom-product',
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
            'supplier_product_id' => 'variable-sku',
            'cost_price' => 8,
            'markup_type' => $markupType,
            'markup_value' => $markupValue,
            'is_active' => true,
            'is_customizable' => true,
            'min_amount' => $mappingMinAmount,
            'max_amount' => $mappingMaxAmount,
            'is_direct_topup' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $denominationId = $this->app['db']->table('supplier_product_denominations')->insertGetId([
            'supplier_product_mapping_id' => $mappingId,
            'supplier_product_id' => 'variable-sku',
            'name' => 'Custom amount',
            'type' => 'variable',
            'min_face_value' => $minFaceValue,
            'max_face_value' => $maxFaceValue,
            'face_value_currency' => 'USD',
            'is_active' => true,
            'sort_order' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$productId, $denominationId];
    }

    private function setPartnerVariableFormula(int $productId, string $type, float $value): void
    {
        PartnerCatalogItem::query()
            ->where('product_id', $productId)
            ->update([
                'variable_price_type' => $type,
                'variable_price_value' => $value,
            ]);
    }
}
