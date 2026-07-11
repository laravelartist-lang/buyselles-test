<?php

namespace Tests\Feature;

use App\Models\Product;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\ManagesTestDatabaseSchema;
use Tests\TestCase;

class DirectTopUpAccountValidationTest extends TestCase
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
            $table->boolean('supports_direct_top_up')->default(false);
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

        $this->recreateTable('guest_users', function (Blueprint $table): void {
            $table->id();
            $table->string('ip_address')->nullable();
            $table->string('fcm_token')->nullable();
            $table->timestamps();
        });
    }

    private function createApiGuestId(): int
    {
        return (int) $this->app['db']->table('guest_users')->insertGetId([
            'ip_address' => '127.0.0.1',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_validate_direct_topup_account_returns_valid_player(): void
    {
        $product = $this->createGolfDirectTopUpProduct();

        Http::fake([
            'api.golf-test.com/api/products/195' => Http::response([
                'status' => 'success',
                'result' => [
                    'data' => [
                        'id' => 195,
                        'api_type' => 'jawaker',
                        'custom_fields' => [
                            ['id' => 3, 'name' => 'Player ID', 'desc' => 'Player ID', 'sort' => 1],
                        ],
                    ],
                ],
            ]),
            'api.golf-test.com/api/order/jawaker/validate' => Http::response([
                'status' => 'success',
                'result' => [
                    'data' => [
                        'userId' => '12345',
                        'username' => 'GamerPro',
                    ],
                ],
                'message' => 'Player ID is valid',
            ]),
        ]);

        $response = $this->postJson(route('cart.validate-direct-topup-account'), [
            'product_id' => $product->id,
            'direct_topup_account_id' => '12345',
        ]);

        $response->assertOk()
            ->assertJson([
                'supported' => true,
                'valid' => true,
                'player_id' => '12345',
                'username' => 'GamerPro',
            ]);
    }

    public function test_validate_direct_topup_account_returns_invalid_player(): void
    {
        $product = $this->createGolfDirectTopUpProduct();

        Http::fake([
            'api.golf-test.com/api/products/195' => Http::response([
                'status' => 'success',
                'result' => [
                    'data' => [
                        'id' => 195,
                        'api_type' => 'jawaker',
                        'custom_fields' => [],
                    ],
                ],
            ]),
            'api.golf-test.com/api/order/jawaker/validate' => Http::response([
                'status' => 'error',
                'message' => 'Player ID is invalid',
                'result' => null,
            ], 422),
        ]);

        $response = $this->postJson(route('cart.validate-direct-topup-account'), [
            'product_id' => $product->id,
            'direct_topup_account_id' => 'bad-id',
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'supported' => true,
                'valid' => false,
                'message' => 'Player ID is invalid',
            ]);
    }

    public function test_validate_direct_topup_account_skips_for_non_golf_supplier(): void
    {
        $product = Product::create([
            'added_by' => 'admin',
            'name' => 'Generic Direct Top-up',
            'slug' => 'generic-direct-topup',
            'code' => 'GEN001',
            'product_type' => 'digital',
            'digital_product_type' => 'ready_product',
            'unit_price' => 1,
            'status' => 1,
        ]);

        $supplierId = $this->app['db']->table('supplier_apis')->insertGetId([
            'name' => 'Generic Supplier',
            'driver' => 'generic_rest',
            'base_url' => 'https://example.com',
            'credentials' => Crypt::encryptString(json_encode(['api_key' => 'test'])),
            'auth_type' => 'api_key',
            'is_active' => true,
            'supports_direct_top_up' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->app['db']->table('supplier_product_mappings')->insert([
            'product_id' => $product->id,
            'supplier_api_id' => $supplierId,
            'supplier_product_id' => 'GEN-001',
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

        Http::fake();

        $response = $this->postJson(route('cart.validate-direct-topup-account'), [
            'product_id' => $product->id,
            'direct_topup_account_id' => 'player123',
        ]);

        $response->assertOk()
            ->assertJson([
                'supported' => false,
                'valid' => true,
            ]);

        Http::assertNothingSent();
    }

    public function test_api_validate_direct_topup_account_returns_valid_player(): void
    {
        $product = $this->createGolfDirectTopUpProduct();

        Http::fake([
            'api.golf-test.com/api/products/195' => Http::response([
                'status' => 'success',
                'result' => [
                    'data' => [
                        'id' => 195,
                        'api_type' => 'jawaker',
                        'custom_fields' => [
                            ['id' => 3, 'name' => 'Player ID', 'desc' => 'Player ID', 'sort' => 1],
                        ],
                    ],
                ],
            ]),
            'api.golf-test.com/api/order/jawaker/validate' => Http::response([
                'status' => 'success',
                'result' => [
                    'data' => [
                        'userId' => '12345',
                        'username' => 'GamerPro',
                    ],
                ],
                'message' => 'Player ID is valid',
            ]),
        ]);

        $response = $this->postJson('/api/v1/cart/validate-direct-topup-account', [
            'guest_id' => $this->createApiGuestId(),
            'product_id' => $product->id,
            'direct_topup_account_id' => '12345',
        ]);

        $response->assertOk()
            ->assertJson([
                'supported' => true,
                'valid' => true,
                'player_id' => '12345',
                'username' => 'GamerPro',
            ]);
    }

    public function test_api_validate_direct_topup_account_returns_invalid_player(): void
    {
        $product = $this->createGolfDirectTopUpProduct();

        Http::fake([
            'api.golf-test.com/api/products/195' => Http::response([
                'status' => 'success',
                'result' => [
                    'data' => [
                        'id' => 195,
                        'api_type' => 'jawaker',
                        'custom_fields' => [],
                    ],
                ],
            ]),
            'api.golf-test.com/api/order/jawaker/validate' => Http::response([
                'status' => 'error',
                'message' => 'Player ID is invalid',
                'result' => null,
            ], 422),
        ]);

        $response = $this->postJson('/api/v1/cart/validate-direct-topup-account', [
            'guest_id' => $this->createApiGuestId(),
            'product_id' => $product->id,
            'direct_topup_account_id' => 'bad-id',
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'supported' => true,
                'valid' => false,
                'message' => 'Player ID is invalid',
            ]);
    }

    public function test_api_validate_direct_topup_account_requires_product_id(): void
    {
        $response = $this->postJson('/api/v1/cart/validate-direct-topup-account', [
            'guest_id' => $this->createApiGuestId(),
            'direct_topup_account_id' => '12345',
        ]);

        $response->assertStatus(403)
            ->assertJsonStructure(['errors']);
    }

    private function createGolfDirectTopUpProduct(): Product
    {
        $product = Product::create([
            'added_by' => 'admin',
            'name' => 'Jawaker Top-up',
            'slug' => 'jawaker-topup',
            'code' => 'JAW001',
            'product_type' => 'digital',
            'digital_product_type' => 'ready_product',
            'unit_price' => 1,
            'status' => 1,
        ]);

        $supplierId = $this->app['db']->table('supplier_apis')->insertGetId([
            'name' => 'Golf API',
            'driver' => 'golf_api',
            'base_url' => 'https://api.golf-test.com/api',
            'credentials' => Crypt::encryptString(json_encode(['api_token' => 'test-token'])),
            'auth_type' => 'bearer_token',
            'is_active' => true,
            'supports_direct_top_up' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->app['db']->table('supplier_product_mappings')->insert([
            'product_id' => $product->id,
            'supplier_api_id' => $supplierId,
            'supplier_product_id' => '195',
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
