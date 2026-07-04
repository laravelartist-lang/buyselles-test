<?php

namespace Tests\Unit;

use App\Models\Product;
use App\Services\DirectTopUp\DirectTopUpService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;
use Tests\Concerns\ManagesTestDatabaseSchema;
use Tests\TestCase;

class DirectTopUpServiceTest extends TestCase
{
    use ManagesTestDatabaseSchema;

    private DirectTopUpService $service;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        $this->recreateTable('business_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('type')->nullable();
            $table->longText('value')->nullable();
            $table->timestamps();
        });
        $this->app['db']->table('business_settings')->insert([
            'type' => 'language',
            'value' => json_encode([
                ['code' => 'en', 'name' => 'English', 'default' => true, 'direction' => 'ltr'],
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->recreateTable('supplier_apis', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->nullable();
            $table->string('driver')->nullable();
            $table->string('base_url')->nullable();
            $table->text('credentials')->nullable();
            $table->string('auth_type')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        $this->recreateTable('supplier_product_mappings', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('product_id')->nullable();
            $table->unsignedBigInteger('supplier_api_id')->nullable();
            $table->string('supplier_product_id')->nullable();
            $table->decimal('cost_price', 10, 2)->default(0);
            $table->string('markup_type')->default('percent');
            $table->decimal('markup_value', 10, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_direct_topup')->default(false);
            $table->string('direct_topup_account_label', 255)->nullable();
            $table->decimal('direct_topup_min_quantity', 20, 4)->nullable();
            $table->decimal('direct_topup_max_quantity', 20, 4)->nullable();
            $table->decimal('direct_topup_price_per_unit', 24, 8)->nullable();
            $table->timestamps();
        });

        $this->recreateTable('products', function (Blueprint $table): void {
            $table->id();
            $table->string('added_by')->nullable();
            $table->string('name')->nullable();
            $table->string('slug')->nullable();
            $table->string('code')->nullable();
            $table->string('product_type')->nullable();
            $table->string('digital_product_type')->nullable();
            $table->decimal('unit_price', 14, 2)->nullable();
            $table->tinyInteger('status')->default(1);
            $table->timestamps();
        });

        $this->service = app(DirectTopUpService::class);
    }

    public function test_calculate_total_price_from_quantity(): void
    {
        $product = $this->makeDirectTopUpProduct();

        $total = $this->service->calculateTotalPrice($product, 1000);

        $this->assertSame(10.0, $total);
    }

    public function test_calculate_quantity_from_price_floors_and_clamps(): void
    {
        $product = $this->makeDirectTopUpProduct();

        $this->assertSame(100.0, $this->service->calculateQuantityFromPrice($product, 0.50));
        $this->assertSame(100.0, $this->service->calculateQuantityFromPrice($product, 0.01));
        $this->assertSame(10000.0, $this->service->calculateQuantityFromPrice($product, 99999));
    }

    public function test_validate_configuration_rejects_invalid_min_max(): void
    {
        $product = $this->makeDirectTopUpProduct();
        $this->app['db']->table('supplier_product_mappings')
            ->where('product_id', $product->id)
            ->update([
                'direct_topup_min_quantity' => 500,
                'direct_topup_max_quantity' => 100,
            ]);

        $this->expectException(InvalidArgumentException::class);

        $this->service->validateConfiguration($product);
    }

    public function test_build_api_payload_returns_configuration(): void
    {
        $product = $this->makeDirectTopUpProduct();

        $payload = $this->service->buildApiPayload($product);

        $this->assertNotNull($payload);
        $this->assertTrue($payload['enabled']);
        $this->assertSame('Player ID', $payload['account_label']);
        $this->assertSame(100.0, $payload['min_quantity']);
        $this->assertSame(10000.0, $payload['max_quantity']);
        $this->assertSame(0.01, $payload['price_per_unit']);
    }

    private function makeDirectTopUpProduct(): Product
    {
        $product = Product::create([
            'added_by' => 'admin',
            'name' => 'Direct Top-up Product',
            'slug' => 'direct-topup-product',
            'code' => 'TOPUP001',
            'product_type' => 'digital',
            'digital_product_type' => 'ready_product',
            'unit_price' => 1,
            'status' => 1,
        ]);

        $supplierId = $this->app['db']->table('supplier_apis')->insertGetId([
            'name' => 'Test Supplier',
            'driver' => 'generic_rest',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->app['db']->table('supplier_product_mappings')->insert([
            'product_id' => $product->id,
            'supplier_api_id' => $supplierId,
            'supplier_product_id' => 'TEST-001',
            'cost_price' => 0.01,
            'markup_type' => 'percent',
            'markup_value' => 0,
            'is_active' => true,
            'is_direct_topup' => true,
            'direct_topup_account_label' => 'Player ID',
            'direct_topup_min_quantity' => 100,
            'direct_topup_max_quantity' => 10000,
            'direct_topup_price_per_unit' => 0.01,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $product;
    }
}
