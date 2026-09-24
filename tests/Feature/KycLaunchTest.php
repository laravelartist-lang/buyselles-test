<?php

namespace Tests\Feature;

use App\Enums\KycStatus;
use App\Enums\KycUserType;
use App\Models\KycVerification;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;
use Tests\Concerns\ManagesTestDatabaseSchema;
use Tests\TestCase;

/**
 * The mobile apps open a signed launch URL instead of receiving Sumsub
 * credentials, so these URLs are the app-facing contract.
 */
class KycLaunchTest extends TestCase
{
    use ManagesTestDatabaseSchema;

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

        $this->recreateTable('users', function (Blueprint $table): void {
            $table->id();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
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
            'sumsub.levels.customer' => 'customer-kyc',
            'sumsub.levels.vendor' => 'vendor-kyc',
        ]);

        Http::fake([
            'api.sumsub.com/*' => Http::response(['token' => 'fresh-access-token', 'userId' => 'customer_7'], 200),
        ]);
    }

    public function test_a_signed_launch_url_renders_the_websdk_for_the_applicant(): void
    {
        $user = new User;
        $user->email = 'buyer@example.com';
        $user->save();

        $response = $this->get($this->launchUrl(KycUserType::CUSTOMER, $user->id));

        $response->assertOk();
        $response->assertSee('sns-websdk-builder.js', false);
        $response->assertSee('sumsub-websdk-container', false);
        $response->assertSee('fresh-access-token', false);

        $this->assertDatabaseHas('kyc_verifications', [
            'external_user_id' => 'customer_'.$user->id,
            'level_name' => 'customer-kyc',
            'status' => KycStatus::NOT_STARTED,
        ]);
    }

    public function test_the_launch_page_rejects_an_unsigned_url(): void
    {
        $this->get('/kyc/launch/customer/7')->assertStatus(403);
    }

    public function test_the_launch_page_is_unavailable_when_the_feature_is_disabled(): void
    {
        config(['sumsub.enabled' => false]);

        $response = $this->get($this->launchUrl(KycUserType::CUSTOMER, 7));

        $response->assertOk();
        $response->assertSee('buyselles-kyc://complete?status=', false);
        $response->assertSee('"unavailable"', false);
    }

    public function test_an_already_verified_applicant_is_sent_straight_back_to_the_app(): void
    {
        KycVerification::create([
            'user_type' => KycUserType::CUSTOMER,
            'user_id' => 7,
            'external_user_id' => 'customer_7',
            'level_name' => 'customer-kyc',
            'status' => KycStatus::APPROVED,
            'verified_at' => now(),
            'required_at' => now()->subDay(),
        ]);

        $response = $this->get($this->launchUrl(KycUserType::CUSTOMER, 7));

        $response->assertOk();
        $response->assertSee('buyselles-kyc://complete?status=', false);
        $response->assertSee('"approved"', false);
        $response->assertDontSee('sns-websdk-builder.js', false);
    }

    public function test_the_launch_page_rejects_an_unknown_user_type(): void
    {
        $this->get($this->launchUrl('reseller', 7))->assertStatus(404);
    }

    public function test_the_signed_token_endpoint_hands_back_a_fresh_token(): void
    {
        $response = $this->get(URL::temporarySignedRoute('kyc.launch.token', now()->addMinutes(10), [
            'userType' => KycUserType::CUSTOMER,
            'userId' => 7,
        ]));

        $response->assertOk();
        $response->assertJson(['token' => 'fresh-access-token']);
    }

    public function test_the_token_endpoint_rejects_an_unsigned_request(): void
    {
        $this->get('/kyc/launch/customer/7/token')->assertStatus(403);
    }

    private function launchUrl(string $userType, int $userId): string
    {
        return URL::temporarySignedRoute('kyc.launch', now()->addMinutes(10), [
            'userType' => $userType,
            'userId' => $userId,
        ]);
    }
}
