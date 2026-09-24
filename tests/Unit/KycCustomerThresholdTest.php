<?php

namespace Tests\Unit;

use App\Enums\KycStatus;
use App\Enums\KycUserType;
use App\Http\Resources\Kyc\KycStatusResource;
use App\Models\KycVerification;
use App\Models\Order;
use App\Models\User;
use App\Services\Kyc\KycService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\ManagesTestDatabaseSchema;
use Tests\TestCase;

class KycCustomerThresholdTest extends TestCase
{
    use ManagesTestDatabaseSchema;

    private KycService $kycService;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();

        $this->recreateTable('business_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('type')->nullable();
            $table->longText('value')->nullable();
            $table->timestamps();
        });

        DB::table('business_settings')->insert([
            'type' => 'language',
            'value' => json_encode([
                ['code' => 'en', 'name' => 'English', 'default' => true, 'direction' => 'ltr'],
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->recreateTable('orders', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('customer_id')->index();
            $table->boolean('is_guest')->default(false);
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

        config([
            'sumsub.enabled' => true,
            'sumsub.app_token' => 'test-app-token',
            'sumsub.secret_key' => 'test-secret-key',
            'sumsub.customer_threshold' => 100,
            'sumsub.levels.customer' => 'customer-kyc',
            'sumsub.levels.vendor' => 'vendor-kyc',
        ]);

        $this->kycService = app(KycService::class);
    }

    public function test_a_customer_under_the_threshold_may_checkout(): void
    {
        $user = $this->customer(1);
        $this->paidOrder(1, 50);

        $evaluation = $this->kycService->evaluateCustomerCheckout($user, 10);

        $this->assertTrue($evaluation['allowed']);
        $this->assertFalse($evaluation['required']);
        $this->assertSame(50.0, $evaluation['purchase_total']);
        $this->assertSame(100.0, $evaluation['threshold']);
    }

    public function test_crossing_the_threshold_with_the_current_order_blocks_checkout(): void
    {
        $user = $this->customer(1);
        $this->paidOrder(1, 95);

        $evaluation = $this->kycService->evaluateCustomerCheckout($user, 20);

        $this->assertFalse($evaluation['allowed']);
        $this->assertTrue($evaluation['required']);
        $this->assertNotNull($evaluation['verification']);
        $this->assertNotNull($evaluation['verification']->required_at);

        $this->assertDatabaseHas('kyc_verifications', [
            'user_type' => KycUserType::CUSTOMER,
            'user_id' => 1,
            'external_user_id' => 'customer_1',
            'status' => KycStatus::NOT_STARTED,
        ]);
    }

    public function test_a_customer_exactly_at_the_threshold_is_blocked(): void
    {
        $user = $this->customer(1);
        $this->paidOrder(1, 100);

        $evaluation = $this->kycService->evaluateCustomerCheckout($user, 0);

        $this->assertFalse($evaluation['allowed']);
    }

    public function test_unpaid_cancelled_returned_and_guest_orders_are_excluded(): void
    {
        $user = $this->customer(1);

        $this->paidOrder(1, 40);
        $this->paidOrder(1, 40, orderStatus: 'canceled');
        $this->paidOrder(1, 40, orderStatus: 'returned');
        $this->paidOrder(1, 40, paymentStatus: 'unpaid');
        $this->paidOrder(1, 40, isGuest: true);

        $this->assertSame(40.0, $this->kycService->customerPurchaseTotal(1));

        $evaluation = $this->kycService->evaluateCustomerCheckout($user, 0);

        $this->assertTrue($evaluation['allowed']);
    }

    public function test_the_requirement_stays_after_orders_are_refunded(): void
    {
        $user = $this->customer(1);
        $this->paidOrder(1, 120);

        $blocked = $this->kycService->evaluateCustomerCheckout($user, 0);
        $this->assertFalse($blocked['allowed']);

        // The purchase total drops below the threshold...
        DB::table('orders')->update(['payment_status' => 'unpaid']);
        $this->assertSame(0.0, $this->kycService->customerPurchaseTotal(1));

        // ...but the account stays restricted until KYC is approved.
        $stillBlocked = $this->kycService->evaluateCustomerCheckout($user, 0);
        $this->assertFalse($stillBlocked['allowed']);
    }

    public function test_a_verified_customer_is_never_blocked(): void
    {
        $user = $this->customer(1);
        $this->paidOrder(1, 5_000);

        KycVerification::create([
            'user_type' => KycUserType::CUSTOMER,
            'user_id' => 1,
            'external_user_id' => 'customer_1',
            'level_name' => 'customer-kyc',
            'status' => KycStatus::APPROVED,
            'verified_at' => now(),
            'required_at' => now()->subDay(),
        ]);

        $evaluation = $this->kycService->evaluateCustomerCheckout($user, 500);

        $this->assertTrue($evaluation['allowed']);
        $this->assertTrue($this->kycService->isVerified(KycUserType::CUSTOMER, 1));
    }

    public function test_everything_is_allowed_when_the_feature_is_disabled(): void
    {
        config(['sumsub.enabled' => false]);

        $user = $this->customer(1);
        $this->paidOrder(1, 10_000);

        $evaluation = $this->kycService->evaluateCustomerCheckout($user, 1_000);

        $this->assertTrue($evaluation['allowed']);
        $this->assertFalse($this->kycService->isEnabled());
    }

    public function test_the_business_setting_overrides_the_configured_threshold(): void
    {
        DB::table('business_settings')->insert([
            'type' => 'kyc_customer_purchase_threshold',
            'value' => '250',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Cache::flush();

        $this->assertSame(250.0, $this->kycService->purchaseThreshold());

        $user = $this->customer(1);
        $this->paidOrder(1, 200);

        $this->assertTrue($this->kycService->evaluateCustomerCheckout($user, 0)['allowed']);
    }

    public function test_the_status_endpoint_arms_the_requirement_from_past_purchases(): void
    {
        $user = $this->customer(1);
        $this->paidOrder(1, 140);

        $verification = $this->kycService->evaluateCustomerStatus($user);

        $this->assertNotNull($verification->required_at);
        $this->assertFalse($verification->isApproved());

        // The apps read this flag to show the popup before an order is tried.
        $this->assertTrue((new KycStatusResource($verification))->resolve()['required']);
    }

    public function test_the_status_endpoint_leaves_small_spenders_alone(): void
    {
        $user = $this->customer(1);
        $this->paidOrder(1, 10);

        $verification = $this->kycService->evaluateCustomerStatus($user);

        $this->assertNull($verification->required_at);
    }

    public function test_guests_are_never_gated(): void
    {
        $this->assertFalse($this->kycService->requiresCustomerVerification('offline', 500));

        $evaluation = $this->kycService->evaluateCustomerCheckout(null, 500);

        $this->assertTrue($evaluation['allowed']);
    }

    public function test_vendor_access_is_denied_until_verification_is_approved(): void
    {
        $denied = $this->kycService->evaluateVendorAccess(3);
        $this->assertFalse($denied['allowed']);

        KycVerification::create([
            'user_type' => KycUserType::VENDOR,
            'user_id' => 3,
            'external_user_id' => 'vendor_3',
            'level_name' => 'vendor-kyc',
            'status' => KycStatus::APPROVED,
            'verified_at' => now(),
        ]);

        $this->assertTrue($this->kycService->evaluateVendorAccess(3)['allowed']);
    }

    private function customer(int $id): User
    {
        $user = new User;
        $user->id = $id;

        return $user;
    }

    private function paidOrder(
        int $customerId,
        float $amount,
        string $paymentStatus = 'paid',
        string $orderStatus = 'delivered',
        bool $isGuest = false,
    ): Order {
        $order = new Order;
        $order->customer_id = $customerId;
        $order->is_guest = $isGuest;
        $order->order_amount = $amount;
        $order->payment_status = $paymentStatus;
        $order->order_status = $orderStatus;
        $order->save();

        return $order;
    }
}
