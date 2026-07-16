<?php

namespace Tests\Feature;

use App\Models\BusinessSetting;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\SupplierApi;
use App\Models\SupplierOrder;
use App\Models\SupplierProductMapping;
use App\Services\Supplier\Drivers\GenericRestDriver;
use App\Services\Supplier\Presets\SecretOrcaPreset;
use App\Services\Supplier\SupplierCatalogSyncService;
use App\Services\Supplier\SupplierManager;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\Concerns\ManagesTestDatabaseSchema;
use Tests\TestCase;

class GenericRestDriverSecretOrcaTest extends TestCase
{
    use ManagesTestDatabaseSchema;

    protected function setUp(): void
    {
        parent::setUp();

        $this->recreateTable('business_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('type');
            $table->text('value')->nullable();
            $table->timestamps();
        });

        $this->recreateTable('supplier_apis', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->nullable();
            $table->string('driver')->default('generic_rest');
            $table->string('base_url')->nullable();
            $table->string('auth_type')->default('api_key');
            $table->text('credentials')->nullable();
            $table->json('settings')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('supports_direct_top_up')->default(true);
            $table->boolean('is_sandbox')->default(true);
            $table->string('health_status')->default('healthy');
            $table->integer('rate_limit_per_minute')->default(120);
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

        $this->recreateTable('supplier_api_logs', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('supplier_api_id');
            $table->string('action', 50)->nullable();
            $table->string('endpoint', 500)->nullable();
            $table->string('method', 10)->default('GET');
            $table->json('request_payload')->nullable();
            $table->json('response_payload')->nullable();
            $table->unsignedSmallInteger('http_status_code')->nullable();
            $table->unsignedInteger('response_time_ms')->nullable();
            $table->string('status')->default('success');
            $table->text('error_message')->nullable();
            $table->unsignedBigInteger('order_id')->nullable();
            $table->timestamp('created_at')->nullable();
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
            $table->decimal('purchase_price', 24, 2)->default(0);
            $table->integer('minimum_order_qty')->default(1);
            $table->integer('current_stock')->default(0);
            $table->boolean('status')->default(true);
            $table->timestamps();
        });

        $this->recreateTable('reviews', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('product_id')->nullable();
            $table->unsignedBigInteger('delivery_man_id')->nullable();
            $table->boolean('status')->default(true);
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

        $this->recreateTable('supplier_product_mappings', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('supplier_api_id');
            $table->string('supplier_product_id');
            $table->decimal('cost_price', 24, 10)->default(0);
            $table->string('cost_currency', 3)->default('USD');
            $table->string('markup_type')->default('percent');
            $table->decimal('markup_value', 24, 2)->default(0);
            $table->integer('priority')->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_direct_topup')->default(true);
            $table->string('direct_topup_account_label')->nullable();
            $table->string('direct_topup_region', 2)->nullable();
            $table->decimal('direct_topup_bundle_quantity', 20, 4)->nullable();
            $table->timestamps();
        });

