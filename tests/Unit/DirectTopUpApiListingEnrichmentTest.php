<?php

namespace Tests\Unit;

use App\Models\Product;
use App\Utils\Helpers;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Tests\Concerns\ManagesTestDatabaseSchema;
use Tests\TestCase;

class DirectTopUpApiListingEnrichmentTest extends TestCase
{
    use ManagesTestDatabaseSchema;

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
            $table->string('colors')->nullable();
            $table->string('attributes')->nullable();
            $table->text('choice_options')->nullable();
            $table->text('variation')->nullable();
            $table->string('category_ids')->nullable();
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

        $this->recreateTable('colors', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->nullable();
            $table->string('code')->nullable();
            $table->timestamps();
        });
    }

    public function test_product_data_formatting_enriches_direct_topup_listing_fields(): void
    {
        $product = $this->makeMicroPricedDirectTopUpProduct();

        $formatted = Helpers::product_data_formatting($product, false);

        $this->assertTrue($formatted['is_direct_topup']);
        $this->assertGreaterThan(0.9, (float) $formatted['display_price']);
        $this->assertStringContainsString('0.95', (string) $formatted['formatted_display_price']);
        $this->assertIsArray($formatted['direct_topup']);
        $this->assertSame(900.0, $formatted['direct_topup']['quantity']);
    }

    public function test_product_data_formatting_batch_enriches_direct_topup_listing_fields(): void
    {
        $product = $this->makeMicroPricedDirectTopUpProduct();

        $formatted = Helpers::product_data_formatting([$product], true);

        $this->assertCount(1, $formatted);
        $this->assertTrue($formatted[0]['is_direct_topup']);
        $this->assertGreaterThan(0.9, (float) $formatted[0]['display_price']);
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
            'colors' => json_encode(['#000000']),
            'attributes' => json_encode([]),
            'choice_options' => json_encode([]),
            'variation' => json_encode([]),
            'category_ids' => json_encode([]),
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

        return Product::query()->with('supplierMapping.supplierApi')->findOrFail($productId);
    }
}
