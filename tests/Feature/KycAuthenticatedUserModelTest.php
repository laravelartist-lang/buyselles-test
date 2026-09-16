<?php

namespace Tests\Feature;

use App\Enums\KycStatus;
use App\Models\User;
use App\Services\Kyc\CustomerCheckoutKycGuard;
use App\Services\Kyc\KycService;
use App\User as AuthenticatedUser;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Laravel\Passport\Passport;
use Tests\Concerns\ManagesTestDatabaseSchema;
use Tests\TestCase;

/**
 * The customer KYC layer must accept whichever user model the guards resolve.
 *
 * `config/auth.php` backs the `customer` and `api` guards with the legacy
 * `App\User` model, while the rest of the KYC code was written against
 * `App\Models\User`. They are unrelated classes over the same `users` table, so
 * an `instanceof App\Models\User` check silently approved every real customer.
 * These tests always authenticate with the model the configured provider
 * returns, so the two can never drift apart again.
 */
class KycAuthenticatedUserModelTest extends TestCase
{
    use ManagesTestDatabaseSchema;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        Queue::fake();

        config([
            'sumsub.enabled' => true,
            'sumsub.app_token' => 'test-app-token',
            'sumsub.secret_key' => 'test-secret-key',
            'sumsub.webhook_secret' => 'test-webhook-secret',
            'sumsub.levels.customer' => 'customer-kyc',
            'sumsub.levels.vendor' => 'vendor-kyc',
        ]);

        $this->recreateTable('business_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('type')->nullable();
            $table->longText('value')->nullable();
            $table->timestamps();
        });

        $this->recreateTable('users', function (Blueprint $table): void {
            $table->id();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            // The `customer` middleware logs out anyone who is not active.
            $table->boolean('is_active')->default(1);
            $table->timestamps();
        });

        // App\User eagerly loads its storage relation, unlike App\Models\User.
        $this->recreateTable('storages', function (Blueprint $table): void {
            $table->id();
            $table->string('data_type')->nullable();
            $table->string('data_id')->nullable();
            $table->string('key')->nullable();
            $table->string('value')->nullable();
            $table->timestamps();
        });

        $this->recreateTable('orders', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->unsignedBigInteger('seller_id')->nullable();
            $table->boolean('is_guest')->default(0);
            $table->decimal('order_amount', 24, 2)->default(0);
            $table->string('payment_status')->default('unpaid');
            $table->string('order_status')->default('pending');
            $table->timestamps();
        });

        $this->recreateTable('kyc_verifications', function (Blueprint $table): void {
            $table->id();
            $table->string('user_type', 20);
            $table->unsignedBigInteger('user_id');
            $table->string('external_user_id', 120)->unique();
            $table->string('applicant_id', 120)->nullable();
            $table->string('level_name', 120);
            $table->string('status', 30)->default('not_started');
            $table->string('review_answer', 20)->nullable();
            $table->string('reject_type', 20)->nullable();
            $table->json('reject_labels')->nullable();
            $table->text('moderation_comment')->nullable();
            $table->timestamp('required_at')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->json('last_webhook_payload')->nullable();
            $table->timestamps();
        });

        $this->storeSetting('language', json_encode([
            ['code' => 'en', 'name' => 'English', 'default' => true, 'direction' => 'ltr'],
        ]));
        $this->storeSetting('kyc_verification_status', '1');

        Cache::flush();
    }

    public function test_the_customer_guard_resolves_the_legacy_user_model(): void
    {
        $user = $this->customerAsTheGuardResolves();

        $this->assertInstanceOf(AuthenticatedUser::class, $user);
        $this->assertNotInstanceOf(User::class, $user);
    }

    public function test_the_guard_blocks_the_user_model_the_guard_actually_resolves(): void
    {
        $user = $this->customerAsTheGuardResolves(paidTotal: 150.0);

        $block = app(CustomerCheckoutKycGuard::class)
            ->blockReason($user, Request::create('/checkout-complete-wallet', 'POST'));

        $this->assertNotNull(
            $block,
            'A customer over the threshold must be blocked no matter which user model the guard returns.'
        );
        $this->assertSame(KycStatus::NOT_STARTED, $block['verification']->status);
    }

    public function test_the_guard_still_lets_a_guest_checkout_through(): void
    {
        $block = app(CustomerCheckoutKycGuard::class)
            ->blockReason('offline', Request::create('/checkout-complete-wallet', 'POST'));

        $this->assertNull($block);
    }

    public function test_the_web_checkout_blocks_the_customer_the_guard_resolves(): void
    {
        $user = $this->customerAsTheGuardResolves(paidTotal: 150.0);

        $response = $this->actingAs($user, 'customer')->get(route('checkout-complete'));

        $response->assertRedirect(route('customer.kyc.index'));
    }

    public function test_the_customer_app_blocks_order_placement_for_the_resolved_customer(): void
    {
        $user = $this->customerAsTheGuardResolves(paidTotal: 150.0);

        Passport::actingAs($user, ['*'], 'api');

        $response = $this->withHeader('Authorization', 'Bearer test-token')
            ->getJson('api/v1/customer/order/place');

        $response->assertStatus(403);
        $response->assertJson(['kyc_required' => true]);
    }

    public function test_the_customer_app_kyc_status_endpoint_accepts_the_resolved_customer(): void
    {
        $user = $this->customerAsTheGuardResolves(paidTotal: 150.0);

        Passport::actingAs($user, ['*'], 'api');

        $response = $this->withHeader('Authorization', 'Bearer test-token')
            ->getJson('api/v1/customer/kyc/status');

        $response->assertOk();
        $response->assertJsonPath('kyc.status', KycStatus::NOT_STARTED);
        $response->assertJsonPath('kyc.required', true);
        $this->assertSame(150.0, (float) $response->json('purchase_total'));
    }

    public function test_the_web_kyc_status_route_accepts_the_resolved_customer(): void
    {
        $user = $this->customerAsTheGuardResolves(paidTotal: 150.0);

        $response = $this->actingAs($user, 'customer')->getJson(route('customer.kyc.status'));

        $response->assertOk();
        $response->assertJsonPath('kyc.status', KycStatus::NOT_STARTED);
    }

    public function test_evaluate_customer_status_accepts_the_resolved_customer(): void
    {
        $user = $this->customerAsTheGuardResolves(paidTotal: 150.0);

        $verification = app(KycService::class)->evaluateCustomerStatus($user);

        $this->assertSame(KycStatus::NOT_STARTED, $verification->status);
        $this->assertSame($user->id, $verification->user_id);
    }

    /**
     * Resolve a customer through the configured provider, so the test always
     * uses exactly the model a real request would be authenticated with.
     *
     * @return AuthenticatedUser|User
     */
    private function customerAsTheGuardResolves(float $paidTotal = 0.0): Authenticatable
    {
        $user = new User;
        $user->email = 'buyer-'.uniqid().'@example.com';
        $user->save();

        if ($paidTotal > 0) {
            DB::table('orders')->insert([
                'customer_id' => $user->id,
                'is_guest' => 0,
                'order_amount' => $paidTotal,
                'payment_status' => 'paid',
                'order_status' => 'delivered',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $resolved = Auth::guard('customer')->getProvider()->retrieveById($user->id);

        $this->assertNotNull($resolved);

        return $resolved;
    }

    private function storeSetting(string $type, string $value): void
    {
        DB::table('business_settings')->updateOrInsert(
            ['type' => $type],
            ['value' => $value, 'updated_at' => now(), 'created_at' => now()],
        );

        Cache::flush();
    }
}
