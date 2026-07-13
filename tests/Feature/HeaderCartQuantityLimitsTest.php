<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\Product;
use App\Models\SupplierApi;
use App\Models\SupplierProductMapping;
use App\Utils\CartManager;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Tests\Concerns\ManagesTestDatabaseSchema;
use Tests\TestCase;

class HeaderCartQuantityLimitsTest extends TestCase
{
    use ManagesTestDatabaseSchema;

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
            $table->integer('rate_limit_per_minute')->default(60);
            $table->timestamps();
        });

        $this->recreateTable('supplier_product_mappings', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('product_id')->index();
            $table->unsignedBigInteger('supplier_api_id')->nullable();
            $table->string('supplier_product_id')->nullable();
            $table->decimal('cost_price', 10, 2)->default(0);
            $table->string('markup_type')->default('percent');
            $table->decimal('markup_value', 10, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_direct_topup')->default(false);
            $table->timestamps();
        });

        $this->recreateTable('digital_product_codes', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('product_id')->index();
            $table->text('code');
            $table->string('status')->default('available')->index();
            $table->boolean('is_active')->default(true);
            $table->date('expiry_date')->nullable();
            $table->timestamps();
        });

        $this->recreateTable('products', function (Blueprint $table): void {
            $table->id();
            $table->string('product_type')->nullable();
            $table->string('digital_product_type')->nullable();
            $table->text('variation')->nullable();
            $table->integer('current_stock')->default(0);
            $table->integer('minimum_order_qty')->default(1);
            $table->decimal('unit_price', 24, 2)->default(0);
            $table->boolean('status')->default(true);
            $table->timestamps();
        });

        $this->recreateTable('carts', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->unsignedBigInteger('product_id')->nullable();
            $table->string('product_type')->nullable();
            $table->string('variant')->nullable();
            $table->integer('quantity')->default(1);
            $table->decimal('price', 24, 2)->default(0);
            $table->decimal('discount', 24, 2)->default(0);
            $table->decimal('shipping_cost', 24, 2)->default(0);
            $table->boolean('is_guest')->default(1);
            $table->timestamps();
        });

        $this->recreateTable('translations', function (Blueprint $table): void {
            $table->id();
            $table->string('translationable_type')->nullable();
            $table->unsignedBigInteger('translationable_id')->nullable();
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
    }

    public function test_unmapped_digital_product_uses_available_code_count_as_max_quantity(): void
    {
        $productId = 201;

        for ($i = 0; $i < 3; $i++) {
            $this->app['db']->table('digital_product_codes')->insert([
                'product_id' => $productId,
                'code' => 'CODE-'.$i,
                'status' => 'available',
                'is_active' => true,
                'expiry_date' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $product = Product::make([
            'product_type' => 'digital',
            'digital_product_type' => 'ready_product',
            'variation' => '[]',
            'current_stock' => 0,
            'minimum_order_qty' => 1,
        ]);
        $product->id = $productId;

        $cart = Cart::make([
            'product_id' => $productId,
            'quantity' => 1,
            'variant' => null,
        ]);
        $cart->id = 1;

        $limits = CartManager::getCartItemQuantityLimits($cart, $product);

        $this->assertSame(1, $limits['min']);
        $this->assertSame(3, $limits['max']);
        $this->assertSame(1, $limits['display_quantity']);
    }

    public function test_mapped_digital_product_uses_supplier_stock_cache_as_max_quantity(): void
    {
        $productId = 202;
        $mappingId = 1;

        $this->app['db']->table('supplier_apis')->insert([
            'id' => 1,
            'name' => 'Golf API',
            'driver' => 'golf_api',
            'base_url' => 'https://example.com',
            'credentials' => '{}',
            'auth_type' => 'api_key',
            'is_active' => true,
            'rate_limit_per_minute' => 60,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->app['db']->table('supplier_product_mappings')->insert([
            'id' => $mappingId,
            'product_id' => $productId,
            'supplier_api_id' => 1,
            'supplier_product_id' => 'GOLF-123',
            'is_active' => true,
            'is_direct_topup' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Cache::put('supplier_stock:'.$mappingId, 25, 3600);

        $product = Product::make([
            'product_type' => 'digital',
            'digital_product_type' => 'ready_after_sell',
            'variation' => '[]',
            'current_stock' => 0,
            'minimum_order_qty' => 1,
        ]);
        $product->id = $productId;

        $mapping = SupplierProductMapping::query()->find($mappingId);
        $supplier = SupplierApi::query()->find(1);
        $mapping->setRelation('supplierApi', $supplier);
        $product->setRelation('supplierMapping', $mapping);

        $cart = Cart::make([
            'product_id' => $productId,
            'quantity' => 1,
            'variant' => null,
        ]);
        $cart->id = 2;

        $limits = CartManager::getCartItemQuantityLimits($cart, $product);

        $this->assertSame(1, $limits['min']);
        $this->assertSame(25, $limits['max']);
    }

    public function test_update_cart_qty_rejects_quantity_above_supplier_stock_for_mapped_product(): void
    {
        $productId = 203;
        $mappingId = 2;
        $guestId = 888001;

        session(['guest_id' => $guestId]);

        $this->app['db']->table('supplier_apis')->insert([
            'id' => 2,
            'name' => 'Golf API',
            'driver' => 'golf_api',
            'base_url' => 'https://example.com',
            'credentials' => '{}',
            'auth_type' => 'api_key',
            'is_active' => true,
            'rate_limit_per_minute' => 60,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->app['db']->table('supplier_product_mappings')->insert([
            'id' => $mappingId,
            'product_id' => $productId,
            'supplier_api_id' => 2,
            'supplier_product_id' => 'GOLF-456',
            'is_active' => true,
            'is_direct_topup' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->app['db']->table('products')->insert([
            'id' => $productId,
            'product_type' => 'digital',
            'digital_product_type' => 'ready_after_sell',
            'variation' => '[]',
            'current_stock' => 0,
            'minimum_order_qty' => 1,
            'unit_price' => 10,
            'status' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $cartId = $this->app['db']->table('carts')->insertGetId([
            'customer_id' => $guestId,
            'product_id' => $productId,
            'product_type' => 'digital',
            'variant' => null,
            'quantity' => 1,
            'price' => 10,
            'discount' => 0,
            'shipping_cost' => 0,
            'is_guest' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Cache::put('supplier_stock:'.$mappingId, 5, 3600);

        $response = CartManager::update_cart_qty(Request::create('/cart/updateQuantity-guest', 'POST', [
            'key' => $cartId,
            'quantity' => 10,
            'buy_now' => 0,
        ]));

        $this->assertSame(0, $response['status']);
        $this->assertSame(1, $response['qty']);
        $this->assertSame(translate('sorry_stock_is_limited'), $response['message']);
    }

    public function test_get_max_purchasable_quantity_uses_supplier_stock_for_mapped_digital_product(): void
    {
        $productId = 204;
        $mappingId = 3;

        $this->app['db']->table('supplier_apis')->insert([
            'id' => 3,
            'name' => 'Golf API',
            'driver' => 'golf_api',
            'base_url' => 'https://example.com',
            'credentials' => '{}',
            'auth_type' => 'api_key',
            'is_active' => true,
            'rate_limit_per_minute' => 60,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->app['db']->table('supplier_product_mappings')->insert([
            'id' => $mappingId,
            'product_id' => $productId,
            'supplier_api_id' => 3,
            'supplier_product_id' => 'GOLF-789',
            'is_active' => true,
            'is_direct_topup' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Cache::put('supplier_stock:'.$mappingId, 12, 3600);

        $product = Product::make([
            'product_type' => 'digital',
            'variation' => '[]',
            'current_stock' => 0,
        ]);
        $product->id = $productId;

        $mapping = SupplierProductMapping::query()->find($mappingId);
        $mapping->setRelation('supplierApi', SupplierApi::query()->find(3));
        $product->setRelation('supplierMapping', $mapping);

        $this->assertSame(12, CartManager::getMaxPurchasableQuantity($product));
    }

    public function test_mapped_digital_product_details_never_show_out_of_stock(): void
    {
        $productId = 205;
        $mappingId = 4;

        $this->app['db']->table('supplier_apis')->insert([
            'id' => 4,
            'name' => 'Golf API',
            'driver' => 'golf_api',
            'base_url' => 'https://example.com',
            'credentials' => '{}',
            'auth_type' => 'api_key',
            'is_active' => true,
            'rate_limit_per_minute' => 60,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->app['db']->table('supplier_product_mappings')->insert([
            'id' => $mappingId,
            'product_id' => $productId,
            'supplier_api_id' => 4,
            'supplier_product_id' => 'GOLF-000',
            'is_active' => true,
            'is_direct_topup' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Cache::put('supplier_stock:'.$mappingId, 0, 3600);

        $product = Product::make([
            'product_type' => 'digital',
            'variation' => '[]',
            'current_stock' => 0,
        ]);
        $product->id = $productId;

        $mapping = SupplierProductMapping::query()->find($mappingId);
        $mapping->setRelation('supplierApi', SupplierApi::query()->find(4));
        $product->setRelation('supplierMapping', $mapping);

        $presentation = CartManager::getProductDetailsStockPresentation($product);

        $this->assertFalse($presentation['show_out_of_stock']);
        $this->assertTrue($presentation['is_supplier_mapped']);
        $this->assertGreaterThan(0, $presentation['available_quantity']);
    }
}
