<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\Product;
use App\Models\SupplierApi;
use App\Models\SupplierProductMapping;
use App\Services\DigitalProductCodeService;
use App\Utils\CartManager;
use Illuminate\Database\Schema\Blueprint;
use Tests\Concerns\ManagesTestDatabaseSchema;
use Tests\TestCase;

class DigitalCheckoutInactiveSupplierMappingTest extends TestCase
{
    use ManagesTestDatabaseSchema;

    protected function setUp(): void
    {
        parent::setUp();

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

        $this->recreateTable('products', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->nullable();
            $table->string('product_type')->default('digital');
            $table->string('digital_product_type')->nullable();
            $table->integer('current_stock')->default(0);
            $table->boolean('partner_api_only')->default(false);
            $table->timestamps();
        });

        $this->recreateTable('supplier_apis', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->nullable();
            $table->string('driver')->default('generic_rest');
            $table->string('base_url')->nullable();
            $table->string('health_status')->default('healthy');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        $this->recreateTable('supplier_product_mappings', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('supplier_api_id');
            $table->string('supplier_product_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('priority')->default(1);
            $table->timestamps();
        });

        $this->recreateTable('digital_product_codes', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('product_id');
            $table->text('code')->nullable();
            $table->string('status')->default('available');
            $table->boolean('is_active')->default(true);
            $table->date('expiry_date')->nullable();
            $table->timestamps();
        });
    }

    public function test_product_stock_check_blocks_digital_with_inactive_supplier_mapping_and_no_local_codes(): void
    {
        SupplierProductMapping::flushEventListeners();
        Product::flushEventListeners();

        $product = Product::query()->create([
            'name' => 'PUBG Test',
            'product_type' => 'digital',
            'digital_product_type' => 'ready_after_sell',
            'current_stock' => 0,
        ]);

        $supplier = SupplierApi::query()->create([
            'name' => 'Bamboo inactive',
            'driver' => 'generic_rest',
            'base_url' => 'https://example.test',
            'health_status' => 'healthy',
            'is_active' => false,
        ]);

        SupplierProductMapping::query()->create([
            'product_id' => $product->id,
            'supplier_api_id' => $supplier->id,
            'supplier_product_id' => 'sku-1',
            'is_active' => true,
            'priority' => 1,
        ]);

        $cart = new Cart([
            'product_id' => $product->id,
            'product_type' => 'digital',
            'quantity' => 1,
        ]);
        $cart->setRelation('product', $product);

        $this->assertFalse(SupplierProductMapping::hasActiveMapping((int) $product->id));

        $errors = app(DigitalProductCodeService::class)->getDigitalStockErrors(collect([$cart]));
        $this->assertNotEmpty($errors);

        $this->assertFalse(CartManager::product_stock_check(collect([$cart])));
    }
}
