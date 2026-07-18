<?php

namespace Tests\Unit;

use App\Models\Order;
use App\Models\OrderDetail;
use App\Services\Order\OrderFulfillmentStatusService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Tests\Concerns\ManagesTestDatabaseSchema;
use Tests\TestCase;

class OrderFulfillmentStatusServiceTest extends TestCase
{
    use ManagesTestDatabaseSchema;

    protected function setUp(): void
    {
        parent::setUp();

        $this->recreateTable('orders', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->string('payment_method')->nullable();
            $table->string('payment_status')->default('paid');
            $table->string('order_status')->default('processing');
            $table->decimal('order_amount', 24, 2)->default(0);
            $table->timestamps();
        });

        $this->recreateTable('order_details', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('order_id');
            $table->unsignedBigInteger('product_id')->nullable();
            $table->integer('qty')->default(1);
            $table->string('delivery_status')->default('pending');
            $table->string('payment_status')->default('paid');
            $table->text('product_details')->nullable();
            $table->timestamps();
        });

        $this->recreateTable('digital_product_codes', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('product_id')->index();
            $table->unsignedBigInteger('order_id')->nullable();
            $table->unsignedBigInteger('order_detail_id')->nullable();
            $table->text('code');
            $table->string('status')->default('sold')->index();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        $this->recreateTable('order_status_histories', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('order_id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('status')->nullable();
            $table->string('user_type')->nullable();
            $table->text('cause')->nullable();
            $table->timestamps();
        });
    }

    public function test_marks_paid_digital_order_delivered_when_all_codes_assigned(): void
    {
        $order = Order::query()->create([
            'customer_id' => 1,
            'payment_method' => 'partner_wallet',
            'payment_status' => 'paid',
            'order_status' => 'processing',
            'order_amount' => 10,
        ]);

        $detail = OrderDetail::query()->create([
            'order_id' => $order->id,
            'product_id' => 10,
            'qty' => 1,
            'delivery_status' => 'pending',
            'payment_status' => 'paid',
            'product_details' => json_encode([
                'product_type' => 'digital',
                'digital_product_type' => 'ready_product',
            ]),
        ]);

        $this->app['db']->table('digital_product_codes')->insert([
            'product_id' => 10,
            'order_id' => $order->id,
            'order_detail_id' => $detail->id,
            'code' => Crypt::encryptString('CODE-123'),
            'status' => 'sold',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        app(OrderFulfillmentStatusService::class)->syncOrderFulfillmentStatus($order);

        $this->assertSame('delivered', $order->fresh()->order_status);
        $this->assertSame(
            'delivered',
            $this->app['db']->table('order_details')->where('id', $detail->id)->value('delivery_status'),
        );
        $this->assertDatabaseHas('order_status_histories', [
            'order_id' => $order->id,
            'status' => 'delivered',
            'user_type' => 'admin',
        ]);
    }

    public function test_keeps_processing_when_digital_codes_are_still_missing(): void
    {
        $order = Order::query()->create([
            'customer_id' => 1,
            'payment_method' => 'partner_wallet',
            'payment_status' => 'paid',
            'order_status' => 'confirmed',
            'order_amount' => 10,
        ]);

        OrderDetail::query()->create([
            'order_id' => $order->id,
            'product_id' => 11,
            'qty' => 1,
            'delivery_status' => 'pending',
            'payment_status' => 'paid',
            'product_details' => json_encode([
                'product_type' => 'digital',
                'digital_product_type' => 'ready_after_sell',
            ]),
        ]);

        app(OrderFulfillmentStatusService::class)->syncOrderFulfillmentStatus($order);

        $this->assertSame(
            'processing',
            $this->app['db']->table('orders')->where('id', $order->id)->value('order_status'),
        );
    }

    public function test_does_not_change_terminal_order_status(): void
    {
        $order = Order::query()->create([
            'customer_id' => 1,
            'payment_method' => 'partner_wallet',
            'payment_status' => 'paid',
            'order_status' => 'failed',
            'order_amount' => 10,
        ]);

        app(OrderFulfillmentStatusService::class)->syncOrderFulfillmentStatus($order);

        $this->assertSame('failed', $order->fresh()->order_status);
    }
}
