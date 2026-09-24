<?php

namespace Tests\Feature\RestAPI\v3\Seller;

use App\Models\Seller;
use App\Models\SellerWallet;
use App\Models\User;
use App\Models\VendorPermission;
use App\Models\WalletTransfer;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\Concerns\ManagesTestDatabaseSchema;
use Tests\TestCase;

class WalletTransferTest extends TestCase
{
    use ManagesTestDatabaseSchema;

    private Seller $seller;

    private User $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createSchema();

        $this->seller = Seller::create([
            'f_name' => 'Vendor',
            'l_name' => 'One',
            'email' => 'vendor@example.com',
            'phone' => '1234567890',
            'password' => bcrypt('password'),
            'status' => 'approved',
            'auth_token' => Str::random(40),
        ]);

        SellerWallet::create([
            'seller_id' => $this->seller->id,
            'total_earning' => 100,
            'pending_withdraw' => 0,
            'withdrawn' => 0,
            'pending_balance' => 0,
            'commission_given' => 0,
            'delivery_charge_earned' => 0,
            'collected_cash' => 0,
            'total_tax_collected' => 0,
        ]);

        $this->customer = User::create([
            'f_name' => 'Customer',
            'l_name' => 'One',
            'email' => 'customer@example.com',
            'phone' => '9876543210',
            'wallet_balance' => 0,
        ]);
    }

    public function test_transfer_debits_vendor_and_credits_customer(): void
    {
        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/v3/seller/wallet-transfer/transfer', [
                'customer_id' => $this->customer->id,
                'amount' => 25,
                'reference' => 'Test transfer',
            ]);

        $response->assertOk()
            ->assertJsonPath('message', translate('balance_transferred_successfully'));

        $this->assertSame(75.0, (float) SellerWallet::where('seller_id', $this->seller->id)->value('total_earning'));
        $this->assertSame(25.0, (float) User::find($this->customer->id)->wallet_balance);
        $this->assertDatabaseCount('wallet_transfers', 1);
    }

    public function test_transfer_fails_with_insufficient_balance(): void
    {
        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/v3/seller/wallet-transfer/transfer', [
                'customer_id' => $this->customer->id,
                'amount' => 500,
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('message', translate('insufficient_balance'));

        $this->assertSame(100.0, (float) SellerWallet::where('seller_id', $this->seller->id)->value('total_earning'));
        $this->assertDatabaseCount('wallet_transfers', 0);
    }

    public function test_transfer_fails_for_invalid_customer(): void
    {
        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/v3/seller/wallet-transfer/transfer', [
                'customer_id' => 99999,
                'amount' => 10,
            ]);

        $response->assertStatus(422);
        $this->assertDatabaseCount('wallet_transfers', 0);
    }

    public function test_transfer_is_blocked_when_module_disabled(): void
    {
        VendorPermission::create([
            'seller_id' => $this->seller->id,
            'module_access' => ['vendor_orders'],
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/v3/seller/wallet-transfer/transfer', [
                'customer_id' => $this->customer->id,
                'amount' => 10,
            ]);

        $response->assertForbidden();
        $this->assertDatabaseCount('wallet_transfers', 0);
    }

    public function test_search_returns_matching_customers(): void
    {
        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/v3/seller/wallet-transfer/search-customers?term=Customer');

        $response->assertOk()
            ->assertJsonPath('customers.0.id', $this->customer->id)
            ->assertJsonPath('customers.0.name', 'Customer One');
    }

    public function test_index_returns_balance_and_history(): void
    {
        WalletTransfer::create([
            'from_user_type' => 'vendor',
            'from_user_id' => $this->seller->id,
            'to_user_type' => 'customer',
            'to_user_id' => $this->customer->id,
            'amount' => 10,
            'reference' => 'History',
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/v3/seller/wallet-transfer');

        $response->assertOk()
            ->assertJsonPath('total_earning', 100)
            ->assertJsonPath('transfers.data.0.reference', 'History');
    }

    /**
     * @return array<string, string>
     */
    private function authHeaders(): array
    {
        return [
            'Authorization' => 'Bearer '.$this->seller->auth_token,
        ];
    }

    private function createSchema(): void
    {
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

        $this->recreateTable('seller_wallets', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('seller_id');
            $table->decimal('total_earning', 24, 3)->default(0);
            $table->decimal('pending_withdraw', 24, 3)->default(0);
            $table->decimal('withdrawn', 24, 3)->default(0);
            $table->decimal('pending_balance', 24, 3)->default(0);
            $table->decimal('commission_given', 24, 3)->default(0);
            $table->decimal('delivery_charge_earned', 24, 3)->default(0);
            $table->decimal('collected_cash', 24, 3)->default(0);
            $table->decimal('total_tax_collected', 24, 3)->default(0);
            $table->timestamps();
        });

        $this->recreateTable('users', function (Blueprint $table): void {
            $table->id();
            $table->string('f_name')->nullable();
            $table->string('l_name')->nullable();
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->decimal('wallet_balance', 24, 4)->default(0);
            $table->string('cm_firebase_token')->nullable();
            $table->string('app_language')->nullable();
            $table->timestamps();
        });

        $this->recreateTable('customer_wallets', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('customer_id');
            $table->decimal('balance', 24, 3)->default(0);
            $table->timestamps();
        });

        $this->recreateTable('wallet_transactions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->uuid('transaction_id')->nullable();
            $table->string('reference')->nullable();
            $table->string('transaction_type')->nullable();
            $table->decimal('credit', 24, 3)->default(0);
            $table->decimal('debit', 24, 3)->default(0);
            $table->decimal('balance', 24, 3)->default(0);
            $table->string('payment_method')->nullable();
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

        $this->recreateTable('vendor_permissions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('seller_id');
            $table->json('module_access')->nullable();
            $table->timestamps();
        });

        $this->recreateTable('shops', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('seller_id');
            $table->string('name')->nullable();
            $table->timestamps();
        });

        if (! Schema::hasTable('business_settings')) {
            Schema::create('business_settings', function (Blueprint $table): void {
                $table->id();
                $table->string('type');
                $table->text('value')->nullable();
            });
        }
    }
}
