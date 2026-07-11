<?php

namespace Tests\Unit;

use App\Models\Product;
use App\Services\DirectTopUp\DirectTopUpService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
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

    public function test_is_direct_topup_product_when_mapping_has_label_without_supplier_support_flag(): void
    {
        $product = Product::create([
            'added_by' => 'admin',
            'name' => 'Zain GSM Direct Top-up',
            'slug' => 'zain-gsm-direct-topup',
            'code' => 'ZAIN001',
            'product_type' => 'digital',
            'digital_product_type' => 'ready_after_sell',
            'unit_price' => 1.72,
            'status' => 1,
        ]);

        $supplierId = $this->app['db']->table('supplier_apis')->insertGetId([
            'name' => 'Zain Supplier',
            'driver' => 'generic_rest',
            'is_active' => true,
            'supports_direct_top_up' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->app['db']->table('supplier_product_mappings')->insert([
            'product_id' => $product->id,
            'supplier_api_id' => $supplierId,
            'supplier_product_id' => 'ZAIN-GSM-1',
            'cost_price' => 1.62,
            'markup_type' => 'percent',
            'markup_value' => 0,
            'is_active' => true,
            'is_direct_topup' => true,
            'direct_topup_account_label' => 'Mobile Number',
            'direct_topup_min_quantity' => 1,
            'direct_topup_max_quantity' => 100,
            'direct_topup_price_per_unit' => 1.72,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $product->load(['supplierMapping.supplierApi']);

        $this->assertTrue($this->service->isDirectTopUpProduct($product));
        $this->assertTrue($product->is_direct_topup);

        $payload = $this->service->buildApiPayload($product);

        $this->assertNotNull($payload);
        $this->assertTrue($payload['enabled']);
        $this->assertSame('Mobile Number', $payload['account_label']);
    }

    public function test_requires_account_verification_is_true_for_golf_api_supplier(): void
    {
        $product = $this->makeGolfDirectTopUpProduct();

        $this->assertTrue($this->service->requiresAccountVerification($product));
    }

    public function test_validate_account_with_supplier_skips_for_non_golf_supplier(): void
    {
        $product = $this->makeDirectTopUpProduct();

        Http::fake();

        $result = $this->service->validateAccountWithSupplier($product, 'player123');

        $this->assertFalse($result['supported']);
        $this->assertTrue($result['valid']);
        Http::assertNothingSent();
    }

    public function test_validate_account_with_supplier_returns_valid_for_golf_jawaker_player(): void
    {
        $product = $this->makeGolfDirectTopUpProduct();

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

        $result = $this->service->validateAccountWithSupplier($product, '12345');

        $this->assertTrue($result['supported']);
        $this->assertTrue($result['valid']);
        $this->assertSame('12345', $result['player_id']);
        $this->assertSame('GamerPro', $result['username']);
    }

    public function test_validate_account_with_supplier_returns_invalid_for_bad_player(): void
    {
        $product = $this->makeGolfDirectTopUpProduct();

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

        $result = $this->service->validateAccountWithSupplier($product, 'bad-id');

        $this->assertTrue($result['supported']);
        $this->assertFalse($result['valid']);
        $this->assertSame('Player ID is invalid', $result['message']);
    }

    public function test_validate_purchase_rejects_invalid_golf_player_id(): void
    {
        $product = $this->makeGolfDirectTopUpProduct();

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

        $errors = $this->service->validatePurchase($product, 'bad-id', 500);

        $this->assertArrayHasKey('direct_topup_account_id', $errors);
        $this->assertSame('Player ID is invalid', $errors['direct_topup_account_id']);
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
            'supports_direct_top_up' => true,
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

    private function makeGolfDirectTopUpProduct(): Product
    {
        $product = Product::create([
            'added_by' => 'admin',
            'name' => 'Jawaker Top-up',
            'slug' => 'jawaker-topup-service-test',
            'code' => 'JAWSRV001',
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
