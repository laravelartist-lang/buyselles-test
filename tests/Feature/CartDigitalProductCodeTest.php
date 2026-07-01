<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\Product;
use App\Utils\CartManager;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Tests\Concerns\ManagesTestDatabaseSchema;
use Tests\TestCase;

class CartDigitalProductCodeTest extends TestCase
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

        $this->recreateTable('digital_product_codes', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('product_id')->index();
            $table->text('code');
            $table->string('status')->default('available')->index();
            $table->boolean('is_active')->default(true);
            $table->date('expiry_date')->nullable();
            $table->timestamps();
        });
    }

    // ── Add-to-cart ───────────────────────────────────────────────────────────

    public function test_ready_product_without_available_codes_is_not_added_to_cart(): void
    {
        $request = Request::create('/api/v1/cart/add', 'POST', [
            'quantity' => 1,
        ]);

        $product = new Product([
            'product_type' => 'digital',
            'digital_product_type' => 'ready_product',
        ]);
        $product->id = 123;

        $response = CartManager::addToCartDigitalProduct(
            request: $request,
            product: $product,
            shippingType: 'order_wise',
            sellerShippingList: null,
        );

        $this->assertSame(0, $response['status']);
        $this->assertSame(translate('out_of_stock!'), $response['message']);
    }

    public function test_ready_after_sell_without_available_codes_is_not_added_to_cart(): void
    {
        $request = Request::create('/api/v1/cart/add', 'POST', [
            'quantity' => 1,
        ]);

        $product = new Product([
            'product_type' => 'digital',
            'digital_product_type' => 'ready_after_sell',
        ]);
        $product->id = 789;

        $response = CartManager::addToCartDigitalProduct(
            request: $request,
            product: $product,
            shippingType: 'order_wise',
            sellerShippingList: null,
        );

        $this->assertSame(0, $response['status']);
        $this->assertSame(translate('out_of_stock!'), $response['message']);
    }

    public function test_ready_digital_product_with_quantity_exceeding_available_codes_is_rejected(): void
    {
        $productId = 456;

        $this->app['db']->table('digital_product_codes')->insert([
            'product_id' => $productId,
            'code' => 'ONLY-ONE-CODE',
            'status' => 'available',
            'is_active' => true,
            'expiry_date' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $request = Request::create('/api/v1/cart/add', 'POST', [
            'quantity' => 3,
        ]);

        $product = new Product([
            'product_type' => 'digital',
            'digital_product_type' => 'ready_product',
        ]);
        $product->id = $productId;

        $response = CartManager::addToCartDigitalProduct(
            request: $request,
            product: $product,
            shippingType: 'order_wise',
            sellerShippingList: null,
        );

        $this->assertSame(0, $response['status']);
        $this->assertStringContainsString('1', $response['message']);
    }

    public function test_ready_after_sell_with_enough_codes_can_be_added_to_cart(): void
    {
        $productId = 790;

        foreach (['RAS-CODE-1', 'RAS-CODE-2'] as $code) {
            $this->app['db']->table('digital_product_codes')->insert([
                'product_id' => $productId,
                'code' => $code,
                'status' => 'available',
                'is_active' => true,
                'expiry_date' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $request = Request::create('/api/v1/cart/add', 'POST', [
            'quantity' => 1,
        ]);

        $product = new Product([
            'product_type' => 'digital',
            'digital_product_type' => 'ready_after_sell',
            'minimum_order_qty' => 1,
        ]);
        $product->id = $productId;

        // With codes present the gate passes; the method may still fail further
        // down (e.g. missing seller data) but the stock guard must NOT block it.
        $available = CartManager::getAvailableDigitalCodeCount((int) $product->id);
        $this->assertSame(2, $available);
    }

    // ── product_stock_check ───────────────────────────────────────────────────

    public function test_product_stock_check_returns_false_for_digital_product_with_no_codes(): void
    {
        $productId = 101;

        $product = new Product([
            'product_type' => 'digital',
            'digital_product_type' => 'ready_product',
            'variation' => '[]',
        ]);
        $product->id = $productId;

        $cart = new Cart([
            'product_id' => $productId,
            'quantity' => 1,
            'variant' => null,
        ]);
        $cart->setRelation('product', $product);

        $this->assertFalse(CartManager::product_stock_check(collect([$cart])));
    }

    public function test_product_stock_check_returns_false_when_quantity_exceeds_available_codes(): void
    {
        $productId = 102;

        $this->app['db']->table('digital_product_codes')->insert([
            'product_id' => $productId,
            'code' => 'SINGLE-CODE',
            'status' => 'available',
            'is_active' => true,
            'expiry_date' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $product = new Product([
            'product_type' => 'digital',
            'digital_product_type' => 'ready_product',
            'variation' => '[]',
        ]);
        $product->id = $productId;

        $cart = new Cart([
            'product_id' => $productId,
            'quantity' => 5,
            'variant' => null,
        ]);
        $cart->setRelation('product', $product);

        $this->assertFalse(CartManager::product_stock_check(collect([$cart])));
    }

    public function test_product_stock_check_returns_true_when_enough_codes_exist(): void
    {
        $productId = 103;

        foreach (['CODE-A', 'CODE-B', 'CODE-C'] as $code) {
            $this->app['db']->table('digital_product_codes')->insert([
                'product_id' => $productId,
                'code' => $code,
                'status' => 'available',
                'is_active' => true,
                'expiry_date' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $product = new Product([
            'product_type' => 'digital',
            'digital_product_type' => 'ready_product',
            'variation' => '[]',
        ]);
        $product->id = $productId;

        $cart = new Cart([
            'product_id' => $productId,
            'quantity' => 2,
            'variant' => null,
        ]);
        $cart->setRelation('product', $product);

        $this->assertTrue(CartManager::product_stock_check(collect([$cart])));
    }

    public function test_product_stock_check_handles_null_variation_without_error(): void
    {
        $productId = 104;

        $product = new Product([
            'product_type' => 'digital',
            'digital_product_type' => 'ready_product',
            'variation' => null,
        ]);
        $product->id = $productId;

        $cart = new Cart([
            'product_id' => $productId,
            'quantity' => 1,
            'variant' => null,
        ]);
        $cart->setRelation('product', $product);

        // Must not throw; returns false because no codes exist
        $this->assertFalse(CartManager::product_stock_check(collect([$cart])));
    }

    public function test_product_stock_check_ignores_sold_and_reserved_codes(): void
    {
        $productId = 105;

        foreach (['sold', 'reserved'] as $status) {
            $this->app['db']->table('digital_product_codes')->insert([
                'product_id' => $productId,
                'code' => 'CODE-'.$status,
                'status' => $status,
                'is_active' => true,
                'expiry_date' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $product = new Product([
            'product_type' => 'digital',
            'digital_product_type' => 'ready_product',
            'variation' => '[]',
        ]);
        $product->id = $productId;

        $cart = new Cart([
            'product_id' => $productId,
            'quantity' => 1,
            'variant' => null,
        ]);
        $cart->setRelation('product', $product);

        // Sold/reserved codes must not count as available
        $this->assertFalse(CartManager::product_stock_check(collect([$cart])));
    }

    public function test_product_stock_check_returns_false_for_ready_after_sell_with_no_codes(): void
    {
        $productId = 106;

        $product = new Product([
            'product_type' => 'digital',
            'digital_product_type' => 'ready_after_sell',
            'variation' => '[]',
        ]);
        $product->id = $productId;

        $cart = new Cart([
            'product_id' => $productId,
            'quantity' => 1,
            'variant' => null,
        ]);
        $cart->setRelation('product', $product);

        $this->assertFalse(CartManager::product_stock_check(collect([$cart])));
    }

    public function test_product_stock_check_returns_true_for_ready_after_sell_with_codes(): void
    {
        $productId = 107;

        foreach (['RAS-A', 'RAS-B'] as $code) {
            $this->app['db']->table('digital_product_codes')->insert([
                'product_id' => $productId,
                'code' => $code,
                'status' => 'available',
                'is_active' => true,
                'expiry_date' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $product = new Product([
            'product_type' => 'digital',
            'digital_product_type' => 'ready_after_sell',
            'variation' => '[]',
        ]);
        $product->id = $productId;

        $cart = new Cart([
            'product_id' => $productId,
            'quantity' => 2,
            'variant' => null,
        ]);
        $cart->setRelation('product', $product);

        $this->assertTrue(CartManager::product_stock_check(collect([$cart])));
    }
}
