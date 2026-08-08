<?php

namespace Tests\Feature\Console;

use App\Models\Seller;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\ManagesTestDatabaseSchema;
use Tests\TestCase;

class DashboardFreshStartTest extends TestCase
{
    use ManagesTestDatabaseSchema;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createSchema();
        $this->seedWalletData();
    }

    public function test_it_clears_all_wallet_tables_and_resets_balances(): void
    {
        $this->artisan('dashboard:fresh')
            ->expectsConfirmation('Are you sure you want to proceed?', 'yes')
            ->assertSuccessful();

        $this->assertDatabaseCount('wallet_transfers', 0);
        $this->assertDatabaseCount('wallet_transactions', 0);
        $this->assertDatabaseCount('customer_wallet_histories', 0);
        $this->assertDatabaseCount('seller_wallet_histories', 0);

        $this->assertSame(0.0, (float) User::find(1)->wallet_balance);
        $this->assertSame(0.0, (float) User::find(1)->loyalty_point);

        $this->assertSame(0.0, (float) DB::table('admin_wallets')->value('inhouse_earning'));
        $this->assertSame(0.0, (float) DB::table('seller_wallets')->where('seller_id', 1)->value('total_earning'));
        $this->assertSame(0.0, (float) DB::table('seller_wallets')->where('seller_id', 1)->value('pending_balance'));
        $this->assertSame(0.0, (float) DB::table('customer_wallets')->where('customer_id', 1)->value('balance'));
    }

    private function createSchema(): void
    {
        $this->recreateTable('users', function (Blueprint $table): void {
            $table->id();
            $table->string('f_name')->nullable();
            $table->string('l_name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->decimal('wallet_balance', 18, 12)->default(0);
            $table->decimal('loyalty_point', 18, 4)->default(0);
            $table->timestamps();
        });

        $this->recreateTable('sellers', function (Blueprint $table): void {
            $table->id();
            $table->string('f_name')->nullable();
            $table->string('l_name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('password')->nullable();
            $table->string('status')->default('approved');
            $table->string('auth_token')->nullable();
            $table->timestamps();
        });

        $this->recreateTable('products', function (Blueprint $table): void {
            $table->id();
            $table->integer('current_stock')->default(10);
            $table->timestamps();
        });

        $this->recreateTable('admin_wallets', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('admin_id');
            $table->decimal('inhouse_earning', 21, 12)->default(0);
            $table->decimal('withdrawn', 21, 12)->default(0);
            $table->decimal('commission_earned', 21, 12)->default(0);
            $table->decimal('delivery_charge_earned', 21, 12)->default(0);
            $table->decimal('pending_amount', 21, 12)->default(0);
            $table->decimal('total_tax_collected', 21, 12)->default(0);
            $table->timestamps();
        });

        $this->recreateTable('seller_wallets', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('seller_id');
            $table->decimal('total_earning', 21, 12)->default(0);
            $table->decimal('pending_balance', 24, 2)->default(0);
            $table->decimal('withdrawn', 21, 12)->default(0);
            $table->decimal('pending_withdraw', 21, 12)->default(0);
            $table->decimal('commission_given', 21, 12)->default(0);
            $table->decimal('delivery_charge_earned', 21, 12)->default(0);
            $table->decimal('collected_cash', 21, 12)->default(0);
            $table->decimal('total_tax_collected', 21, 12)->default(0);
            $table->timestamps();
        });

        $this->recreateTable('customer_wallets', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('customer_id');
            $table->decimal('balance', 21, 12)->default(0);
            $table->decimal('royality_points', 21, 12)->default(0);
            $table->timestamps();
        });

        $this->recreateTable('wallet_transfers', function (Blueprint $table): void {
            $table->id();
            $table->string('from_user_type');
            $table->unsignedBigInteger('from_user_id');
            $table->string('to_user_type');
            $table->unsignedBigInteger('to_user_id');
            $table->decimal('amount', 24, 3)->default(0);
            $table->string('reference')->nullable();
            $table->timestamps();
        });

        $this->recreateTable('wallet_transactions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->char('transaction_id', 36);
            $table->decimal('credit', 21, 12)->default(0);
            $table->decimal('debit', 21, 12)->default(0);
            $table->decimal('admin_bonus', 21, 12)->default(0);
            $table->decimal('balance', 21, 12)->default(0);
            $table->string('transaction_type')->nullable();
            $table->string('payment_method')->nullable();
            $table->json('order_ids')->nullable();
            $table->string('reference')->nullable();
            $table->timestamps();
        });

        $this->recreateTable('admin_wallet_histories', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('admin_id');
            $table->double('amount')->default(0);
            $table->unsignedBigInteger('order_id')->nullable();
            $table->unsignedBigInteger('product_id')->nullable();
            $table->string('payment')->nullable();
            $table->timestamps();
        });

        $this->recreateTable('seller_wallet_histories', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('seller_id');
            $table->decimal('amount', 21, 12)->default(0);
            $table->unsignedBigInteger('order_id')->nullable();
            $table->unsignedBigInteger('product_id')->nullable();
            $table->string('payment')->nullable();
            $table->timestamps();
        });

        $this->recreateTable('customer_wallet_histories', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('customer_id');
            $table->decimal('transaction_amount', 21, 12)->default(0);
            $table->string('transaction_type')->nullable();
            $table->string('transaction_method')->nullable();
            $table->string('transaction_id')->nullable();
            $table->timestamps();
        });
    }

    private function seedWalletData(): void
    {
        User::create([
            'id' => 1,
            'f_name' => 'Customer',
            'l_name' => 'One',
            'email' => 'customer@example.com',
            'phone' => '1111111111',
            'wallet_balance' => 50,
            'loyalty_point' => 25,
        ]);

        Seller::create([
            'id' => 1,
            'f_name' => 'Vendor',
            'l_name' => 'One',
            'email' => 'vendor@example.com',
            'phone' => '2222222222',
            'password' => bcrypt('password'),
            'status' => 'approved',
            'auth_token' => Str::random(40),
        ]);

        DB::table('products')->insert([
            'id' => 1,
            'current_stock' => 15,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('admin_wallets')->insert([
            'admin_id' => 1,
            'inhouse_earning' => 500,
            'withdrawn' => 0,
            'commission_earned' => 0,
            'delivery_charge_earned' => 0,
            'pending_amount' => 0,
            'total_tax_collected' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('seller_wallets')->insert([
            'seller_id' => 1,
            'total_earning' => 200,
            'pending_balance' => 75,
            'withdrawn' => 0,
            'pending_withdraw' => 0,
            'commission_given' => 0,
            'delivery_charge_earned' => 0,
            'collected_cash' => 0,
            'total_tax_collected' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('customer_wallets')->insert([
            'customer_id' => 1,
            'balance' => 50,
            'royality_points' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('wallet_transfers')->insert([
            'from_user_type' => 'vendor',
            'from_user_id' => 1,
            'to_user_type' => 'customer',
            'to_user_id' => 1,
            'amount' => 25,
            'reference' => 'Seed transfer',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('wallet_transactions')->insert([
            'user_id' => 1,
            'transaction_id' => (string) Str::uuid(),
            'credit' => 25,
            'debit' => 0,
            'admin_bonus' => 0,
            'balance' => 50,
            'transaction_type' => 'vendor_transfer_to_customer',
            'payment_method' => 'wallet_transfer',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('customer_wallet_histories')->insert([
            'customer_id' => 1,
            'transaction_amount' => 25,
            'transaction_type' => 'credit',
            'transaction_method' => 'wallet_transfer',
            'transaction_id' => 'TXN-1',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('seller_wallet_histories')->insert([
            'seller_id' => 1,
            'amount' => 25,
            'payment' => 'wallet_transfer',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
