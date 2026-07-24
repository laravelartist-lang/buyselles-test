<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\User;
use App\Services\Supplier\SupplierFulfillmentFailureService;
use App\Utils\OrderManager;
use Illuminate\Database\Schema\Blueprint;
use Tests\Concerns\ManagesTestDatabaseSchema;
use Tests\TestCase;

class CheckoutCartCleanupTest extends TestCase
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

        $this->recreateTable('products', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->nullable();
            $table->timestamps();
        });

        $this->recreateTable('carts', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->unsignedBigInteger('product_id')->nullable();
            $table->integer('is_guest')->default(0);
            $table->integer('is_checked')->default(1);
            $table->string('cart_group_id')->nullable();
            $table->integer('quantity')->default(1);
            $table->timestamps();
        });

        $this->recreateTable('orders', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->integer('is_guest')->default(0);
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
            $table->string('delivery_status')->nullable();
            $table->string('payment_status')->nullable();
            $table->text('product_details')->nullable();
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

        $this->recreateTable('wallet_transactions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->uuid('transaction_id')->nullable();
            $table->string('reference')->nullable();
            $table->string('transaction_type')->nullable();
            $table->decimal('credit', 24, 4)->default(0);
            $table->decimal('debit', 24, 4)->default(0);
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
        ]);
    }

    public function test_clean_cart_for_placed_order_removes_checked_items_for_order_products(): void
    {
        $this->app['db']->table('products')->insert(['id' => 10, 'name' => 'Digital']);

        $user = User::query()->create([
            'f_name' => 'Buyer',
            'email' => 'buyer@test.com',
        ]);

        Cart::query()->create([
            'customer_id' => $user->id,
            'product_id' => 10,
            'is_guest' => 0,
            'is_checked' => 1,
            'cart_group_id' => 'group-1',
            'quantity' => 1,
        ]);

        Cart::query()->create([
            'customer_id' => $user->id,
            'product_id' => 10,
            'is_guest' => 0,
            'is_checked' => 0,
            'cart_group_id' => 'group-1',
            'quantity' => 1,
        ]);

        $order = Order::query()->create([
            'customer_id' => $user->id,
            'is_guest' => 0,
            'payment_method' => 'pay_by_wallet',
            'payment_status' => 'paid',
            'order_status' => 'processing',
            'order_amount' => 5,
        ]);

        OrderDetail::query()->create([
            'order_id' => $order->id,
            'product_id' => 10,
            'qty' => 1,
            'delivery_status' => 'pending',
            'payment_status' => 'paid',
            'product_details' => json_encode(['product_type' => 'digital']),
        ]);

        OrderManager::cleanCartForPlacedOrder($order);

        $this->assertSame(1, Cart::query()->count());
        $this->assertSame(0, Cart::query()->where('is_checked', 1)->count());
    }

    public function test_mark_order_failed_clears_cart_when_deferred_session_has_no_cart_groups(): void
    {
        $this->app['db']->table('products')->insert(['id' => 11, 'name' => 'Game']);

        $user = User::query()->create([
            'f_name' => 'Buyer',
            'email' => 'fail-cart@test.com',
            'wallet_balance' => 20,
        ]);

        Cart::query()->create([
            'customer_id' => $user->id,
            'product_id' => 11,
            'is_guest' => 0,
            'is_checked' => 1,
            'cart_group_id' => 'group-2',
            'quantity' => 1,
        ]);

        $order = Order::query()->create([
            'customer_id' => $user->id,
            'is_guest' => 0,
            'payment_method' => 'pay_by_wallet',
            'payment_status' => 'paid',
            'order_status' => 'processing',
            'order_amount' => 10,
        ]);

        OrderDetail::query()->create([
            'order_id' => $order->id,
            'product_id' => 11,
            'qty' => 1,
            'delivery_status' => 'pending',
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
            'balance' => 10,
            'order_ids' => json_encode([$order->id]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        session([
            'deferred_checkout_completion' => [
                'notification_events' => [],
                'mail_events' => [],
                'cart_group_ids' => [],
                'referral_user_id' => null,
            ],
        ]);

        app(SupplierFulfillmentFailureService::class)->markOrderFailed(
            $order,
            'Supplier fulfillment failed: out of stock',
            $user->id,
        );

        $this->assertSame(0, Cart::query()->where('is_checked', 1)->count());
        $this->assertSame('failed', $order->fresh()->order_status);
    }
}
