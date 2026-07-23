<?php

namespace Tests\Feature;

use App\Events\OrderPlacedEvent;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\User;
use App\Services\Order\OrderPlacedEmailService;
use App\Services\Supplier\SupplierFulfillmentFailureService;
use App\Utils\OrderManager;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Event;
use Tests\Concerns\ManagesTestDatabaseSchema;
use Tests\TestCase;

class DigitalWalletCheckoutEmailTest extends TestCase
{
    use ManagesTestDatabaseSchema;

    protected function setUp(): void
    {
        parent::setUp();

        $this->recreateTable('users', function (Blueprint $table): void {
            $table->id();
            $table->string('f_name')->nullable();
            $table->string('email')->nullable();
            $table->decimal('wallet_balance', 24, 4)->default(0);
            $table->timestamps();
        });

        $this->recreateTable('orders', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->unsignedBigInteger('seller_id')->default(0);
            $table->string('seller_is')->default('admin');
            $table->string('payment_method')->nullable();
            $table->string('payment_status')->default('paid');
            $table->string('order_status')->default('failed');
            $table->decimal('order_amount', 24, 4)->default(0);
            $table->unsignedBigInteger('shipping_address')->nullable();
            $table->unsignedBigInteger('billing_address')->nullable();
            $table->text('order_note')->nullable();
            $table->timestamp('order_placed_email_sent_at')->nullable();
            $table->timestamps();
        });

        $this->recreateTable('order_details', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('order_id')->nullable();
            $table->unsignedBigInteger('product_id')->nullable();
            $table->integer('qty')->default(1);
            $table->string('delivery_status')->nullable();
            $table->string('payment_status')->nullable();
            $table->text('product_details')->nullable();
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

        $this->recreateTable('wallet_transactions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('transaction_id')->nullable();
            $table->string('reference')->nullable();
            $table->string('transaction_type')->nullable();
            $table->decimal('debit', 24, 4)->default(0);
            $table->decimal('credit', 24, 4)->default(0);
            $table->decimal('balance', 24, 4)->default(0);
            $table->json('order_ids')->nullable();
            $table->timestamps();
        });

        $this->recreateTable('business_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('type');
            $table->text('value')->nullable();
        });

        $this->app['db']->table('business_settings')->insert([
            ['type' => 'wallet_status', 'value' => '1'],
            ['type' => 'loyalty_point_status', 'value' => '0'],
        ]);
    }

    public function test_failed_digital_wallet_order_does_not_dispatch_order_placed_email(): void
    {
        Event::fake([OrderPlacedEvent::class]);

        $order = Order::query()->create([
            'customer_id' => 1,
            'payment_method' => 'pay_by_wallet',
            'payment_status' => 'unpaid',
            'order_status' => 'failed',
        ]);

        OrderDetail::query()->create([
            'order_id' => $order->id,
            'product_id' => 10,
            'qty' => 1,
            'product_details' => json_encode(['product_type' => 'digital']),
        ]);

        OrderManager::dispatchDeferredCheckoutSideEffects(
            notificationEvents: [],
            mailEvents: [[[
                'email' => 'customer@example.com',
                'data' => [
                    'subject' => 'Order placed',
                    'title' => 'Order placed',
                    'userName' => 'Test',
                    'userType' => 'customer',
                    'templateName' => 'order-place',
                    'orderId' => $order->id,
                ],
            ]]],
            cartGroupIds: [],
        );

        Event::assertNotDispatched(OrderPlacedEvent::class);
        $this->assertNull($order->fresh()->order_placed_email_sent_at);
    }

    public function test_finalize_deferred_checkout_aborts_without_email_when_order_failed(): void
    {
        Event::fake([OrderPlacedEvent::class]);

        $order = Order::query()->create([
            'customer_id' => 1,
            'payment_method' => 'pay_by_wallet',
            'payment_status' => 'unpaid',
            'order_status' => 'failed',
        ]);

        OrderDetail::query()->create([
            'order_id' => $order->id,
            'product_id' => 10,
            'qty' => 1,
            'product_details' => json_encode(['product_type' => 'digital']),
        ]);

        session([
            'deferred_checkout_completion' => [
                'notification_events' => [],
                'mail_events' => [[[
                    'email' => 'customer@example.com',
                    'data' => [
                        'subject' => 'Order placed',
                        'title' => 'Order placed',
                        'userName' => 'Test',
                        'userType' => 'customer',
                        'templateName' => 'order-place',
                        'orderId' => $order->id,
                    ],
                ]]],
                'cart_group_ids' => [],
                'referral_user_id' => null,
            ],
        ]);

        OrderManager::finalizeDeferredCheckoutIfReady([$order->id]);

        Event::assertNotDispatched(OrderPlacedEvent::class);
        $this->assertNull(session('deferred_checkout_completion'));
    }

    public function test_mark_order_failed_discards_deferred_checkout_without_email(): void
    {
        Event::fake([OrderPlacedEvent::class]);

        $user = User::query()->create([
            'f_name' => 'Test',
            'email' => 'failed-wallet@test.com',
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
            'product_details' => json_encode(['product_type' => 'digital']),
        ]);

        $this->app['db']->table('wallet_transactions')->insert([
            'user_id' => $user->id,
            'transaction_id' => (string) \Str::uuid(),
            'reference' => 'order payment',
            'transaction_type' => 'order_place',
            'debit' => 10,
            'credit' => 0,
            'balance' => 40,
            'order_ids' => json_encode([$order->id]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        session([
            'deferred_checkout_completion' => [
                'notification_events' => [],
                'mail_events' => [[[
                    'email' => $user->email,
                    'data' => [
                        'subject' => 'Order placed',
                        'title' => 'Order placed',
                        'userName' => 'Test',
                        'userType' => 'customer',
                        'templateName' => 'order-place',
                        'orderId' => $order->id,
                    ],
                ]]],
                'cart_group_ids' => [],
                'referral_user_id' => null,
            ],
        ]);

        app(SupplierFulfillmentFailureService::class)->markOrderFailed($order, 'Supplier API timeout', $user->id);

        Event::assertNotDispatched(OrderPlacedEvent::class);
        $this->assertNull(session('deferred_checkout_completion'));
        $this->assertSame('failed', $order->fresh()->order_status);
    }

    public function test_delivered_digital_order_sends_order_placed_email_once_via_service(): void
    {
        Event::fake([OrderPlacedEvent::class]);

        $order = Order::query()->create([
            'customer_id' => 1,
            'seller_id' => 0,
            'seller_is' => 'admin',
            'payment_method' => 'pay_by_wallet',
            'payment_status' => 'paid',
            'order_status' => 'delivered',
        ]);

        $service = app(OrderPlacedEmailService::class);

        $this->assertFalse($service->shouldSkipCustomerOrderPlaceMail([
            'templateName' => 'order-place',
            'orderId' => $order->id,
        ]));
    }
}
