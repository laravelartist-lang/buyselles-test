<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\Supplier\SupplierController;
use App\Http\Controllers\Admin\Supplier\SupplierMappingController;
use App\Jobs\SyncSupplierCatalogJob;
use App\Models\SupplierApi;
use App\Utils\ProductManager;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use ReflectionMethod;
use Tests\Concerns\ManagesTestDatabaseSchema;
use Tests\TestCase;

class SupplierMappingCatalogBrowseTest extends TestCase
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

        $this->recreateTable('reviews', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('product_id')->nullable();
            $table->unsignedBigInteger('delivery_man_id')->nullable();
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
            $table->boolean('supports_direct_top_up')->default(false);
            $table->timestamps();
        });

        $this->recreateTable('products', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->nullable();
            $table->string('added_by')->default('admin');
            $table->string('product_type')->default('digital');
            $table->string('digital_product_type')->nullable();
            $table->integer('current_stock')->default(0);
            $table->decimal('unit_price', 24, 4)->default(0);
            $table->decimal('purchase_price', 24, 4)->default(0);
            $table->text('variation')->nullable();
            $table->text('colors')->nullable();
            $table->integer('minimum_order_qty')->default(1);
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
            $table->boolean('is_customizable')->default(false);
            $table->boolean('is_direct_topup')->default(false);
            $table->string('direct_topup_account_label', 255)->nullable();
            $table->timestamps();
        });

        $this->recreateTable('supplier_product_denominations', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('supplier_product_mapping_id')->nullable();
            $table->string('type')->nullable();
            $table->decimal('face_value', 24, 4)->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        $this->recreateTable('stock_clearance_products', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('product_id')->nullable();
            $table->boolean('is_active')->default(false);
            $table->timestamps();
        });
    }

    public function test_browse_catalog_excludes_globally_mapped_supplier_product(): void
    {
        $supplier = SupplierApi::query()->create([
            'name' => 'Golf',
            'driver' => 'golf_api',
            'is_active' => true,
        ]);

        Cache::put(SyncSupplierCatalogJob::catalogCacheKey($supplier->id), [
            ['id' => '41', 'name' => 'Mapped SKU', 'price' => 2.28, 'currency' => 'USD', 'stock' => 100],
            ['id' => '42', 'name' => 'Available SKU', 'price' => 3.00, 'currency' => 'USD', 'stock' => 50],
        ], now()->addHour());

        $this->app['db']->table('products')->insert([
            'id' => 1,
            'name' => 'Store Product',
            'added_by' => 'admin',
            'product_type' => 'digital',
            'digital_product_type' => 'ready_after_sell',
            'unit_price' => 5,
            'purchase_price' => 4,
            'status' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->app['db']->table('supplier_product_mappings')->insert([
            'id' => 1,
            'product_id' => 1,
            'supplier_api_id' => $supplier->id,
            'supplier_product_id' => '41',
            'cost_price' => 1.62,
            'cost_currency' => 'USD',
            'markup_type' => 'percent',
            'markup_value' => 10,
            'priority' => 0,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $controller = app(SupplierController::class);
        $response = $controller->browseCatalog($supplier->id, Request::create('/admin/supplier/'.$supplier->id.'/catalog', 'GET'));
        $payload = $response->getData(true);

        $this->assertTrue($payload['success']);
        $this->assertSame(['42'], collect($payload['products'])->pluck('id')->all());
    }

    public function test_browse_catalog_keeps_current_sku_when_except_mapping_id_is_passed(): void
    {
        $supplier = SupplierApi::query()->create([
            'name' => 'Golf',
            'driver' => 'golf_api',
            'is_active' => true,
        ]);

        Cache::put(SyncSupplierCatalogJob::catalogCacheKey($supplier->id), [
            ['id' => '41', 'name' => 'Mapped SKU', 'price' => 2.28, 'currency' => 'USD', 'stock' => 100],
            ['id' => '42', 'name' => 'Available SKU', 'price' => 3.00, 'currency' => 'USD', 'stock' => 50],
        ], now()->addHour());

        $this->app['db']->table('products')->insert([
            'id' => 1,
            'name' => 'Store Product',
            'added_by' => 'admin',
            'product_type' => 'digital',
            'digital_product_type' => 'ready_after_sell',
            'unit_price' => 5,
            'purchase_price' => 4,
            'status' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->app['db']->table('supplier_product_mappings')->insert([
            'id' => 2,
            'product_id' => 1,
            'supplier_api_id' => $supplier->id,
            'supplier_product_id' => '41',
            'cost_price' => 1.62,
            'cost_currency' => 'USD',
            'markup_type' => 'percent',
            'markup_value' => 10,
            'priority' => 0,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $mappingId = 2;

        $controller = app(SupplierController::class);
        $response = $controller->browseCatalog($supplier->id, Request::create('/admin/supplier/'.$supplier->id.'/catalog', 'GET', [
            'except_mapping_id' => $mappingId,
        ]));
        $payload = $response->getData(true);

        $this->assertTrue($payload['success']);
        $this->assertSame(['41', '42'], collect($payload['products'])->pluck('id')->all());
    }

    public function test_supplier_product_already_mapped_rejects_duplicate_pair(): void
    {
        $controller = app(SupplierMappingController::class);
        $method = new ReflectionMethod($controller, 'supplierProductAlreadyMapped');

        $this->app['db']->table('supplier_apis')->insert([
            'id' => 8,
            'name' => 'Golf',
            'driver' => 'golf_api',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->app['db']->table('products')->insert([
            'id' => 10,
            'name' => 'Product A',
            'added_by' => 'admin',
            'product_type' => 'digital',
            'status' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->app['db']->table('products')->insert([
            'id' => 11,
            'name' => 'Product B',
            'added_by' => 'admin',
            'product_type' => 'digital',
            'status' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->app['db']->table('supplier_product_mappings')->insert([
            'id' => 5,
            'product_id' => 10,
            'supplier_api_id' => 8,
            'supplier_product_id' => '41',
            'cost_price' => 1,
            'cost_currency' => 'USD',
            'markup_type' => 'percent',
            'markup_value' => 0,
            'priority' => 0,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertTrue($method->invoke($controller, 8, '41'));
        $this->assertFalse($method->invoke($controller, 8, '41', 5));
        $this->assertFalse($method->invoke($controller, 8, '42'));
    }

    public function test_resolve_mapped_display_unit_price_returns_mapping_sell_price(): void
    {
        $supplier = SupplierApi::query()->create([
            'name' => 'Golf',
            'driver' => 'golf_api',
            'is_active' => true,
        ]);

        $productId = 20;

        $this->app['db']->table('products')->insert([
            'id' => $productId,
            'name' => 'Mapped Product',
            'added_by' => 'admin',
            'product_type' => 'digital',
            'digital_product_type' => 'ready_after_sell',
            'unit_price' => 99,
            'purchase_price' => 50,
            'status' => true,
            'variation' => '[]',
            'colors' => '[]',
            'minimum_order_qty' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->app['db']->table('supplier_product_mappings')->insert([
            'product_id' => $productId,
            'supplier_api_id' => $supplier->id,
            'supplier_product_id' => '41',
            'cost_price' => 2.00,
            'cost_currency' => 'USD',
            'markup_type' => 'percent',
            'markup_value' => 10,
            'priority' => 0,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $pricing = ProductManager::resolveMappedDisplayUnitPrice(['id' => $productId]);

        $this->assertTrue($pricing['applied']);
        $this->assertSame(2.2, $pricing['unit_price']);
        $this->assertSame(2.2, $pricing['discounted_unit_price']);
    }
}
