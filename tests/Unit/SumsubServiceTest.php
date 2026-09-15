<?php

namespace Tests\Unit;

use App\Enums\KycStatus;
use App\Services\Kyc\SumsubService;
use Tests\TestCase;

class SumsubServiceTest extends TestCase
{
    private SumsubService $sumsub;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sumsub = app(SumsubService::class);

        config([
            'sumsub.app_token' => 'test-app-token',
            'sumsub.secret_key' => 'test-secret-key',
            'sumsub.webhook_secret' => 'test-webhook-secret',
        ]);
    }

    public function test_request_signature_is_hmac_sha256_of_timestamp_method_path_and_body(): void
    {
        $body = json_encode(['userId' => 'customer_7', 'levelName' => 'customer-kyc']);

        $expected = hash_hmac('sha256', '1700000000POST/resources/accessTokens/sdk'.$body, 'test-secret-key');

        $this->assertSame(
            $expected,
            $this->sumsub->signature('1700000000', 'POST', '/resources/accessTokens/sdk', $body)
        );
    }

    public function test_request_signature_uppercases_the_http_method(): void
    {
        $this->assertSame(
            $this->sumsub->signature('1700000000', 'POST', '/path'),
            $this->sumsub->signature('1700000000', 'post', '/path')
        );
    }

    public function test_webhook_signature_is_accepted_when_the_digest_matches(): void
    {
        $body = '{"applicantId":"1","type":"applicantReviewed"}';
        $digest = hash_hmac('sha256', $body, 'test-webhook-secret');

        $this->assertTrue($this->sumsub->verifyWebhookSignature($body, $digest, 'HMAC_SHA256_HEX'));
    }

    public function test_webhook_signature_is_rejected_for_a_tampered_body(): void
    {
        $digest = hash_hmac('sha256', '{"applicantId":"1"}', 'test-webhook-secret');

        $this->assertFalse(
            $this->sumsub->verifyWebhookSignature('{"applicantId":"2"}', $digest, 'HMAC_SHA256_HEX')
        );
    }

    public function test_webhook_signature_is_rejected_when_the_secret_is_not_configured(): void
    {
        config(['sumsub.webhook_secret' => null]);

        $body = '{"applicantId":"1"}';

        $this->assertFalse(
            $this->sumsub->verifyWebhookSignature($body, hash_hmac('sha256', $body, 'whatever'), 'HMAC_SHA256_HEX')
        );
    }

    public function test_webhook_signature_is_rejected_when_the_digest_header_is_missing(): void
    {
        $this->assertFalse($this->sumsub->verifyWebhookSignature('{}', null, 'HMAC_SHA256_HEX'));
    }

    public function test_it_supports_sha512_digests(): void
    {
        $body = '{"applicantId":"1"}';
        $digest = hash_hmac('sha512', $body, 'test-webhook-secret');

        $this->assertTrue($this->sumsub->verifyWebhookSignature($body, $digest, 'HMAC_SHA512_HEX'));
    }

    public function test_status_mapping_covers_every_sumsub_review_outcome(): void
    {
        $this->assertSame(
            KycStatus::APPROVED,
            $this->sumsub->mapStatus(['reviewStatus' => 'completed', 'reviewResult' => ['reviewAnswer' => 'GREEN']])
        );

        $this->assertSame(
            KycStatus::REJECTED,
            $this->sumsub->mapStatus([
                'reviewStatus' => 'completed',
                'reviewResult' => ['reviewAnswer' => 'RED', 'reviewRejectType' => 'FINAL'],
            ])
        );

        $this->assertSame(
            KycStatus::RESET,
            $this->sumsub->mapStatus([
                'reviewStatus' => 'completed',
                'reviewResult' => ['reviewAnswer' => 'RED', 'reviewRejectType' => 'RETRY'],
            ])
        );

        $this->assertSame(KycStatus::ON_HOLD, $this->sumsub->mapStatus(['reviewStatus' => 'onHold']));

        $this->assertSame(KycStatus::PENDING, $this->sumsub->mapStatus(['reviewStatus' => 'pending']));
    }

    public function test_a_brand_new_applicant_without_documents_is_not_started(): void
    {
        $this->assertSame(
            KycStatus::NOT_STARTED,
            $this->sumsub->mapStatus(['reviewStatus' => 'init', 'idDocs' => []])
        );
    }

    public function test_configured_detection_requires_both_credentials(): void
    {
        $this->assertTrue($this->sumsub->isConfigured());

        config(['sumsub.secret_key' => null]);

        $this->assertFalse($this->sumsub->isConfigured());
    }
}
