<?php

namespace Tests\Feature;

use App\Enums\KycStatus;
use App\Enums\KycUserType;
use App\Http\Controllers\Admin\KycManagementController;
use App\Models\KycVerification;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\ManagesTestDatabaseSchema;
use Tests\TestCase;

/**
 * The admin "Reset" action re-opens a rejected applicant so they can submit
 * documents again.
 *
 * It can only work once the Sumsub applicant id has been stored, so these
 * tests drive the id in the way production does - through a signed webhook -
 * and then assert the action actually reaches the Sumsub reset endpoint.
 */
class AdminKycResetTest extends TestCase
{
    use ManagesTestDatabaseSchema;

    private const WEBHOOK_SECRET = 'test-webhook-secret';

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();

        config([
            'sumsub.enabled' => true,
            'sumsub.app_token' => 'test-app-token',
            'sumsub.secret_key' => 'test-secret-key',
            'sumsub.webhook_secret' => self::WEBHOOK_SECRET,
        ]);

        $this->recreateTable('business_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('type')->nullable();
            $table->longText('value')->nullable();
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

        Cache::flush();
    }

    public function test_a_rejected_applicant_can_be_reset_once_the_webhook_recorded_the_applicant_id(): void
    {
        $verification = $this->verification();

        $this->postSignedWebhook([
            'applicantId' => 'app-123',
            'externalUserId' => 'customer_42',
            'type' => 'applicantReviewed',
            'reviewStatus' => 'completed',
            'reviewResult' => [
                'reviewAnswer' => 'RED',
                'reviewRejectType' => 'FINAL',
                'rejectLabels' => ['FORGERY'],
            ],
        ])->assertOk();

        $verification->refresh();
        $this->assertSame(KycStatus::REJECTED, $verification->status);

        Http::fake(['api.sumsub.com/*' => Http::response([], 200)]);

        app(KycManagementController::class)->reset($verification->id);

        $verification->refresh();

        $this->assertSame(KycStatus::RESET, $verification->status);
        $this->assertNull($verification->verified_at);

        Http::assertSent(fn ($request): bool => str_contains($request->url(), '/resources/applicants/app-123/reset'));
    }

    public function test_the_reset_is_refused_while_no_applicant_id_is_known(): void
    {
        $verification = $this->verification();

        Http::fake();

        app(KycManagementController::class)->reset($verification->id);

        $verification->refresh();

        $this->assertSame(KycStatus::PENDING, $verification->status);
        Http::assertNothingSent();
    }

    public function test_the_reset_is_refused_for_a_verification_that_does_not_exist(): void
    {
        Http::fake();

        app(KycManagementController::class)->reset(999);

        Http::assertNothingSent();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function postSignedWebhook(array $payload): \Illuminate\Testing\TestResponse
    {
        $body = json_encode($payload);

        return $this->call(
            'POST',
            '/webhooks/sumsub',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_PAYLOAD_DIGEST' => hash_hmac('sha256', (string) $body, self::WEBHOOK_SECRET),
                'HTTP_X_PAYLOAD_DIGEST_ALG' => 'HMAC_SHA256_HEX',
            ],
            $body
        );
    }

    private function verification(): KycVerification
    {
        return KycVerification::create([
            'user_type' => KycUserType::CUSTOMER,
            'user_id' => 42,
            'external_user_id' => 'customer_42',
            'level_name' => 'customer-kyc',
            'status' => KycStatus::PENDING,
            'required_at' => now(),
        ]);
    }

    private function storeSetting(string $type, string $value): void
    {
        \Illuminate\Support\Facades\DB::table('business_settings')->updateOrInsert(
            ['type' => $type],
            ['value' => $value, 'updated_at' => now(), 'created_at' => now()],
        );

        Cache::flush();
    }
}