        $this->recreateTable('orders', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->string('payment_status')->default('paid');
            $table->string('order_status')->default('confirmed');
            $table->timestamps();
        });

        $this->recreateTable('order_details', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('order_id');
            $table->unsignedBigInteger('product_id')->nullable();
            $table->decimal('direct_topup_quantity', 24, 4)->nullable();
            $table->text('direct_topup_account_id')->nullable();
            $table->decimal('custom_amount', 24, 2)->nullable();
            $table->timestamps();
        });

        $this->recreateTable('supplier_orders', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('supplier_api_id');
            $table->unsignedBigInteger('supplier_product_mapping_id');
            $table->unsignedBigInteger('order_id')->nullable();
            $table->unsignedBigInteger('order_detail_id')->nullable();
            $table->string('supplier_order_id')->nullable();
            $table->integer('quantity')->default(1);
            $table->decimal('cost_per_unit', 24, 2)->default(0);
            $table->decimal('total_cost', 24, 2)->default(0);
            $table->string('cost_currency', 3)->default('USD');
            $table->string('status')->default('pending');
            $table->text('codes_received')->nullable();
            $table->timestamp('fulfilled_at')->nullable();
            $table->string('failed_reason')->nullable();
            $table->integer('attempt_count')->default(0);
            $table->timestamps();
        });

        BusinessSetting::query()->create(['type' => 'decimal_point_settings', 'value' => '2']);
        BusinessSetting::query()->create([
            'type' => 'language',
            'value' => json_encode([['code' => 'en', 'default' => true, 'direction' => 'ltr']]),
        ]);
    }

    private function makeSupplier(): SupplierApi
    {
        $supplier = SupplierApi::query()->create([
            'name' => 'SecretOrca',
            'driver' => 'generic_rest',
            'base_url' => 'https://secretorca.test',
            'auth_type' => 'api_key',
            'settings' => SecretOrcaPreset::settings(),
            'is_active' => true,
            'supports_direct_top_up' => true,
            'health_status' => 'healthy',
            'rate_limit_per_minute' => 120,
        ]);
        $supplier->setEncryptedCredentials(['api_key' => 'sk_test_example']);
        $supplier->save();

        return $supplier;
    }

    public function test_fetch_products_parses_drf_paginated_response_with_default_rate(): void
    {
        Http::fake([
            'secretorca.test/api/v1/external/catalog/products*' => Http::response([
                'count' => 1,
                'next' => null,
                'previous' => null,
                'results' => [
                    [
                        'id' => '08214d2b-92f6-49f0-9d8c-3b5069e9d659',
                        'name' => 'Bigo Diamonds',
                        'default_rate' => '0.0177',
                        'unit_price' => '0.0170',
                        'bot_name' => 'bigo',
                        'code' => 'BIGO_DIAMONDS',
                        'currency' => 'USD',
                    ],
                ],
            ]),
        ]);

        $driver = app(GenericRestDriver::class)->configure($this->makeSupplier());
        $products = $driver->fetchProducts(['page' => 1, 'page_size' => 100, 'fetch_all' => false]);

        $this->assertCount(1, $products);
        $this->assertSame('08214d2b-92f6-49f0-9d8c-3b5069e9d659', $products[0]->supplierProductId);
        $this->assertEqualsWithDelta(0.0177, $products[0]->price, 0.0000001);
        $this->assertSame('BIGO_DIAMONDS', $products[0]->region);
    }

    public function test_place_topup_order_returns_pending_with_order_number(): void
    {
        Http::fake([
            'secretorca.test/api/v1/external/orders/create/' => Http::response([
                'order_number' => 'ORD-TEST123',
                'status' => 'pending',
                'quantity' => '1000.00',
            ], 201),
        ]);

        $driver = app(GenericRestDriver::class)->configure($this->makeSupplier());
        $driver->setTopUpPayloadExtras([
            'region' => 'EG',
            'idempotency_key' => 'test-key',
            'client_order_id' => '42',
        ]);

        $result = $driver->placeTopUpOrder('product-uuid', 1000, '108594930');

        $this->assertSame('ORD-TEST123', $result->supplierOrderId);
        $this->assertSame('processing', $result->status);

        Http::assertSent(function ($request): bool {
            $body = $request->data();

            return $body['product_id'] === 'product-uuid'
                && $body['quantity'] === '1000'
                && $body['target_account'] === '108594930'
                && $body['region'] === 'EG'
                && $body['idempotency_key'] === 'test-key'
                && $body['client_order_id'] === '42';
        });
    }

    public function test_get_order_status_maps_completed_to_fulfilled(): void
    {
        Http::fake([
            'secretorca.test/api/v1/external/orders/ORD-TEST123/' => Http::response([
                'order_number' => 'ORD-TEST123',
                'status' => 'completed',
                'bot_reference' => '1776649581762214811',
            ]),
        ]);

        $driver = app(GenericRestDriver::class)->configure($this->makeSupplier());
        $result = $driver->getOrderStatus('ORD-TEST123');

        $this->assertSame('fulfilled', $result->status);
        $this->assertSame('1776649581762214811', $result->rawResponse['bot_reference']);
    }

    public function test_parse_webhook_verifies_sha256_prefix_signature(): void
    {
        $supplier = $this->makeSupplier();
        $settings = $supplier->settings;
        $settings['webhook_secret'] = 'test-webhook-secret';
        $supplier->settings = $settings;
        $supplier->save();

        $payload = [
            'event_type' => 'order.completed',
            'event_id' => 'evt-123',
            'data' => [
                'order_number' => 'ORD-TEST123',
                'status' => 'completed',
                'bot_reference' => 'abc',
            ],
        ];
        $raw = json_encode($payload);
        $signature = 'sha256='.hash_hmac('sha256', $raw, 'test-webhook-secret');

        $request = Request::create('/webhook', 'POST', $payload, [], [], [
            'HTTP_X-Webhook-Signature' => $signature,
            'CONTENT_TYPE' => 'application/json',
        ], $raw);

        $driver = app(GenericRestDriver::class)->configure($supplier);
        $result = $driver->parseWebhook($request);

        $this->assertTrue($result->isVerified());
        $this->assertSame('order_fulfilled', $result->type);
        $this->assertSame('ORD-TEST123', $result->supplierOrderId);
        $this->assertSame('fulfilled', $result->status);
    }

    public function test_fulfill_direct_topup_order_accepts_pending_and_dispatches_poll_job(): void
    {
        Queue::fake();

        Http::fake([
            'secretorca.test/api/v1/external/orders/create/' => Http::response([
                'order_number' => 'ORD-ASYNC1',
                'status' => 'pending',
            ], 201),
        ]);

        $supplier = $this->makeSupplier();

        \Illuminate\Support\Facades\DB::table('products')->insert([
            'id' => 10,
            'name' => 'Bigo Diamonds',
            'added_by' => 'admin',
            'product_type' => 'digital',
            'status' => 1,
            'current_stock' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $mapping = SupplierProductMapping::query()->create([
            'product_id' => 10,
            'supplier_api_id' => $supplier->id,
            'supplier_product_id' => 'product-uuid',
            'cost_price' => 0.02,
            'cost_currency' => 'USD',
            'markup_type' => 'percent',
            'markup_value' => 0,
            'priority' => 0,
            'is_active' => true,
            'is_direct_topup' => true,
            'direct_topup_account_label' => 'Player ID',
            'direct_topup_region' => 'EG',
        ]);

        $order = Order::query()->create([
            'customer_id' => 1,
            'payment_status' => 'paid',
            'order_status' => 'confirmed',
        ]);

        OrderDetail::query()->create([
            'order_id' => $order->id,
            'product_id' => 10,
            'direct_topup_quantity' => 1000,
            'direct_topup_account_id' => '108594930',
        ]);

        $order = Order::query()->with(['orderDetails' => fn ($query) => $query->without('storage')])->find($order->id);

        $result = app(SupplierManager::class)->fulfillDirectTopUpOrder($order);

        $this->assertNull($result['error']);
        $this->assertFalse($result['fulfilled']);
        $this->assertTrue($result['placed']);
        $this->assertTrue($result['pending']);

        $supplierOrder = SupplierOrder::query()->first();
        $this->assertNotNull($supplierOrder);
        $this->assertSame('ORD-ASYNC1', $supplierOrder->supplier_order_id);
        $this->assertSame('processing', $supplierOrder->status);
        $this->assertSame($mapping->id, $supplierOrder->supplier_product_mapping_id);

        Queue::assertPushed(\App\Jobs\SupplierOrderPollJob::class);
    }

    public function test_catalog_sync_uses_one_based_page_numbers(): void
    {
        Http::fake([
            'secretorca.test/api/v1/external/catalog/products*' => function ($request) {
                $page = (int) $request->data()['page'];

                if ($page === 0) {
                    return Http::response([
                        'success' => false,
                        'error' => ['code' => 404, 'detail' => ['detail' => 'Invalid page.']],
                    ], 404);
                }

                return Http::response([
                    'count' => 1,
                    'next' => null,
                    'previous' => null,
                    'results' => [
                        [
                            'id' => 'abc',
                            'name' => 'Test Product',
                            'default_rate' => '1.00',
                            'bot_name' => 'Channel 1',
                            'code' => 'TEST',
                            'currency' => 'USD',
                        ],
                    ],
                ]);
            },
        ]);

        $supplier = $this->makeSupplier();
        $service = app(SupplierCatalogSyncService::class);
        $service->beginSync($supplier->id, freshStart: true);

        $result = $service->syncPage($supplier->id, 0);

        $this->assertFalse($result->hasMorePages);
        $this->assertSame(1, $result->itemsOnPage);

        Http::assertSent(function ($request): bool {
            return str_contains($request->url(), '/catalog/products/')
                && ($request->data()['page'] ?? null) === 1;
        });
    }
}
