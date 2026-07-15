<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\Product;
use App\Models\User;
use App\Services\DirectTopUp\DirectTopUpWalletCheckoutService;
use App\Services\Supplier\SupplierManager;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\Concerns\ManagesTestDatabaseSchema;
use Tests\TestCase;

class DirectTopUpWalletCheckoutTest extends TestCase
{
    use ManagesTestDatabaseSchema;

    protected function setUp(): void
    {
        parent::setUp();

        $this->recreateTable('users', function (Blueprint $table): void {
            $table->id();
            $table->string('f_name')->nullable();
            $table->string('l_name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->decimal('wallet_balance', 24, 4)->default(0);
            $table->timestamps();
        });

        $this->recreateTable('business_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('type');
            $table->text('value')->nullable();
        });

        $this->app['db']->table('business_settings')->insert([
            'type' => 'wallet_status',
            'value' => '1',
        ]);

        $this->recreateTable('products', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->nullable();
            $table->string('product_type')->default('digital');
            $table->timestamps();
        });

        $this->recreateTable('supplier_apis', function (Blueprint $table): void {
            $table->id();
            $table->boolean('is_active')->default(true);
            $table->boolean('supports_direct_top_up')->default(true);
            $table->timestamps();
        });

        $this->recreateTable('supplier_product_mappings', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('product_id')->index();
            $table->unsignedBigInteger('supplier_api_id')->nullable();
            $table->string('supplier_product_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_direct_topup')->default(true);
            $table->timestamps();
        });

        $this->recreateTable('carts', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->unsignedBigInteger('product_id')->nullable();
            $table->decimal('direct_topup_quantity', 24, 4)->nullable();
            $table->text('direct_topup_account_id')->nullable();
            $table->timestamps();
        });

        $this->recreateTable('orders', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->unsignedBigInteger('seller_id')->default(0);
            $table->string('seller_is')->default('admin');
            $table->string('payment_method')->nullable();
            $table->string('payment_status')->default('unpaid');
            $table->string('order_status')->default('pending');
            $table->decimal('order_amount', 24, 4)->default(0);
            $table->decimal('discount_amount', 24, 4)->default(0);
            $table->decimal('total_tax_amount', 24, 4)->default(0);
            $table->decimal('shipping_cost', 24, 4)->default(0);
            $table->decimal('admin_commission', 24, 4)->default(0);
            $table->string('shipping_responsibility')->nullable();
            $table->text('order_note')->nullable();
            $table->timestamps();
        });

        $this->recreateTable('order_details', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('order_id')->nullable();
            $table->unsignedBigInteger('product_id')->nullable();
            $table->decimal('price', 24, 4)->default(0);
            $table->integer('qty')->default(1);
            $table->decimal('discount', 24, 4)->default(0);
            $table->decimal('tax', 24, 4)->default(0);
            $table->decimal('direct_topup_quantity', 24, 4)->nullable();
            $table->text('direct_topup_account_id')->nullable();
            $table->string('delivery_status')->nullable();
            $table->string('payment_status')->nullable();
            $table->timestamps();
        });

        $this->recreateTable('supplier_orders', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('order_id')->nullable();
            $table->string('status')->nullable();
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

        $this->recreateTable('order_transactions', function (Blueprint $table): void {
            $table->id();
            $table->string('transaction_id')->nullable();
            $table->unsignedBigInteger('order_id')->nullable();
            $table->string('status')->nullable();
            $table->decimal('order_amount', 24, 4)->default(0);
            $table->timestamps();
        });

        $this->recreateTable('admin_wallets', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('admin_id');
            $table->decimal('withdrawn', 24, 4)->default(0);
            $table->decimal('pending_amount', 24, 4)->default(0);
            $table->decimal('commission_earned', 24, 4)->default(0);
            $table->decimal('inhouse_earning', 24, 4)->default(0);
            $table->decimal('delivery_charge_earned', 24, 4)->default(0);
            $table->decimal('total_tax_collected', 24, 4)->default(0);
            $table->timestamps();
        });

        $this->recreateTable('seller_wallets', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('seller_id');
            $table->decimal('withdrawn', 24, 4)->default(0);
            $table->decimal('commission_given', 24, 4)->default(0);
            $table->decimal('total_earning', 24, 4)->default(0);
            $table->decimal('pending_withdraw', 24, 4)->default(0);
            $table->decimal('delivery_charge_earned', 24, 4)->default(0);
            $table->decimal('collected_cash', 24, 4)->default(0);
            $table->decimal('total_tax_collected', 24, 4)->default(0);
            $table->timestamps();
        });

        $this->recreateTable('storages', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('data_id')->nullable();
            $table->string('data_type')->nullable();
            $table->string('value')->nullable();
            $table->timestamps();
        });

        $this->recreateTable('order_edit_histories', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('order_id')->nullable();
            $table->decimal('order_due_amount', 24, 4)->default(0);
            $table->decimal('order_return_amount', 24, 4)->default(0);
            $table->string('order_due_payment_status')->nullable();
            $table->string('order_due_payment_method')->nullable();
            $table->string('order_return_payment_status')->nullable();
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

        $this->recreateTable('supplier_orders', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('order_id')->nullable();
            $table->string('status')->nullable();
            $table->timestamps();
        });
    }

    public function test_wallet_is_not_charged_when_direct_topup_fulfillment_fails(): void
    {
        $user = User::create([
            'f_name' => 'Test',
            'email' => 'wallet@test.com',
            'wallet_balance' => 100,
        ]);

        $product = Product::create([
            'name' => 'Top Up Product',
            'product_type' => 'digital',
        ]);

        $this->app['db']->table('supplier_apis')->insert([
            'id' => 1,
            'is_active' => true,
            'supports_direct_top_up' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->app['db']->table('supplier_product_mappings')->insert([
            'product_id' => $product->id,
            'supplier_api_id' => 1,
            'supplier_product_id' => '75',
            'is_active' => true,
            'is_direct_topup' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Cart::create([
            'customer_id' => $user->id,
            'product_id' => $product->id,
            'direct_topup_quantity' => 2,
            'direct_topup_account_id' => encrypt('player123'),
        ]);

        $order = Order::create([
            'customer_id' => $user->id,
            'payment_method' => 'pay_by_wallet',
            'payment_status' => 'unpaid',
            'order_status' => 'pending',
            'order_amount' => 25,
        ]);

        OrderDetail::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'price' => 25,
            'qty' => 1,
            'direct_topup_quantity' => 2,
            'direct_topup_account_id' => encrypt('player123'),
        ]);

        $supplierManager = Mockery::mock(SupplierManager::class);
        $supplierManager->shouldReceive('fulfillDirectTopUpOrder')
            ->once()
            ->andReturn([
                'fulfilled' => false,
                'error' => "Product 'Top Up Product': Your balance is not enough",
            ]);

        $this->app->instance(SupplierManager::class, $supplierManager);

        $service = app(DirectTopUpWalletCheckoutService::class);

        $result = $service->completeWalletPaymentAfterFulfillment([$order->id], $user->id, 25);

        $this->assertFalse($result['success']);
        $this->assertSame('Your balance is not enough', $result['error']);

        $user->refresh();
        $this->assertSame(100.0, (float) $user->wallet_balance);
        $this->assertSame(0, $this->app['db']->table('wallet_transactions')->count());

        $order->refresh();
        $this->assertSame('canceled', $order->order_status);
        $this->assertSame('unpaid', $order->payment_status);
    }

    public function test_wallet_is_charged_only_after_successful_direct_topup_fulfillment(): void
    {
        $user = User::create([
            'f_name' => 'Test',
            'email' => 'wallet-ok@test.com',
            'wallet_balance' => 100,
        ]);

        $product = Product::create([
            'name' => 'Top Up Product',
            'product_type' => 'digital',
        ]);

        $this->app['db']->table('supplier_apis')->insert([
            'id' => 1,
            'is_active' => true,
            'supports_direct_top_up' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->app['db']->table('supplier_product_mappings')->insert([
            'product_id' => $product->id,
            'supplier_api_id' => 1,
            'supplier_product_id' => '75',
            'is_active' => true,
            'is_direct_topup' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $order = Order::create([
            'customer_id' => $user->id,
            'payment_method' => 'pay_by_wallet',
            'payment_status' => 'unpaid',
            'order_status' => 'pending',
            'order_amount' => 25,
        ]);

        OrderDetail::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'price' => 25,
            'qty' => 1,
            'direct_topup_quantity' => 2,
            'direct_topup_account_id' => encrypt('player123'),
        ]);

        $this->app['db']->table('supplier_orders')->insert([
            'order_id' => $order->id,
            'status' => 'fulfilled',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $supplierManager = Mockery::mock(SupplierManager::class);
        $supplierManager->shouldReceive('fulfillDirectTopUpOrder')
            ->once()
            ->andReturn([
                'fulfilled' => true,
                'error' => null,
            ]);

        $this->app->instance(SupplierManager::class, $supplierManager);

        $service = app(DirectTopUpWalletCheckoutService::class);
        $result = $service->completeWalletPaymentAfterFulfillment([$order->id], $user->id, 25);

        $this->assertTrue($result['success']);

        $user->refresh();
        $this->assertSame(75.0, (float) $user->wallet_balance);
        $this->assertSame(1, $this->app['db']->table('wallet_transactions')->where('transaction_type', 'order_place')->count());

        $order->refresh();
        $this->assertSame('paid', $order->payment_status);
        $this->assertSame('delivered', $order->order_status);
    }

    public function test_wallet_is_charged_when_direct_topup_is_pending_at_supplier(): void
    {
        Queue::fake();

        $user = User::create([
            'f_name' => 'Test',
            'email' => 'wallet-pending@test.com',
            'wallet_balance' => 100,
        ]);

        $product = Product::create([
            'name' => 'Top Up Product',
            'product_type' => 'digital',
        ]);

        $this->app['db']->table('supplier_apis')->insert([
            'id' => 1,
            'is_active' => true,
            'supports_direct_top_up' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->app['db']->table('supplier_product_mappings')->insert([
            'product_id' => $product->id,
            'supplier_api_id' => 1,
            'supplier_product_id' => '75',
            'is_active' => true,
            'is_direct_topup' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $order = Order::create([
            'customer_id' => $user->id,
            'payment_method' => 'pay_by_wallet',
            'payment_status' => 'unpaid',
            'order_status' => 'pending',
            'order_amount' => 25,
        ]);

        OrderDetail::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'price' => 25,
            'qty' => 1,
            'direct_topup_quantity' => 2,
            'direct_topup_account_id' => encrypt('player123'),
        ]);

        $supplierManager = Mockery::mock(SupplierManager::class);
        $supplierManager->shouldReceive('fulfillDirectTopUpOrder')
            ->once()
            ->andReturn([
                'fulfilled' => false,
                'placed' => true,
                'pending' => true,
                'error' => null,
            ]);

        $this->app->instance(SupplierManager::class, $supplierManager);

        $service = app(DirectTopUpWalletCheckoutService::class);
        $result = $service->completeWalletPaymentAfterFulfillment([$order->id], $user->id, 25);

        $this->assertTrue($result['success']);
        $this->assertTrue($result['pending']);

        $user->refresh();
        $this->assertSame(75.0, (float) $user->wallet_balance);
        $this->assertSame(1, $this->app['db']->table('wallet_transactions')->where('transaction_type', 'order_place')->count());

        $order->refresh();
        $this->assertSame('paid', $order->payment_status);
        $this->assertSame('processing', $order->order_status);
    }

    public function test_paid_wallet_is_refunded_when_async_direct_topup_fulfillment_fails(): void
    {
        $user = User::create([
            'f_name' => 'Test',
            'email' => 'wallet-refund@test.com',
            'wallet_balance' => 75,
        ]);

        $order = Order::create([
            'customer_id' => $user->id,
            'payment_method' => 'pay_by_wallet',
            'payment_status' => 'paid',
            'order_status' => 'confirmed',
            'order_amount' => 25,
        ]);

        $this->app['db']->table('wallet_transactions')->insert([
            'user_id' => $user->id,
            'transaction_id' => (string) \Str::uuid(),
            'reference' => 'order payment',
            'transaction_type' => 'order_place',
            'debit' => 25,
            'credit' => 0,
            'balance' => 75,
            'order_ids' => json_encode([$order->id]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $service = app(DirectTopUpWalletCheckoutService::class);
        $service->markDirectTopUpOrderFailed($order, 'Your balance is not enough', $user->id);

        $user->refresh();
        $order->refresh();

        $this->assertSame(100.0, (float) $user->wallet_balance);
        $this->assertSame('failed', $order->order_status);
        $this->assertSame('unpaid', $order->payment_status);
        $this->assertSame(1, $this->app['db']->table('wallet_transactions')->where('transaction_type', 'order_refund')->count());
    }
}
