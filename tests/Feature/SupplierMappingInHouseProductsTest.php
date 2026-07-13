<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\Supplier\SupplierMappingController;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use ReflectionMethod;
use Tests\Concerns\ManagesTestDatabaseSchema;
use Tests\TestCase;

class SupplierMappingInHouseProductsTest extends TestCase
{
    use ManagesTestDatabaseSchema;

    protected function setUp(): void
    {
        parent::setUp();

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

        $this->recreateTable('translations', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('translationable_id')->nullable();
            $table->string('translationable_type')->nullable();
            $table->string('locale')->nullable();
            $table->string('key')->nullable();
            $table->text('value')->nullable();
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

        $this->recreateTable('reviews', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('product_id')->nullable();
            $table->unsignedBigInteger('delivery_man_id')->nullable();
            $table->boolean('status')->default(true);
            $table->timestamps();
        });

        $this->recreateTable('stock_clearance_products', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('product_id')->nullable();
            $table->boolean('is_active')->default(false);
            $table->timestamps();
        });

        $this->recreateTable('supplier_apis', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->nullable();
            $table->string('driver')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('supports_direct_top_up')->default(false);
            $table->timestamps();
        });

        $this->recreateTable('products', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->nullable();
            $table->string('added_by')->default('admin');
            $table->string('product_type')->default('digital');
            $table->unsignedBigInteger('category_id')->nullable();
            $table->unsignedBigInteger('sub_category_id')->nullable();
            $table->unsignedBigInteger('sub_sub_category_id')->nullable();
            $table->boolean('status')->default(true);
            $table->timestamps();
        });

        $this->recreateTable('supplier_product_mappings', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('product_id')->index();
            $table->unsignedBigInteger('supplier_api_id')->nullable();
            $table->string('supplier_product_id')->nullable();
            $table->decimal('cost_price', 24, 4)->default(0);
            $table->string('cost_currency', 3)->default('USD');
            $table->string('markup_type')->default('percent');
            $table->decimal('markup_value', 24, 4)->default(0);
            $table->integer('priority')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function test_in_house_products_excludes_products_mapped_to_selected_supplier(): void
    {
        $this->seedProductsAndSuppliers();

        $controller = app(SupplierMappingController::class);
        $response = $controller->getInHouseProducts(Request::create('/admin/supplier/mapping/in-house-products', 'GET', [
            'category_id' => 1,
            'supplier_api_id' => 1,
        ]));

        $payload = $response->getData(true);
        $productIds = collect($payload['products'])->pluck('id')->all();

        $this->assertTrue($payload['success']);
        $this->assertSame([2], $productIds);
    }

    public function test_in_house_products_keeps_product_visible_for_other_suppliers(): void
    {
        $this->seedProductsAndSuppliers();

        $controller = app(SupplierMappingController::class);
        $response = $controller->getInHouseProducts(Request::create('/admin/supplier/mapping/in-house-products', 'GET', [
            'category_id' => 1,
            'supplier_api_id' => 2,
        ]));

        $payload = $response->getData(true);
        $productIds = collect($payload['products'])->pluck('id')->all();

        $this->assertTrue($payload['success']);
        $this->assertSame([1, 2], $productIds);
    }

    public function test_in_house_products_without_supplier_returns_all_category_products(): void
    {
        $this->seedProductsAndSuppliers();

        $controller = app(SupplierMappingController::class);
        $response = $controller->getInHouseProducts(Request::create('/admin/supplier/mapping/in-house-products', 'GET', [
            'category_id' => 1,
        ]));

        $payload = $response->getData(true);
        $productIds = collect($payload['products'])->pluck('id')->all();

        $this->assertTrue($payload['success']);
        $this->assertSame([1, 2], $productIds);
    }

    public function test_in_house_products_keeps_current_mapping_product_when_except_mapping_id_is_passed(): void
    {
        $this->seedProductsAndSuppliers();

        $controller = app(SupplierMappingController::class);
        $response = $controller->getInHouseProducts(Request::create('/admin/supplier/mapping/in-house-products', 'GET', [
            'category_id' => 1,
            'supplier_api_id' => 1,
            'except_mapping_id' => 1,
        ]));

        $payload = $response->getData(true);
        $productIds = collect($payload['products'])->pluck('id')->all();

        $this->assertTrue($payload['success']);
        $this->assertSame([1, 2], $productIds);
    }

    public function test_product_already_mapped_to_supplier_rejects_duplicate_pair(): void
    {
        $this->seedProductsAndSuppliers();

        $controller = app(SupplierMappingController::class);
        $method = new ReflectionMethod($controller, 'productAlreadyMappedToSupplier');

        $this->assertTrue($method->invoke($controller, 1, 1));
        $this->assertFalse($method->invoke($controller, 1, 1, 1));
        $this->assertFalse($method->invoke($controller, 1, 2));
    }

    public function test_same_product_can_map_to_different_suppliers(): void
    {
        $this->seedProductsAndSuppliers();

        $controller = app(SupplierMappingController::class);
        $method = new ReflectionMethod($controller, 'productAlreadyMappedToSupplier');

        $this->assertTrue($method->invoke($controller, 1, 1));
        $this->assertFalse($method->invoke($controller, 1, 2));
    }

    private function seedProductsAndSuppliers(): void
    {
        $this->app['db']->table('supplier_apis')->insert([
            [
                'id' => 1,
                'name' => 'Golf',
                'driver' => 'golf_api',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 2,
                'name' => 'Bamboo',
                'driver' => 'bamboo',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $this->app['db']->table('products')->insert([
            [
                'id' => 1,
                'name' => 'Product A',
                'added_by' => 'admin',
                'product_type' => 'digital',
                'category_id' => 1,
                'status' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 2,
                'name' => 'Product B',
                'added_by' => 'admin',
                'product_type' => 'digital',
                'category_id' => 1,
                'status' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $this->app['db']->table('supplier_product_mappings')->insert([
            'id' => 1,
            'product_id' => 1,
            'supplier_api_id' => 1,
            'supplier_product_id' => 'SKU-A',
            'cost_price' => 1,
            'cost_currency' => 'USD',
            'markup_type' => 'percent',
            'markup_value' => 0,
            'priority' => 0,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
