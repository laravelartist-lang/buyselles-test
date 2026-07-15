<?php

namespace Tests\Unit;

use App\Models\Product;
use App\Services\DirectTopUp\DirectTopUpService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Tests\Concerns\ManagesTestDatabaseSchema;
use Tests\TestCase;

class DirectTopUpListingPriceTest extends TestCase
{
    use ManagesTestDatabaseSchema;

    private DirectTopUpService $service;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();

        $this->recreateTable('business_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('type');
            $table->text('value')->nullable();
        });

        $this->app['db']->table('business_settings')->insert([
            ['type' => 'language', 'value' => json_encode([['code' => 'en', 'default' => true, 'direction' => 'ltr']])],
            ['type' => 'currency_model', 'value' => 'single_currency'],
            ['type' => 'system_default_currency', 'value' => '1'],
            ['type' => 'decimal_point_settings', 'value' => '2'],
            ['type' => 'currency_symbol_position', 'value' => 'left'],
        ]);

        $this->recreateTable('currencies', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->nullable();
            $table->string('symbol')->nullable();
            $table->string('code')->nullable();
            $table->decimal('exchange_rate', 24, 8)->default(1);
            $table->boolean('status')->default(true);
            $table->timestamps();
        });

        $this->app['db']->table('currencies')->insert([
            'id' => 1,
            'name' => 'US Dollar',
            'symbol' => '$',
            'code' => 'USD',
            'exchange_rate' => 1,
            'status' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->recreateTable('supplier_apis', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->nullable();
            $table->string('driver')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('supports_direct_top_up')->default(true);
            $table->timestamps();
        });

        $this->recreateTable('supplier_product_mappings', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('product_id')->nullable();
            $table->unsignedBigInteger('supplier_api_id')->nullable();
            $table->string('supplier_product_id')->nullable();
            $table->decimal('cost_price', 24, 10)->default(0);
            $table->string('markup_type')->default('percent');
            $table->decimal('markup_value', 10, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_direct_topup')->default(false);
            $table->string('direct_topup_account_label', 255)->nullable();
            $table->timestamps();
        });

        $this->recreateTable('products', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->nullable();
            $table->string('product_type')->default('digital');
            $table->decimal('unit_price', 24, 10)->default(0);
            $table->integer('minimum_order_qty')->default(1);
            $table->decimal('discount', 24, 2)->default(0);
            $table->string('discount_type')->nullable();
            $table->boolean('status')->default(true);
            $table->timestamps();
        });

        $this->recreateTable('translations', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('translationable_id')->nullable();
            $table->string('translationable_type')->nullable();
            $table->string('locale')->nullable();
            $table->string('key')->nullable();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        $this->recreateTable('reviews', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('product_id')->nullable();
            $table->unsignedBigInteger('delivery_man_id')->nullable();
            $table->boolean('status')->default(1);
            $table->timestamps();
        });

        $this->service = app(DirectTopUpService::class);
    }

    public function test_listing_payload_returns_line_total_for_micro_priced_direct_topup(): void
    {
        $product = $this->makeMicroPricedDirectTopUpProduct();

        $payload = $this->service->buildListingPricePayload($product);

        $this->assertNotNull($payload);
        $this->assertSame(900.0, $payload['quantity']);
        $this->assertEqualsWithDelta(0.9565506, $payload['line_total'], 0.0000001);
        $this->assertStringContainsString('0.95', $payload['formatted_line_total']);
    }

    public function test_resolve_listing_display_amount_is_not_micro_unit_price(): void
    {
        $product = $this->makeMicroPricedDirectTopUpProduct();

        $listingAmount = $this->service->resolveListingDisplayAmount($product);

        $this->assertNotNull($listingAmount);
        $this->assertGreaterThan(0.9, $listingAmount);
        $this->assertLessThan(0.01, $product->getEffectiveSellPrice());
    }

    public function test_get_product_price_by_type_returns_line_total_not_zero_for_direct_topup(): void
    {
        $product = $this->makeMicroPricedDirectTopUpProduct();

        $unitOnlyPrice = webCurrencyConverter($product->unit_price);

        $listingPrice = getProductPriceByType(
            product: $product,
            type: 'discounted_unit_price',
            result: 'string',
        );

        $this->assertSame('$0.00', $unitOnlyPrice);
        $this->assertNotSame('$0.00', $listingPrice);
        $this->assertStringContainsString('0.95', (string) $listingPrice);
    }

    public function test_get_product_price_by_type_value_returns_line_total_amount(): void
    {
        $product = $this->makeMicroPricedDirectTopUpProduct();

        $listingValue = getProductPriceByType(
            product: $product,
            type: 'discounted_unit_price',
            result: 'value',
        );

        $this->assertEqualsWithDelta(0.9565506, (float) $listingValue, 0.0000001);
    }

    private function makeMicroPricedDirectTopUpProduct(): Product
    {
        $this->app['db']->table('supplier_apis')->insert([
            'id' => 1,
            'name' => 'SecretOrca',
            'driver' => 'generic_rest',
            'is_active' => true,
            'supports_direct_top_up' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $productId = 1;

        $this->app['db']->table('products')->insert([
            'id' => $productId,
            'name' => 'Secret Orca Test Product',
            'product_type' => 'digital',
            'unit_price' => 0.001062834,
            'minimum_order_qty' => 900,
            'discount' => 0,
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->app['db']->table('supplier_product_mappings')->insert([
            'product_id' => $productId,
            'supplier_api_id' => 1,
            'supplier_product_id' => 'test-product-id',
            'cost_price' => 0.001062834,
            'markup_type' => 'percent',
            'markup_value' => 0,
            'is_active' => true,
            'is_direct_topup' => true,
            'direct_topup_account_label' => 'Player ID',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $product = Product::query()->with('supplierMapping.supplierApi')->findOrFail($productId);

        return $product;
    }
}
