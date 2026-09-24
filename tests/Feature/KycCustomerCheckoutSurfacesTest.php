<?php

namespace Tests\Feature;

use App\Enums\KycStatus;
use App\Models\KycVerification;
use App\Models\User;
use App\Services\Kyc\CustomerCheckoutKycGuard;
use App\Services\Kyc\KycService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Laravel\Passport\Passport;
use Tests\Concerns\ManagesTestDatabaseSchema;
use Tests\TestCase;

/**
 * The customer checkout gate on both customer-facing surfaces.
 *
 * The web storefront and the customer app both route through the same
 * CustomerCheckoutKycGuard, so a customer cannot sidestep verification by
 * switching channel.
 */
class KycCustomerCheckoutSurfacesTest extends TestCase
{
    use ManagesTestDatabaseSchema;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();

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

    public function test_the_web_checkout_redirects_a_customer_who_must_verify(): void
    {
        $user = $this->customerWithOrders(150.0);

        $response = $this->actingAs($user, 'customer')->get(route('checkout-complete'));

        $response->assertRedirect(route('customer.kyc.index'));
    }

    public function test_the_web_checkout_returns_a_json_block_to_ajax_callers(): void
    {
        $user = $this->customerWithOrders(150.0);

        $response = $this->actingAs($user, 'customer')
            ->withHeader('X-Requested-With', 'XMLHttpRequest')
            ->get(route('checkout-complete'));

        $response->assertStatus(403);
        $response->assertJson([
            'kyc_required' => true,
            'status' => 0,
        ]);
        $response->assertJsonPath('kyc.status', KycStatus::NOT_STARTED);
    }

    public function test_the_web_checkout_stops_the_customer_before_the_order_is_created(): void
    {
        $user = $this->customerWithOrders(150.0);
        $ordersBefore = DB::table('orders')->count();

        $this->actingAs($user, 'customer')->get(route('checkout-complete'));

        // The blocked checkout must not have created anything new.
        $this->assertSame($ordersBefore, DB::table('orders')->count());

        $verification = KycVerification::query()->where('user_id', $user->id)->first();

        $this->assertNotNull($verification);
        $this->assertNotNull($verification->required_at);
    }

    public function test_the_web_checkout_lets_a_customer_under_the_threshold_past_the_gate(): void
    {
        $user = $this->customerWithOrders(10.0);

        $response = $this->actingAs($user, 'customer')->get(route('checkout-complete'));

        $this->assertNotSame(
            route('customer.kyc.index'),
            $response->headers->get('Location'),
            'A customer under the threshold must not be sent to the KYC screen.'
        );

        $this->assertDatabaseCount('kyc_verifications', 0);
    }

    public function test_the_customer_app_blocks_order_placement_once_the_threshold_is_reached(): void
    {
        $user = $this->customerWithOrders(150.0);

        Passport::actingAs($user, ['*'], 'api');

        $response = $this->withHeader('Authorization', 'Bearer test-token')
            ->getJson('api/v1/customer/order/place');

        $response->assertStatus(403);
        $response->assertJson(['kyc_required' => true]);
        $response->assertJsonPath('kyc.status', KycStatus::NOT_STARTED);
        $response->assertJsonPath('kyc.required', true);
    }

    public function test_the_customer_app_block_arms_the_requirement_once_only(): void
    {
        $user = $this->customerWithOrders(150.0);

        Passport::actingAs($user, ['*'], 'api');
        $this->withHeader('Authorization', 'Bearer test-token')->getJson('api/v1/customer/order/place');

        Passport::actingAs($user, ['*'], 'api');
        $this->withHeader('Authorization', 'Bearer test-token')->getJson('api/v1/customer/order/place');

        $this->assertDatabaseCount('kyc_verifications', 1);
    }

    /**
     * The guard has to count the cart that is in flight, otherwise an order
     * that crosses the threshold would only be caught on the next attempt.
     */
    public function test_the_guard_feeds_the_in_flight_cart_amount_into_the_evaluation(): void
    {
        $user = $this->customer();

        $kyc = $this->mock(KycService::class);
        $kyc->shouldReceive('isEnabled')->andReturn(true);
        $kyc->shouldReceive('resolveCustomerId')->with($user)->andReturn($user->id);
        $kyc->shouldReceive('resolvePendingOrderAmount')->once()->andReturn(75.0);
        $kyc->shouldReceive('evaluateCustomerCheckout')
            ->once()
            ->with($user, 75.0)
            ->andReturn([
                'allowed' => false,
                'required' => true,
                'message' => 'verify please',
                'verification' => null,
                'purchase_total' => 40.0,
                'pending_amount' => 75.0,
                'threshold' => 100.0,
            ]);

        $guard = app(CustomerCheckoutKycGuard::class);

        $block = $guard->blockReason($user, Request::create('/checkout-complete', 'GET'));

        $this->assertNotNull($block);
        $this->assertSame('verify please', $block['message']);
    }

    public function test_the_guard_stands_down_when_kyc_is_disabled(): void
    {
        $this->storeSetting('kyc_verification_status', '0');

        $user = $this->customerWithOrders(500.0);

        $block = app(CustomerCheckoutKycGuard::class)
            ->blockReason($user, Request::create('/checkout-complete', 'GET'));

        $this->assertNull($block);
    }

    private function customer(): User
    {
        $user = new User;
        $user->email = 'buyer-'.uniqid().'@example.com';
        $user->save();

        return $user;
    }

    private function customerWithOrders(float $paidTotal): User
    {
        $user = $this->customer();

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

        return $user;
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
