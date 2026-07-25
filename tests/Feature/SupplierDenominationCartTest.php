<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Utils\CartManager;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Tests\Concerns\ManagesTestDatabaseSchema;
use Tests\TestCase;

class SupplierDenominationCartTest extends TestCase
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
            $table->decimal('cost_price', 24, 10)->default(0);
            $table->string('markup_type')->default('percent');
            $table->decimal('markup_value', 10, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_customizable')->default(false);
            $table->boolean('is_direct_topup')->default(false);
            $table->decimal('min_amount', 24, 2)->nullable();
            $table->decimal('max_amount', 24, 2)->nullable();
            $table->timestamps();
        });

        $this->recreateTable('supplier_product_denominations', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('supplier_product_mapping_id')->index();
            $table->string('supplier_product_id')->nullable();
            $table->string('name')->nullable();
            $table->string('type')->default('fixed');
            $table->decimal('face_value', 24, 2)->nullable();
            $table->decimal('min_face_value', 24, 2)->nullable();
            $table->decimal('max_face_value', 24, 2)->nullable();
            $table->string('face_value_currency')->default('USD');
            $table->decimal('cost_price', 24, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
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
            $table->decimal('unit_price', 24, 2)->default(0);
            $table->integer('minimum_order_qty')->default(1);
            $table->integer('current_stock')->default(0);
            $table->text('variation')->nullable();
            $table->decimal('discount', 24, 2)->default(0);
            $table->string('discount_type')->nullable();
            $table->boolean('status')->default(1);
            $table->timestamps();
        });

        $this->recreateTable('digital_product_variations', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('product_id')->nullable();
            $table->string('variant_key')->nullable();
            $table->decimal('price', 24, 2)->default(0);
            $table->timestamps();
        });

        $this->recreateTable('reviews', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('product_id')->nullable();
            $table->boolean('status')->default(1);
            $table->timestamps();
        });
    }

    public function test_add_to_cart_infers_default_denomination_when_variable_amount_disabled(): void
    {
        $productId = 48;
        $mappingId = $this->seedPubgStyleMapping($productId, isCustomizable: false);
        $matchedDenomId = $this->seedFixedDenomination($mappingId, '3313615', 1.0, 0);
        $this->seedFixedDenomination($mappingId, '3313616', 5.0, 1);

        $product = $this->makeDigitalProduct($productId);
        session(['guest_id' => 88001]);

        $request = Request::create('/api/v1/cart/add', 'POST', [
            'id' => $productId,
            'quantity' => 1,
        ]);

        $response = CartManager::addToCartDigitalProduct(
            request: $request,
            product: $product,
            shippingType: 'order_wise',
            sellerShippingList: null,
        );

        $this->assertSame(1, $response['status']);

        $cart = $this->app['db']->table('carts')->where('product_id', $productId)->first();
        $this->assertNotNull($cart);
        $this->assertSame($matchedDenomId, (int) $cart->supplier_denomination_id);
        $this->assertEquals(1.0, (float) $cart->custom_amount);
    }

    public function test_add_to_cart_requires_denomination_when_customizable(): void
    {
        $productId = 49;
        $mappingId = $this->seedPubgStyleMapping($productId, isCustomizable: true);
        $this->seedFixedDenomination($mappingId, '3313615', 1.0, 0);

        $product = $this->makeDigitalProduct($productId);

        $request = Request::create('/api/v1/cart/add', 'POST', [
            'id' => $productId,
            'quantity' => 1,
        ]);

        $response = CartManager::addToCartDigitalProduct(
            request: $request,
            product: $product,
            shippingType: 'order_wise',
            sellerShippingList: null,
        );

        $this->assertSame(0, $response['status']);
    }

    public function test_add_to_cart_requires_custom_amount_for_variable_denomination(): void
    {
        $productId = 50;
        $mappingId = $this->seedPubgStyleMapping($productId, isCustomizable: true);

        $variableDenomId = $this->app['db']->table('supplier_product_denominations')->insertGetId([
            'supplier_product_mapping_id' => $mappingId,
            'supplier_product_id' => 'VAR-1',
            'name' => 'Variable',
            'type' => 'variable',
            'min_face_value' => 10,
            'max_face_value' => 100,
            'face_value_currency' => 'USD',
            'is_active' => true,
            'sort_order' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $product = $this->makeDigitalProduct($productId);

        $request = Request::create('/api/v1/cart/add', 'POST', [
            'id' => $productId,
            'quantity' => 1,
            'supplier_denomination_id' => $variableDenomId,
        ]);

        $response = CartManager::addToCartDigitalProduct(
            request: $request,
            product: $product,
            shippingType: 'order_wise',
            sellerShippingList: null,
        );

        $this->assertSame(0, $response['status']);
    }

    private function seedPubgStyleMapping(int $productId, bool $isCustomizable): int
    {
        $this->app['db']->table('products')->insert([
            'id' => $productId,
            'added_by' => 'admin',
            'name' => 'PUBG UC',
            'slug' => 'pubg-'.$productId,
            'code' => 'PUBG'.$productId,
            'product_type' => 'digital',
            'digital_product_type' => 'ready_product',
            'unit_price' => 1,
            'minimum_order_qty' => 1,
            'current_stock' => 0,
            'variation' => json_encode([]),
            'discount' => 0,
            'discount_type' => 'percent',
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $this->app['db']->table('supplier_product_mappings')->insertGetId([
            'product_id' => $productId,
            'supplier_api_id' => 1,
            'supplier_product_id' => '3313615',
            'cost_price' => 0.9,
            'markup_type' => 'percent',
            'markup_value' => 12,
            'is_active' => true,
            'is_customizable' => $isCustomizable,
            'is_direct_topup' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function seedFixedDenomination(int $mappingId, string $supplierProductId, float $faceValue, int $sortOrder): int
    {
        return $this->app['db']->table('supplier_product_denominations')->insertGetId([
            'supplier_product_mapping_id' => $mappingId,
            'supplier_product_id' => $supplierProductId,
            'name' => 'Denom '.$faceValue,
            'type' => 'fixed',
            'face_value' => $faceValue,
            'face_value_currency' => 'USD',
            'cost_price' => $faceValue * 0.9,
            'is_active' => true,
            'sort_order' => $sortOrder,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function makeDigitalProduct(int $productId): Product
    {
        $product = new Product([
            'added_by' => 'admin',
            'name' => 'PUBG UC',
            'slug' => 'pubg-'.$productId,
            'code' => 'PUBG'.$productId,
            'product_type' => 'digital',
            'digital_product_type' => 'ready_product',
            'unit_price' => 1,
            'minimum_order_qty' => 1,
            'variation' => '[]',
            'status' => 1,
        ]);
        $product->id = $productId;

        return $product;
    }
}
