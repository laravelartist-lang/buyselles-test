<?php

namespace Tests\Feature;

use App\Enums\KycStatus;
use App\Enums\KycUserType;
use App\Models\KycVerification;
use App\Models\User;
use App\Services\Kyc\KycService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\ManagesTestDatabaseSchema;
use Tests\TestCase;

/**
 * The customer purchase threshold.
 *
 * A customer must verify their identity once they have spent the configured
 * amount - and critically, the order currently being placed counts towards
 * that amount, so an order that would cross the threshold is stopped before
 * it is created rather than after.
 */
class KycCustomerPurchaseThresholdTest extends TestCase
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

    public function test_a_customer_below_the_threshold_may_check_out(): void
    {
        $user = $this->customerWithOrders(20.0);

        $evaluation = $this->kyc()->evaluateCustomerCheckout($user, 30.0);

        $this->assertTrue($evaluation['allowed']);
        $this->assertFalse($evaluation['required']);
    }

    public function test_an_order_that_would_cross_the_threshold_is_blocked_before_it_is_placed(): void
    {
        // 60 already spent, the cart in flight is 50 -> 110 >= 100.
        $user = $this->customerWithOrders(60.0);

        $evaluation = $this->kyc()->evaluateCustomerCheckout($user, 50.0);

        $this->assertFalse($evaluation['allowed']);
        $this->assertTrue($evaluation['required']);
        $this->assertEqualsWithDelta(60.0, $evaluation['purchase_total'], 0.001);
        $this->assertEqualsWithDelta(50.0, $evaluation['pending_amount'], 0.001);
        $this->assertEqualsWithDelta(100.0, $evaluation['threshold'], 0.001);
    }

    public function test_the_pending_cart_alone_can_cross_the_threshold(): void
    {
        // Separate customers: a blocked checkout arms a sticky requirement, so
        // the same customer could not then be used for the allowed case.
        $this->assertFalse($this->kyc()->evaluateCustomerCheckout($this->customerWithOrders(0.0), 100.0)['allowed']);
        $this->assertTrue($this->kyc()->evaluateCustomerCheckout($this->customerWithOrders(0.0), 99.99)['allowed']);
    }

    public function test_landing_exactly_on_the_threshold_is_blocked(): void
    {
        $user = $this->customerWithOrders(50.0);

        $this->assertFalse($this->kyc()->evaluateCustomerCheckout($user, 50.0)['allowed']);
    }

    public function test_a_customer_already_over_the_threshold_is_blocked_with_an_empty_cart(): void
    {
        $user = $this->customerWithOrders(150.0);

        $this->assertFalse($this->kyc()->evaluateCustomerCheckout($user, 0.0)['allowed']);
    }

    public function test_a_verified_customer_is_never_blocked(): void
    {
        $user = $this->customerWithOrders(500.0);
        $this->approveCustomer($user->id);

        $evaluation = $this->kyc()->evaluateCustomerCheckout($user, 500.0);

        $this->assertTrue($evaluation['allowed']);
    }

    public function test_the_requirement_stays_armed_even_if_the_total_drops_below_the_threshold(): void
    {
        // A refund or a cancelled order must not unlock an account that has
        // already been asked to verify.
        $user = $this->customerWithOrders(0.0);
        $this->kyc()->ensureVerification(KycUserType::CUSTOMER, $user->id, required: true);

        $this->assertFalse($this->kyc()->evaluateCustomerCheckout($user, 0.0)['allowed']);
    }

    public function test_blocking_a_checkout_arms_the_requirement_and_records_the_verification(): void
    {
        $user = $this->customerWithOrders(0.0);

        $this->kyc()->evaluateCustomerCheckout($user, 120.0);

        $this->assertDatabaseHas('kyc_verifications', [
            'user_type' => KycUserType::CUSTOMER,
            'user_id' => $user->id,
            'external_user_id' => 'customer_'.$user->id,
            'status' => KycStatus::NOT_STARTED,
        ]);

        $verification = KycVerification::query()->where('user_id', $user->id)->first();

        $this->assertNotNull($verification);
        $this->assertNotNull($verification->required_at);
        $this->assertTrue($verification->blocksAccount());
    }

    public function test_the_gate_is_off_when_the_admin_toggle_is_off(): void
    {
        $this->storeSetting('kyc_verification_status', '0');

        $user = $this->customerWithOrders(500.0);

        $this->assertFalse($this->kyc()->isEnabled());
        $this->assertTrue($this->kyc()->evaluateCustomerCheckout($user, 500.0)['allowed']);
    }

    public function test_guest_checkout_is_not_gated(): void
    {
        // Guest checkouts resolve to the string 'offline', never a User.
        $this->assertTrue($this->kyc()->evaluateCustomerCheckout('offline', 500.0)['allowed']);
    }

    public function test_the_admin_configured_threshold_is_the_one_enforced(): void
    {
        $this->storeSetting('kyc_customer_purchase_threshold', '250');

        $user = $this->customerWithOrders(240.0);

        $this->assertEqualsWithDelta(250.0, $this->kyc()->purchaseThreshold(), 0.001);
        $this->assertTrue($this->kyc()->evaluateCustomerCheckout($user, 0.0)['allowed']);
        $this->assertFalse($this->kyc()->evaluateCustomerCheckout($user, 20.0)['allowed']);
    }

    public function test_cancelled_returned_failed_unpaid_and_guest_orders_do_not_count(): void
    {
        $user = $this->customerWithOrders(30.0);

        $this->order($user->id, 500.0, status: 'canceled');
        $this->order($user->id, 500.0, status: 'returned');
        $this->order($user->id, 500.0, status: 'failed');
        $this->order($user->id, 500.0, payment: 'unpaid');
        $this->order($user->id, 500.0, isGuest: 1);

        $this->assertEqualsWithDelta(30.0, $this->kyc()->customerPurchaseTotal($user->id), 0.001);
        $this->assertTrue($this->kyc()->evaluateCustomerCheckout($user, 20.0)['allowed']);
    }

    private function kyc(): KycService
    {
        return app(KycService::class);
    }

    private function customerWithOrders(float $paidTotal): User
    {
        $user = new User;
        $user->email = 'buyer-'.uniqid().'@example.com';
        $user->save();

        if ($paidTotal > 0) {
            $this->order($user->id, $paidTotal);
        }

        return $user;
    }

    private function order(
        int $customerId,
        float $amount,
        string $status = 'delivered',
        string $payment = 'paid',
        int $isGuest = 0,
    ): void {
        DB::table('orders')->insert([
            'customer_id' => $customerId,
            'is_guest' => $isGuest,
            'order_amount' => $amount,
            'payment_status' => $payment,
            'order_status' => $status,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function approveCustomer(int $userId): void
    {
        KycVerification::create([
            'user_type' => KycUserType::CUSTOMER,
            'user_id' => $userId,
            'external_user_id' => 'customer_'.$userId,
            'level_name' => 'customer-kyc',
            'status' => KycStatus::APPROVED,
            'required_at' => now()->subDay(),
            'verified_at' => now(),
        ]);
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
