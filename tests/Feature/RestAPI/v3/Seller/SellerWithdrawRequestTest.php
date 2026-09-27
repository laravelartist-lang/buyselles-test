<?php

namespace Tests\Feature\RestAPI\v3\Seller;

use App\Models\Seller;
use App\Models\SellerWallet;
use App\Models\WithdrawalMethod;
use App\Models\WithdrawRequest;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\Concerns\ApprovesVendorKycForSellerApiTests;
use Tests\Concerns\ManagesTestDatabaseSchema;
use Tests\TestCase;

class SellerWithdrawRequestTest extends TestCase
{
    use ApprovesVendorKycForSellerApiTests;
    use ManagesTestDatabaseSchema;

    private Seller $seller;

    private WithdrawalMethod $withdrawMethod;

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

        $this->withdrawMethod = WithdrawalMethod::create([
            'method_name' => 'Bank',
            'method_fields' => [],
            'is_default' => 0,
            'is_active' => 1,
        ]);

        $this->approveVendorKycForSellerApi($this->seller->id);
    }

    public function test_close_withdraw_request_restores_wallet_using_stored_amount(): void
    {
        SellerWallet::where('seller_id', $this->seller->id)->update([
            'total_earning' => 90,
            'pending_withdraw' => 10,
        ]);

        $withdrawRequest = WithdrawRequest::create([
            'seller_id' => $this->seller->id,
            'amount' => 10,
            'withdrawal_method_id' => $this->withdrawMethod->id,
            'withdrawal_method_fields' => ['method_name' => 'Bank'],
            'approved' => 0,
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->deleteJson('/api/v3/seller/close-withdraw-request', [
                'id' => $withdrawRequest->id,
                'amount' => 999,
            ]);

        $response->assertOk();

        $wallet = SellerWallet::where('seller_id', $this->seller->id)->first();
        $this->assertSame(100.0, (float) $wallet->total_earning);
        $this->assertSame(0.0, (float) $wallet->pending_withdraw);
        $this->assertDatabaseMissing('withdraw_requests', ['id' => $withdrawRequest->id]);
    }

    public function test_close_withdraw_request_rejects_other_sellers_request(): void
    {
        $otherSeller = Seller::create([
            'f_name' => 'Vendor',
            'l_name' => 'Two',
            'email' => 'vendor2@example.com',
            'phone' => '1234567891',
            'password' => bcrypt('password'),
            'status' => 'approved',
            'auth_token' => Str::random(40),
        ]);

        SellerWallet::create([
            'seller_id' => $otherSeller->id,
            'total_earning' => 50,
            'pending_withdraw' => 0,
            'withdrawn' => 0,
            'pending_balance' => 0,
            'commission_given' => 0,
            'delivery_charge_earned' => 0,
            'collected_cash' => 0,
            'total_tax_collected' => 0,
        ]);

        $this->approveVendorKycForSellerApi($otherSeller->id);

        SellerWallet::where('seller_id', $this->seller->id)->update([
            'total_earning' => 90,
            'pending_withdraw' => 10,
        ]);

        $withdrawRequest = WithdrawRequest::create([
            'seller_id' => $this->seller->id,
            'amount' => 10,
            'withdrawal_method_id' => $this->withdrawMethod->id,
            'withdrawal_method_fields' => ['method_name' => 'Bank'],
            'approved' => 0,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$otherSeller->auth_token)
            ->deleteJson('/api/v3/seller/close-withdraw-request', [
                'id' => $withdrawRequest->id,
            ]);

        $response->assertStatus(403);

        $wallet = SellerWallet::where('seller_id', $this->seller->id)->first();
        $this->assertSame(90.0, (float) $wallet->total_earning);
        $this->assertSame(10.0, (float) $wallet->pending_withdraw);
        $this->assertDatabaseHas('withdraw_requests', ['id' => $withdrawRequest->id]);
    }

    public function test_withdraw_request_rejects_when_insufficient_balance(): void
    {
        SellerWallet::where('seller_id', $this->seller->id)->update([
            'total_earning' => 5,
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/v3/seller/balance-withdraw', [
                'withdraw_method_id' => $this->withdrawMethod->id,
                'amount' => 10,
            ]);

        $response->assertStatus(403);
        $this->assertDatabaseCount('withdraw_requests', 0);
        $this->assertSame(5.0, (float) SellerWallet::where('seller_id', $this->seller->id)->value('total_earning'));
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

        $this->recreateTable('withdrawal_methods', function (Blueprint $table): void {
            $table->id();
            $table->string('method_name');
            $table->text('method_fields')->nullable();
            $table->tinyInteger('is_default')->default(0);
            $table->tinyInteger('is_active')->default(1);
            $table->timestamps();
        });

        $this->recreateTable('withdraw_requests', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('seller_id')->nullable();
            $table->unsignedBigInteger('delivery_man_id')->nullable();
            $table->unsignedBigInteger('admin_id')->nullable();
            $table->decimal('amount', 24, 3)->default(0);
            $table->unsignedBigInteger('withdrawal_method_id')->nullable();
            $table->json('withdrawal_method_fields')->nullable();
            $table->text('transaction_note')->nullable();
            $table->integer('approved')->default(0);
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
