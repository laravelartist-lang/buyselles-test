<?php

namespace Tests\Feature;

use App\Jobs\DirectTopUpFulfillmentJob;
use App\Jobs\SupplierCodeFetchJob;
use App\Models\Order;
use App\Models\OrderDetail;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Bus;
use Tests\Concerns\ManagesTestDatabaseSchema;
use Tests\TestCase;

class DirectTopUpFulfillmentTest extends TestCase
{
    use ManagesTestDatabaseSchema;

    protected function setUp(): void
    {
        parent::setUp();

        Bus::fake();

        $this->recreateTable('supplier_apis', function (Blueprint $table): void {
            $table->id();
            $table->boolean('is_active')->default(true);
            $table->boolean('supports_direct_top_up')->default(false);
            $table->timestamps();
        });

        $this->recreateTable('supplier_product_mappings', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('product_id')->index();
            $table->unsignedBigInteger('supplier_api_id')->nullable();
            $table->string('supplier_product_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_direct_topup')->default(false);
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

        $this->recreateTable('supplier_orders', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('order_id')->nullable();
            $table->unsignedBigInteger('order_detail_id')->nullable();
            $table->string('status')->nullable();
            $table->timestamps();
        });
    }

    public function test_supplier_code_fetch_is_skipped_for_direct_topup_order_details(): void
    {
        $this->app['db']->table('supplier_apis')->insert([
            'id' => 1,
            'is_active' => true,
            'supports_direct_top_up' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->app['db']->table('supplier_product_mappings')->insert([
            'product_id' => 55,
            'supplier_api_id' => 1,
            'supplier_product_id' => 'SUP-55',
            'is_active' => true,
            'is_direct_topup' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $order = new Order(['payment_status' => 'paid']);
        $order->id = 10;

        $detail = new OrderDetail([
            'product_id' => 55,
            'qty' => 1,
            'direct_topup_quantity' => 500,
            'direct_topup_account_id' => 'player123',
            'product_details' => json_encode([
                'product_type' => 'digital',
                'digital_product_type' => 'ready_product',
                'is_direct_topup' => true,
            ]),
        ]);
        $detail->id = 1;

        $order->setRelation('orderDetails', collect([$detail]));

        app(\App\Services\Supplier\SupplierOrderFulfillmentDispatcher::class)->dispatchForOrder($order);

        Bus::assertNotDispatched(SupplierCodeFetchJob::class);
    }

    public function test_direct_topup_fulfillment_job_is_dispatched_when_mapping_exists(): void
    {
        $this->app['db']->table('supplier_apis')->insert([
            'id' => 1,
            'is_active' => true,
            'supports_direct_top_up' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->app['db']->table('supplier_product_mappings')->insert([
            'product_id' => 55,
            'supplier_api_id' => 1,
            'supplier_product_id' => 'SUP-55',
            'is_active' => true,
            'is_direct_topup' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $order = new Order(['payment_status' => 'paid']);
        $order->id = 20;

        $detail = new OrderDetail([
            'product_id' => 55,
            'qty' => 1,
            'direct_topup_quantity' => 500,
            'direct_topup_account_id' => 'player123',
        ]);
        $detail->id = 2;

        $order->setRelation('orderDetails', collect([$detail]));

        app(\App\Services\Supplier\SupplierOrderFulfillmentDispatcher::class)->dispatchForOrder($order);

        Bus::assertDispatched(DirectTopUpFulfillmentJob::class, function (DirectTopUpFulfillmentJob $job): bool {
            return $job->orderId === 20;
        });
    }

    public function test_failed_order_does_not_dispatch_direct_topup_fulfillment_job(): void
    {
        $this->app['db']->table('supplier_apis')->insert([
            'id' => 1,
            'is_active' => true,
            'supports_direct_top_up' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->app['db']->table('supplier_product_mappings')->insert([
            'product_id' => 55,
            'supplier_api_id' => 1,
            'supplier_product_id' => 'SUP-55',
            'is_active' => true,
            'is_direct_topup' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $order = new Order([
            'payment_status' => 'paid',
            'order_status' => 'failed',
        ]);
        $order->id = 21;

        $detail = new OrderDetail([
            'product_id' => 55,
            'qty' => 1,
            'direct_topup_quantity' => 500,
            'direct_topup_account_id' => 'player123',
        ]);
        $detail->id = 3;

        $order->setRelation('orderDetails', collect([$detail]));

        app(\App\Services\Supplier\SupplierOrderFulfillmentDispatcher::class)->dispatchForOrder($order);

        Bus::assertNotDispatched(DirectTopUpFulfillmentJob::class);
    }

    public function test_order_with_failed_supplier_attempt_does_not_dispatch_direct_topup_fulfillment_job(): void
    {
        $this->app['db']->table('supplier_apis')->insert([
            'id' => 1,
            'is_active' => true,
            'supports_direct_top_up' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->app['db']->table('supplier_product_mappings')->insert([
            'product_id' => 55,
            'supplier_api_id' => 1,
            'supplier_product_id' => 'SUP-55',
            'is_active' => true,
            'is_direct_topup' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->app['db']->table('supplier_orders')->insert([
            'order_id' => 22,
            'order_detail_id' => 4,
            'status' => 'failed',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $order = new Order([
            'payment_status' => 'paid',
            'order_status' => 'processing',
        ]);
        $order->id = 22;

        $detail = new OrderDetail([
            'product_id' => 55,
            'qty' => 1,
            'direct_topup_quantity' => 500,
            'direct_topup_account_id' => 'player123',
        ]);
        $detail->id = 4;

        $order->setRelation('orderDetails', collect([$detail]));

        app(\App\Services\Supplier\SupplierOrderFulfillmentDispatcher::class)->dispatchForOrder($order);

        Bus::assertNotDispatched(DirectTopUpFulfillmentJob::class);
    }

    public function test_canceled_order_does_not_dispatch_any_fulfillment_jobs(): void
    {
        $this->app['db']->table('supplier_apis')->insert([
            'id' => 1,
            'is_active' => true,
            'supports_direct_top_up' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->app['db']->table('supplier_product_mappings')->insert([
            'product_id' => 55,
            'supplier_api_id' => 1,
            'supplier_product_id' => 'SUP-55',
            'is_active' => true,
            'is_direct_topup' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $order = new Order([
            'payment_status' => 'paid',
            'order_status' => 'canceled',
        ]);
        $order->id = 23;

        $detail = new OrderDetail([
            'product_id' => 55,
            'qty' => 1,
            'direct_topup_quantity' => 500,
            'direct_topup_account_id' => 'player123',
            'product_details' => json_encode([
                'product_type' => 'digital',
                'digital_product_type' => 'ready_after_sell',
            ]),
        ]);
        $detail->id = 5;

        $order->setRelation('orderDetails', collect([$detail]));

        app(\App\Services\Supplier\SupplierOrderFulfillmentDispatcher::class)->dispatchForOrder($order);

        Bus::assertNotDispatched(DirectTopUpFulfillmentJob::class);
        Bus::assertNotDispatched(SupplierCodeFetchJob::class);
    }
}
