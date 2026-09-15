<?php

namespace Tests\Feature;

use App\Enums\KycStatus;
use App\Enums\KycUserType;
use App\Models\KycVerification;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Tests\Concerns\ManagesTestDatabaseSchema;
use Tests\TestCase;

class SumsubWebhookTest extends TestCase
{
    use ManagesTestDatabaseSchema;

    private const WEBHOOK_SECRET = 'test-webhook-secret';

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();

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
            'sumsub.webhook_secret' => self::WEBHOOK_SECRET,
        ]);
    }

    public function test_a_signed_approval_webhook_marks_the_customer_verified(): void
    {
        $verification = $this->verification();

        $payload = [
            'applicantId' => 'app-123',
            'externalUserId' => 'customer_42',
            'type' => 'applicantReviewed',
            'reviewStatus' => 'completed',
            'reviewResult' => [
                'reviewAnswer' => 'GREEN',
                'rejectLabels' => [],
            ],
        ];

        $response = $this->postSignedWebhook($payload);

        $response->assertOk();

        $verification->refresh();

        $this->assertSame(KycStatus::APPROVED, $verification->status);
        $this->assertSame('GREEN', $verification->review_answer);
        $this->assertNotNull($verification->verified_at);
        $this->assertNotNull($verification->last_synced_at);
    }

    public function test_a_signed_rejection_webhook_records_the_reason(): void
    {
        $verification = $this->verification();

        $response = $this->postSignedWebhook([
            'applicantId' => 'app-123',
            'externalUserId' => 'customer_42',
            'type' => 'applicantReviewed',
            'reviewStatus' => 'completed',
            'reviewResult' => [
                'reviewAnswer' => 'RED',
                'reviewRejectType' => 'FINAL',
                'rejectLabels' => ['FORGERY'],
                'moderationComment' => 'Document appears altered',
            ],
        ]);

        $response->assertOk();

        $verification->refresh();

        $this->assertSame(KycStatus::REJECTED, $verification->status);
        $this->assertSame('FINAL', $verification->reject_type);
        $this->assertSame(['FORGERY'], $verification->reject_labels);
        $this->assertSame('Document appears altered', $verification->moderation_comment);
        $this->assertNull($verification->verified_at);
    }

    public function test_an_unsigned_webhook_is_rejected_and_changes_nothing(): void
    {
        $verification = $this->verification();

        $response = $this->postJson('/webhooks/sumsub', [
            'applicantId' => 'app-123',
            'externalUserId' => 'customer_42',
            'type' => 'applicantReviewed',
            'reviewStatus' => 'completed',
            'reviewResult' => ['reviewAnswer' => 'GREEN'],
        ]);

        $response->assertStatus(401);

        $verification->refresh();

        $this->assertSame(KycStatus::PENDING, $verification->status);
        $this->assertNull($verification->verified_at);
    }

    public function test_a_webhook_signed_with_the_wrong_secret_is_rejected(): void
    {
        $this->verification();

        $body = json_encode([
            'applicantId' => 'app-123',
            'externalUserId' => 'customer_42',
            'type' => 'applicantReviewed',
            'reviewStatus' => 'completed',
            'reviewResult' => ['reviewAnswer' => 'GREEN'],
        ]);

        $response = $this->call(
            'POST',
            '/webhooks/sumsub',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_PAYLOAD_DIGEST' => hash_hmac('sha256', (string) $body, 'the-wrong-secret'),
                'HTTP_X_PAYLOAD_DIGEST_ALG' => 'HMAC_SHA256_HEX',
            ],
            $body
        );

        $response->assertStatus(401);
    }

    public function test_a_webhook_for_an_unknown_applicant_is_acknowledged_without_an_error(): void
    {
        $payload = [
            'applicantId' => 'app-999',
            'externalUserId' => 'customer_999',
            'type' => 'applicantReviewed',
            'reviewStatus' => 'completed',
            'reviewResult' => ['reviewAnswer' => 'GREEN'],
        ];

        $this->postSignedWebhook($payload)->assertOk();
    }

    public function test_an_on_hold_webhook_keeps_the_account_pending(): void
    {
        $verification = $this->verification();

        $this->postSignedWebhook([
            'applicantId' => 'app-123',
            'externalUserId' => 'customer_42',
            'type' => 'applicantOnHold',
            'reviewStatus' => 'onHold',
        ])->assertOk();

        $verification->refresh();

        $this->assertSame(KycStatus::ON_HOLD, $verification->status);
        $this->assertNull($verification->verified_at);
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
}
