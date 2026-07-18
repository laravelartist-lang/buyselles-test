<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\Supplier\SupplierMappingController;
use App\Jobs\DirectTopUpFulfillmentJob;
use App\Jobs\SupplierCodeFetchJob;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\SupplierApi;
use App\Models\SupplierOrder;
use App\Models\User;
use App\Services\Supplier\SupplierManager;
use App\Services\Supplier\SupplierOrderEligibilityService;
use App\Utils\OrderManager;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;
use Mockery;
use ReflectionMethod;
use Tests\Concerns\ManagesTestDatabaseSchema;
use Tests\TestCase;

class SupplierCodeFetchJobTest extends TestCase
{
    use ManagesTestDatabaseSchema;

    protected function setUp(): void
    {
        parent::setUp();

        Bus::fake();

        $this->recreateTable('business_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('type');
            $table->text('value')->nullable();
        });

        $this->app['db']->table('business_settings')->insert([
            [
                'type' => 'language',
                'value' => json_encode([['code' => 'en', 'default' => true, 'direction' => 'ltr']]),
            ],
            [
                'type' => 'company_name',
                'value' => 'Test Shop',
            ],
            [
                'type' => 'wallet_status',
                'value' => '1',
            ],
        ]);

        $this->recreateTable('translations', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('translationable_id')->nullable();
            $table->string('translationable_type')->nullable();
            $table->string('locale')->nullable();
            $table->string('key')->nullable();
            $table->text('value')->nullable();
        });

        $this->recreateTable('products', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->nullable();
            $table->string('product_type')->default('digital');
            $table->string('digital_product_type')->nullable();
            $table->integer('current_stock')->default(0);
            $table->decimal('unit_price', 24, 4)->default(0);
            $table->decimal('purchase_price', 24, 4)->default(0);
            $table->timestamps();
        });

        $this->recreateTable('supplier_apis', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->nullable();
            $table->string('driver')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('supports_direct_top_up')->default(false);
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
            $table->boolean('is_active')->default(true);
            $table->boolean('is_direct_topup')->default(false);
            $table->string('direct_topup_account_label')->nullable();
            $table->timestamps();
        });

        $this->recreateTable('digital_product_codes', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('product_id')->index();
            $table->unsignedBigInteger('order_detail_id')->nullable();
            $table->text('code')->nullable();
            $table->string('status')->default('available')->index();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        $this->recreateTable('orders', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->string('payment_method')->nullable();
            $table->string('payment_status')->default('paid');
            $table->string('order_status')->default('confirmed');
            $table->decimal('order_amount', 24, 4)->default(0);
            $table->text('order_note')->nullable();
            $table->timestamps();
        });

        $this->recreateTable('order_details', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('order_id')->nullable();
            $table->unsignedBigInteger('product_id')->nullable();
            $table->integer('qty')->default(1);
            $table->text('product_details')->nullable();
            $table->string('delivery_status')->nullable();
            $table->string('payment_status')->nullable();
            $table->decimal('direct_topup_quantity', 24, 4)->nullable();
            $table->text('direct_topup_account_id')->nullable();
            $table->timestamps();
        });

        $this->recreateTable('users', function (Blueprint $table): void {
            $table->id();
            $table->string('f_name')->nullable();
            $table->string('email')->nullable();
            $table->decimal('wallet_balance', 24, 4)->default(0);
            $table->timestamps();
        });

        $this->recreateTable('wallet_transactions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->uuid('transaction_id')->nullable();
            $table->string('reference')->nullable();
            $table->string('transaction_type')->nullable();
            $table->string('payment_method')->nullable();
            $table->decimal('credit', 24, 4)->default(0);
            $table->decimal('debit', 24, 4)->default(0);
            $table->decimal('balance', 24, 4)->default(0);
            $table->json('order_ids')->nullable();
            $table->timestamps();
        });

        $this->recreateTable('order_status_histories', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('order_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('user_type')->nullable();
            $table->string('status')->nullable();
            $table->string('cause')->nullable();
            $table->timestamps();
        });

        $this->recreateTable('supplier_orders', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('supplier_api_id')->nullable();
            $table->unsignedBigInteger('supplier_product_mapping_id')->nullable();
            $table->string('supplier_order_id')->nullable();
            $table->unsignedBigInteger('order_id')->nullable();
            $table->unsignedBigInteger('order_detail_id')->nullable();
            $table->integer('quantity')->default(1);
            $table->decimal('cost_per_unit', 24, 4)->default(0);
            $table->decimal('total_cost', 24, 4)->default(0);
            $table->string('cost_currency', 3)->default('USD');
            $table->string('status')->default('processing');
            $table->timestamps();
        });
    }

    public function test_eligibility_service_accepts_ready_after_sell_with_supplier_mapping(): void
    {
        $this->seedSupplierMapping(productId: 10, supportsDirectTopUp: false, isDirectTopup: false);

        $detail = $this->makeOrderDetail(
            productId: 10,
            digitalProductType: 'ready_after_sell',
        );

        $service = app(SupplierOrderEligibilityService::class);

        $this->assertTrue($service->orderDetailNeedsSupplierCodeFetch($detail));
    }

    public function test_order_manager_dispatches_supplier_code_fetch_for_ready_after_sell(): void
    {
        $this->seedSupplierMapping(productId: 11, supportsDirectTopUp: false, isDirectTopup: false);

        $order = new Order(['payment_status' => 'paid']);
        $order->id = 100;
        $order->setRelation('orderDetails', collect([
            $this->makeOrderDetail(productId: 11, digitalProductType: 'ready_after_sell'),
        ]));

        $method = new ReflectionMethod(OrderManager::class, 'dispatchSupplierFallbackIfNeeded');
        $method->invoke(null, $order);

        Bus::assertDispatched(SupplierCodeFetchJob::class, function (SupplierCodeFetchJob $job): bool {
            return $job->orderId === 100;
        });
    }

    public function test_supplier_code_fetch_job_does_not_fail_order_when_async_supplier_order_exists(): void
    {
        $order = Order::query()->create([
            'payment_status' => 'paid',
            'order_status' => 'confirmed',
        ]);

        SupplierOrder::query()->create([
            'order_id' => $order->id,
            'status' => 'processing',
        ]);

        $manager = Mockery::mock(SupplierManager::class);
        $manager->shouldReceive('fulfillOrder')
            ->once()
            ->andReturn([
                'fulfilled' => false,
                'error' => null,
            ]);

        $this->app->instance(SupplierManager::class, $manager);

        (new SupplierCodeFetchJob($order->id))->handle(
            $manager,
            app(\App\Services\Supplier\SupplierFulfillmentFailureService::class)
        );

        $order->refresh();

        $this->assertSame('confirmed', $order->order_status);
        $this->assertNull($order->order_note);
    }

    public function test_supplier_code_fetch_job_refunds_wallet_when_fulfillment_fails(): void
    {
        $user = User::create([
            'f_name' => 'Test',
            'email' => 'supplier-refund@test.com',
            'wallet_balance' => 50,
        ]);

        $order = Order::query()->create([
            'customer_id' => $user->id,
            'payment_method' => 'pay_by_wallet',
            'payment_status' => 'paid',
            'order_status' => 'delivered',
            'order_amount' => 10,
        ]);

        OrderDetail::query()->create([
            'order_id' => $order->id,
            'product_id' => 10,
            'qty' => 1,
            'delivery_status' => 'delivered',
            'payment_status' => 'paid',
            'product_details' => json_encode([
                'product_type' => 'digital',
                'digital_product_type' => 'ready_after_sell',
            ]),
        ]);

        $this->app['db']->table('wallet_transactions')->insert([
            'user_id' => $user->id,
            'transaction_id' => (string) \Str::uuid(),
            'reference' => 'order payment',
            'transaction_type' => 'order_place',
            'debit' => 10,
            'credit' => 0,
            'balance' => 50,
            'order_ids' => json_encode([$order->id]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $manager = Mockery::mock(SupplierManager::class);
        $manager->shouldReceive('fulfillOrder')
            ->once()
            ->andReturn([
                'fulfilled' => false,
                'error' => 'Your balance is not enough',
            ]);

        $this->app->instance(SupplierManager::class, $manager);

        (new SupplierCodeFetchJob($order->id))->handle(
            $manager,
            app(\App\Services\Supplier\SupplierFulfillmentFailureService::class)
        );

        $user->refresh();

        $this->assertSame(60.0, (float) $user->wallet_balance);
        $this->assertSame('failed', Order::query()->where('id', $order->id)->value('order_status'));
        $this->assertSame('unpaid', Order::query()->where('id', $order->id)->value('payment_status'));
        $this->assertSame(1, $this->app['db']->table('wallet_transactions')->where('transaction_type', 'order_refund')->count());
    }

    public function test_direct_topup_line_skips_supplier_code_fetch_when_supplier_supports_topup(): void
    {
        $this->seedSupplierMapping(productId: 13, supportsDirectTopUp: true, isDirectTopup: true);

        $detail = $this->makeOrderDetail(
            productId: 13,
            digitalProductType: 'ready_product',
            directTopupQuantity: 100,
            directTopupAccountId: 'player123',
        );

        $service = app(SupplierOrderEligibilityService::class);

        $this->assertFalse($service->orderDetailNeedsSupplierCodeFetch($detail));
    }

    public function test_stale_direct_topup_flag_still_dispatches_supplier_code_fetch(): void
    {
        $this->seedSupplierMapping(productId: 14, supportsDirectTopUp: false, isDirectTopup: true);

        $order = new Order(['payment_status' => 'paid']);
        $order->id = 200;
        $order->setRelation('orderDetails', collect([
            $this->makeOrderDetail(productId: 14, digitalProductType: 'ready_after_sell'),
        ]));

        $dispatcher = app(\App\Services\Supplier\SupplierOrderFulfillmentDispatcher::class);
        $dispatcher->dispatchForOrder($order);

        Bus::assertDispatched(SupplierCodeFetchJob::class, function (SupplierCodeFetchJob $job): bool {
            return $job->orderId === 200;
        });

        Bus::assertNotDispatched(DirectTopUpFulfillmentJob::class);
    }

    public function test_mapping_update_clears_direct_topup_when_supplier_does_not_support_it(): void
    {
        SupplierApi::query()->create([
            'id' => 2,
            'name' => 'Bamboo',
            'driver' => 'bamboo',
            'is_active' => true,
            'supports_direct_top_up' => false,
        ]);

        $controller = app(SupplierMappingController::class);
        $method = new ReflectionMethod($controller, 'resolveDirectTopupAttributesFromRequest');
        $request = Request::create('/admin/supplier/mapping/1', 'POST', [
            'supplier_api_id' => 2,
            'is_direct_topup' => 1,
            'direct_topup_account_label' => 'Player ID',
        ]);

        $attributes = $method->invoke($controller, $request);

        $this->assertFalse($attributes['is_direct_topup']);
        $this->assertNull($attributes['direct_topup_account_label']);
    }

    private function seedSupplierMapping(int $productId, bool $supportsDirectTopUp, bool $isDirectTopup): void
    {
        $this->app['db']->table('products')->insert([
            'id' => $productId,
            'name' => 'Product '.$productId,
            'product_type' => 'digital',
            'digital_product_type' => 'ready_after_sell',
            'current_stock' => 0,
            'unit_price' => 10,
            'purchase_price' => 8,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->app['db']->table('supplier_apis')->insert([
            'id' => 1,
            'name' => 'Supplier',
            'driver' => 'bamboo',
            'is_active' => true,
            'supports_direct_top_up' => $supportsDirectTopUp,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->app['db']->table('supplier_product_mappings')->insert([
            'product_id' => $productId,
            'supplier_api_id' => 1,
            'supplier_product_id' => 'SKU-'.$productId,
            'cost_price' => 8,
            'cost_currency' => 'USD',
            'markup_type' => 'percent',
            'markup_value' => 0,
            'is_active' => true,
            'is_direct_topup' => $isDirectTopup,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function makeOrderDetail(
        int $productId,
        string $digitalProductType,
        ?float $directTopupQuantity = null,
        ?string $directTopupAccountId = null,
    ): OrderDetail {
        $detail = new OrderDetail([
            'product_id' => $productId,
            'qty' => 1,
            'direct_topup_quantity' => $directTopupQuantity,
            'direct_topup_account_id' => $directTopupAccountId,
            'product_details' => json_encode([
                'product_type' => 'digital',
                'digital_product_type' => $digitalProductType,
                'id' => $productId,
            ]),
        ]);
        $detail->id = $productId;

        return $detail;
    }
}
