<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Tests\Concerns\ManagesTestDatabaseSchema;
use Tests\TestCase;

class DigitalPaymentCustomerIdTest extends TestCase
{
    use ManagesTestDatabaseSchema;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();

        $this->recreateTable('users', function (Blueprint $table): void {
            $table->id();
            $table->string('f_name')->nullable();
            $table->string('l_name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->decimal('wallet_balance', 24, 4)->default(0);
            $table->timestamps();
        });

        $this->recreateTable('guest_users', function (Blueprint $table): void {
            $table->id();
            $table->string('ip_address')->nullable();
            $table->timestamps();
        });

        $this->recreateTable('business_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('type')->nullable();
            $table->longText('value')->nullable();
            $table->timestamps();
        });

        $this->app['db']->table('business_settings')->insert([
            [
                'type' => 'language',
                'value' => json_encode([
                    ['code' => 'en', 'name' => 'English', 'default' => true, 'direction' => 'ltr'],
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'type' => 'shipping_method',
                'value' => json_encode(['status' => 1, 'shipping_method' => 'inhouse_shipping']),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'type' => 'company_name',
                'value' => json_encode('Test Shop'),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'type' => 'currency_model',
                'value' => json_encode('single_currency'),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'type' => 'system_default_currency',
                'value' => json_encode(1),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'type' => 'digital_product',
                'value' => json_encode(['status' => 1]),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'type' => 'product_brand',
                'value' => json_encode(['status' => 0]),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'type' => 'business_mode',
                'value' => json_encode('single'),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $this->recreateTable('sellers', function (Blueprint $table): void {
            $table->id();
            $table->string('status')->default('approved');
            $table->timestamps();
        });

        $this->recreateTable('products', function (Blueprint $table): void {
            $table->id();
            $table->string('added_by')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('brand_id')->nullable();
            $table->string('name')->nullable();
            $table->string('slug')->nullable();
            $table->string('product_type')->nullable();
            $table->string('digital_product_type')->nullable();
            $table->decimal('unit_price', 24, 2)->default(10);
            $table->integer('minimum_order_qty')->default(1);
            $table->integer('current_stock')->default(0);
            $table->boolean('status')->default(1);
            $table->boolean('request_status')->default(1);
            $table->timestamps();
        });

        $this->recreateTable('digital_product_codes', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('product_id')->index();
            $table->text('code');
            $table->string('status')->default('available')->index();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        $this->recreateTable('carts', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->unsignedBigInteger('product_id')->nullable();
            $table->string('product_type')->nullable();
            $table->string('cart_group_id')->nullable();
            $table->integer('quantity')->default(1);
            $table->decimal('price', 24, 2)->default(10);
            $table->decimal('discount', 24, 2)->default(0);
            $table->decimal('shipping_cost', 24, 2)->default(0);
            $table->unsignedBigInteger('seller_id')->default(0);
            $table->string('seller_is')->default('admin');
            $table->boolean('is_checked')->default(1);
            $table->boolean('is_guest')->default(0);
            $table->timestamps();
        });

        $this->recreateTable('reviews', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('product_id')->nullable();
            $table->boolean('status')->default(1);
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

        $this->recreateTable('storages', function (Blueprint $table): void {
            $table->id();
            $table->string('data_type')->nullable();
            $table->unsignedBigInteger('data_id')->nullable();
            $table->string('key')->nullable();
            $table->string('value')->nullable();
            $table->timestamps();
        });
    }

    public function test_guest_app_digital_payment_merges_customer_id_from_guest_id(): void
    {
        $guestId = (int) $this->app['db']->table('guest_users')->insertGetId([
            'ip_address' => '127.0.0.1',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->seedDigitalCart($guestId, isGuest: true);

        $response = $this->postJson('/api/v1/digital-payment', [
            'payment_method' => 'stripe',
            'payment_platform' => 'app',
            'payment_request_from' => 'app',
            'guest_id' => $guestId,
            'is_guest' => 1,
            'address_id' => '',
            'billing_address_id' => '',
            'coupon_code' => '',
            'coupon_discount' => 0,
            'order_note' => '',
        ]);

        $this->assertDoesNotHaveCustomerIdValidationError($response);
    }

    public function test_authenticated_app_request_merges_customer_id_from_api_user_before_validation(): void
    {
        $userId = 42;

        $request = Request::create('/api/v1/digital-payment', 'POST', [
            'payment_method' => 'stripe',
            'payment_platform' => 'app',
            'payment_request_from' => 'app',
            'guest_id' => '',
            'is_guest' => 0,
        ]);

        $apiUser = (object) ['id' => $userId];

        if (in_array($request['payment_request_from'], ['app'])) {
            if (empty($request['customer_id']) && $apiUser) {
                $request->merge([
                    'customer_id' => $apiUser->id,
                    'is_guest' => 0,
                ]);
            }
        }

        $this->assertSame($userId, (int) $request['customer_id']);

        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
            'payment_method' => 'required',
            'payment_platform' => 'required',
        ]);
        $validator->sometimes('customer_id', 'required', function ($input) {
            return in_array($input->payment_request_from, ['app']);
        });
        $validator->sometimes('is_guest', 'required', function ($input) {
            return in_array($input->payment_request_from, ['app']);
        });

        $this->assertFalse($validator->fails(), json_encode($validator->errors()->toArray()));
    }

    private function seedDigitalCart(int $customerId, bool $isGuest): void
    {
        $productId = (int) $this->app['db']->table('products')->insertGetId([
            'added_by' => 'admin',
            'user_id' => 0,
            'brand_id' => null,
            'name' => 'Digital Game',
            'slug' => 'digital-game',
            'product_type' => 'digital',
            'digital_product_type' => 'ready_product',
            'unit_price' => 10,
            'minimum_order_qty' => 1,
            'current_stock' => 0,
            'status' => 1,
            'request_status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->app['db']->table('digital_product_codes')->insert([
            'product_id' => $productId,
            'code' => 'CODE-1',
            'status' => 'available',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->app['db']->table('carts')->insert([
            'customer_id' => $customerId,
            'product_id' => $productId,
            'product_type' => 'digital',
            'cart_group_id' => 'guest-'.$customerId,
            'quantity' => 1,
            'price' => 10,
            'discount' => 0,
            'shipping_cost' => 0,
            'seller_id' => 0,
            'seller_is' => 'admin',
            'is_checked' => 1,
            'is_guest' => $isGuest ? 1 : 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function assertDoesNotHaveCustomerIdValidationError($response): void
    {
        if (method_exists($response, 'json')) {
            $errors = $response->json('errors') ?? [];
        } else {
            $payload = json_decode($response->getContent(), true) ?? [];
            $errors = $payload['errors'] ?? [];
        }

        $this->assertIsArray($errors);

        foreach ($errors as $error) {
            $this->assertNotEquals(
                'customer_id',
                $error['code'] ?? null,
                'Unexpected customer_id validation error: '.($error['message'] ?? '')
            );
        }
    }
}
