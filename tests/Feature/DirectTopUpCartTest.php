<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Utils\CartManager;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Tests\Concerns\ManagesTestDatabaseSchema;
use Tests\TestCase;

class DirectTopUpCartTest extends TestCase
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
            $table->timestamps();
        });

        $this->app['db']->table('supplier_apis')->insert([
            'id' => 1,
            'name' => 'Test Supplier',
            'driver' => 'generic_rest',
            'base_url' => 'https://example.com',
            'credentials' => '{}',
            'auth_type' => 'api_key',
            'is_active' => true,
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

        $this->recreateTable('stock_clearance_products', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('product_id')->nullable();
            $table->boolean('is_active')->default(false);
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

        $this->recreateTable('shops', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->nullable();
            $table->string('author_type')->nullable();
            $table->timestamps();
        });

        $this->app['db']->table('shops')->insert([
            'id' => 1,
            'name' => 'In House',
            'author_type' => 'admin',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->recreateTable('carts', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->unsignedBigInteger('product_id')->nullable();
            $table->string('product_type')->nullable();
            $table->string('digital_product_type')->nullable();
            $table->text('choices')->nullable();
            $table->text('variations')->nullable();
            $table->string('variant')->nullable();
            $table->integer('quantity')->default(1);
            $table->decimal('price', 24, 2)->default(0);
            $table->decimal('custom_amount', 24, 2)->nullable();
            $table->unsignedBigInteger('supplier_denomination_id')->nullable();
            $table->text('direct_topup_account_id')->nullable();
            $table->decimal('direct_topup_quantity', 24, 4)->nullable();
            $table->decimal('discount', 24, 2)->default(0);
            $table->boolean('is_checked')->default(1);
            $table->string('slug')->nullable();
            $table->string('name')->nullable();
            $table->string('thumbnail')->nullable();
            $table->unsignedBigInteger('seller_id')->nullable();
            $table->string('seller_is')->nullable();
            $table->string('shop_info')->nullable();
            $table->string('cart_group_id')->nullable();
            $table->decimal('shipping_cost', 24, 2)->default(0);
            $table->string('shipping_type')->nullable();
            $table->boolean('is_guest')->default(0);
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
            $table->boolean('is_direct_topup')->default(false);
            $table->string('direct_topup_account_label')->nullable();
            $table->decimal('direct_topup_min_quantity', 24, 4)->nullable();
            $table->decimal('direct_topup_max_quantity', 24, 4)->nullable();
            $table->decimal('direct_topup_price_per_unit', 24, 4)->nullable();
            $table->decimal('unit_price', 24, 2)->default(0);
            $table->integer('current_stock')->default(0);
            $table->text('variation')->nullable();
            $table->decimal('discount', 24, 2)->default(0);
            $table->string('discount_type')->nullable();
            $table->boolean('status')->default(1);
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

    public function test_direct_topup_rejects_empty_account_without_out_of_stock_message(): void
    {
        $productId = 501;

        $this->app['db']->table('supplier_product_mappings')->insert([
            'product_id' => $productId,
            'supplier_api_id' => 1,
            'supplier_product_id' => 'SUP-501',
            'cost_price' => 0.01,
            'markup_type' => 'percent',
            'markup_value' => 0,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $product = $this->makeDirectTopUpProduct($productId);

        $request = Request::create('/api/v1/cart/add', 'POST', [
            'quantity' => 1,
            'direct_topup_account_id' => '',
            'direct_topup_quantity' => 500,
        ]);

        $response = CartManager::addToCartDigitalProduct(
            request: $request,
            product: $product,
            shippingType: 'order_wise',
            sellerShippingList: null,
        );

        $this->assertSame(0, $response['status']);
        $this->assertNotSame(translate('out_of_stock!'), $response['message']);
    }

    public function test_direct_topup_rejects_quantity_below_minimum(): void
    {
        $productId = 502;

        $this->app['db']->table('supplier_product_mappings')->insert([
            'product_id' => $productId,
            'supplier_api_id' => 1,
            'supplier_product_id' => 'SUP-502',
            'cost_price' => 0.01,
            'markup_type' => 'percent',
            'markup_value' => 0,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $product = $this->makeDirectTopUpProduct($productId);

        $request = Request::create('/api/v1/cart/add', 'POST', [
            'quantity' => 1,
            'direct_topup_account_id' => 'player123',
            'direct_topup_quantity' => 50,
        ]);

        $response = CartManager::addToCartDigitalProduct(
            request: $request,
            product: $product,
            shippingType: 'order_wise',
            sellerShippingList: null,
        );

        $this->assertSame(0, $response['status']);
        $this->assertStringContainsString('100', $response['message']);
    }

    public function test_direct_topup_bypasses_digital_code_stock_gate(): void
    {
        $productId = 503;

        $product = $this->makeDirectTopUpProduct($productId);
        $product->is_direct_topup = false;
        $product->digital_product_type = 'ready_product';

        $request = Request::create('/api/v1/cart/add', 'POST', [
            'quantity' => 1,
        ]);

        $response = CartManager::addToCartDigitalProduct(
            request: $request,
            product: $product,
            shippingType: 'order_wise',
            sellerShippingList: null,
        );

        $this->assertSame(0, $response['status']);
        $this->assertSame(translate('out_of_stock!'), $response['message']);
    }

    public function test_direct_topup_adds_correct_quantity_and_total_price(): void
    {
        $productId = 504;

        $this->app['db']->table('supplier_product_mappings')->insert([
            'product_id' => $productId,
            'supplier_api_id' => 1,
            'supplier_product_id' => 'SUP-504',
            'cost_price' => 0.01,
            'markup_type' => 'percent',
            'markup_value' => 0,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $product = $this->makeDirectTopUpProduct($productId);

        session(['guest_id' => 999001]);

        $request = Request::create('/cart/add', 'POST', [
            'id' => $productId,
            'quantity' => 1,
            'direct_topup_account_id' => 'player123',
            'direct_topup_quantity' => 500,
        ]);

        $response = CartManager::addToCartDigitalProduct(
            request: $request,
            product: $product,
            shippingType: 'order_wise',
            sellerShippingList: null,
        );

        $this->assertSame(1, $response['status']);
        $this->assertSame(500.0, (float) $response['cart']['direct_topup_quantity']);
        $this->assertSame(1, (int) $response['cart']['quantity']);
        $this->assertSame(5.0, (float) $response['cart']['price']);
    }

    public function test_direct_topup_update_quantity_on_cart_page_endpoint(): void
    {
        $productId = 505;
        $guestId = 999002;

        $this->seedDirectTopUpProductMapping($productId, 'SUP-505');
        $product = $this->makeDirectTopUpProduct($productId);
        $this->persistProduct($product);

        session(['guest_id' => $guestId]);

        $addResponse = CartManager::addToCartDigitalProduct(
            request: Request::create('/cart/add', 'POST', [
                'id' => $productId,
                'quantity' => 1,
                'direct_topup_account_id' => 'player123',
                'direct_topup_quantity' => 500,
            ]),
            product: $product,
            shippingType: 'order_wise',
            sellerShippingList: null,
        );

        $cartId = (int) $addResponse['cart']['id'];
        $this->encryptCartAccountId($cartId, 'player123');

        $updateResponse = CartManager::update_cart_qty(Request::create('/cart/updateQuantity', 'POST', [
            'key' => $cartId,
            'quantity' => 1,
            'direct_topup_quantity' => 600,
            'direct_topup_account_id' => 'player123',
        ]));

        $this->assertSame(1, $updateResponse['status']);
        $this->assertSame(600.0, (float) $updateResponse['qty']);

        $cartRow = $this->app['db']->table('carts')->where('id', $cartId)->first();
        $this->assertNotNull($cartRow);
        $this->assertSame(600.0, (float) $cartRow->direct_topup_quantity);
        $this->assertSame(1, (int) $cartRow->quantity);
        $this->assertSame(6.0, (float) $cartRow->price);
    }

    public function test_direct_topup_update_quantity_with_guest_payload_shape(): void
    {
        $productId = 506;
        $guestId = 999003;

        $this->seedDirectTopUpProductMapping($productId, 'SUP-506');
        $product = $this->makeDirectTopUpProduct($productId);
        $this->persistProduct($product);

        session(['guest_id' => $guestId]);

        $addResponse = CartManager::addToCartDigitalProduct(
            request: Request::create('/cart/add', 'POST', [
                'id' => $productId,
                'quantity' => 1,
                'direct_topup_account_id' => 'player456',
                'direct_topup_quantity' => 500,
            ]),
            product: $product,
            shippingType: 'order_wise',
            sellerShippingList: null,
        );

        $cartId = (int) $addResponse['cart']['id'];
        $this->encryptCartAccountId($cartId, 'player456');

        $updateResponse = CartManager::update_cart_qty(Request::create('/cart/updateQuantity-guest', 'POST', [
            'key' => $cartId,
            'product_id' => $productId,
            'quantity' => 1,
            'direct_topup_quantity' => 700,
            'direct_topup_account_id' => 'player456',
        ]));

        $this->assertSame(1, $updateResponse['status']);
        $this->assertSame(700.0, (float) $updateResponse['qty']);

        $cartRow = $this->app['db']->table('carts')->where('id', $cartId)->first();
        $this->assertNotNull($cartRow);
        $this->assertSame(700.0, (float) $cartRow->direct_topup_quantity);
        $this->assertSame(1, (int) $cartRow->quantity);
        $this->assertSame(7.0, (float) $cartRow->price);
    }

    private function seedDirectTopUpProductMapping(int $productId, string $supplierProductId): void
    {
        $this->app['db']->table('supplier_product_mappings')->insert([
            'product_id' => $productId,
            'supplier_api_id' => 1,
            'supplier_product_id' => $supplierProductId,
            'cost_price' => 0.01,
            'markup_type' => 'percent',
            'markup_value' => 0,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function encryptCartAccountId(int $cartId, string $accountId): void
    {
        $this->app['db']->table('carts')->where('id', $cartId)->update([
            'direct_topup_account_id' => encrypt($accountId),
        ]);
    }

    private function persistProduct(Product $product): void
    {
        $this->app['db']->table('products')->insert([
            'id' => $product->id,
            'added_by' => $product->added_by,
            'name' => $product->name,
            'slug' => $product->slug,
            'code' => $product->code,
            'product_type' => $product->product_type,
            'digital_product_type' => $product->digital_product_type,
            'is_direct_topup' => $product->is_direct_topup,
            'direct_topup_account_label' => $product->direct_topup_account_label,
            'direct_topup_min_quantity' => $product->direct_topup_min_quantity,
            'direct_topup_max_quantity' => $product->direct_topup_max_quantity,
            'direct_topup_price_per_unit' => $product->direct_topup_price_per_unit,
            'unit_price' => $product->unit_price,
            'current_stock' => 0,
            'variation' => json_encode([]),
            'discount' => 0,
            'discount_type' => 'percent',
            'status' => $product->status,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function makeDirectTopUpProduct(int $productId): Product
    {
        $product = new Product([
            'added_by' => 'admin',
            'name' => 'Direct Top-up Product',
            'slug' => 'direct-topup-product-'.$productId,
            'code' => 'TOPUP'.$productId,
            'product_type' => 'digital',
            'digital_product_type' => 'ready_product',
            'is_direct_topup' => true,
            'direct_topup_account_label' => 'Player ID',
            'direct_topup_min_quantity' => 100,
            'direct_topup_max_quantity' => 10000,
            'direct_topup_price_per_unit' => 0.01,
            'unit_price' => 1,
            'status' => 1,
        ]);
        $product->id = $productId;

        return $product;
    }
}
