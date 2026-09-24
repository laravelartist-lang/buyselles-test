<?php

namespace App\Services\Kyc;

use App\Enums\KycStatus;
use App\Enums\KycUserType;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Thin, dependency free client for the Sumsub (Idensic) REST API.
 *
 * The same credentials and the same access token endpoint serve the web
 * (WebSDK), the customer mobile app and the vendor mobile app - only the
 * SDK that consumes the token differs.
 */
class SumsubService
{
    /**
     * Sumsub requires every request to be signed with HMAC SHA256 over
     * (timestamp + method + path + body).
     */
    public function signature(string $timestamp, string $method, string $path, string $body = ''): string
    {
        return hash_hmac(
            'sha256',
            $timestamp.strtoupper($method).$path.$body,
            (string) $this->secretKey()
        );
    }

    public function isConfigured(): bool
    {
        return ! empty($this->appToken()) && ! empty($this->secretKey());
    }

    public function isEnabled(): bool
    {
        return (bool) config('sumsub.enabled', false) && $this->isConfigured();
    }

    public function levelFor(string $userType): string
    {
        $level = config('sumsub.levels.'.$userType);

        if (empty($level)) {
            throw new RuntimeException("No Sumsub verification level configured for user type [{$userType}].");
        }

        return (string) $level;
    }

    /**
     * Mint a short lived SDK access token for the given applicant.
     *
     * @param  array<string, mixed>  $applicantIdentifiers  Optional email / phone hints applied on first creation.
     * @return array{token: string, userId: string}
     */
    public function generateAccessToken(
        string $userType,
        int $userId,
        ?string $levelName = null,
        array $applicantIdentifiers = [],
    ): array {
        $this->ensureEnabled();

        $payload = [
            'userId' => KycUserType::externalId($userType, $userId),
            'levelName' => $levelName ?? $this->levelFor($userType),
            'ttlInSecs' => (int) config('sumsub.access_token_ttl', 600),
        ];

        if ($applicantIdentifiers !== []) {
            $payload['applicantIdentifiers'] = array_filter($applicantIdentifiers);
        }

        $response = $this->signedRequest('POST', '/resources/accessTokens/sdk', $payload);

        if (! $response->successful()) {
            throw new RuntimeException('Sumsub access token request failed: '.$response->body());
        }

        /** @var array{token: string, userId: string} $data */
        $data = $response->json();

        return $data;
    }

    /**
     * Fetch the full applicant record using our own external userId.
     *
     * @return array<string, mixed>|null
     */
    public function getApplicantByExternalUserId(string $externalUserId): ?array
    {
        $this->ensureEnabled();

        $response = $this->signedRequest(
            'GET',
            '/resources/applicants/-;externalUserId/'.rawurlencode($externalUserId).'/one'
        );

        if ($response->status() === 404) {
            return null;
        }

        if (! $response->successful()) {
            throw new RuntimeException('Sumsub applicant lookup failed: '.$response->body());
        }

        return $response->json();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getApplicantById(string $applicantId): ?array
    {
        $this->ensureEnabled();

        $response = $this->signedRequest('GET', '/resources/applicants/'.rawurlencode($applicantId).'/one');

        if ($response->status() === 404) {
            return null;
        }

        if (! $response->successful()) {
            throw new RuntimeException('Sumsub applicant lookup failed: '.$response->body());
        }

        return $response->json();
    }

    /**
     * Ask Sumsub to reset an applicant so the user can re-submit documents
     * after a rejection.
     */
    public function resetApplicant(string $applicantId): bool
    {
        $this->ensureEnabled();

        $response = $this->signedRequest(
            'POST',
            '/resources/applicants/'.rawurlencode($applicantId).'/reset'
        );

        return $response->successful();
    }

    /**
     * Verify the X-Payload-Digest header sent with every webhook.
     */
    public function verifyWebhookSignature(string $rawBody, ?string $digest, ?string $algorithm): bool
    {
        $secret = (string) config('sumsub.webhook_secret');

        if ($secret === '' || $digest === null) {
            return false;
        }

        $algo = match (strtoupper((string) $algorithm)) {
            'HMAC_SHA512_HEX', 'SHA512' => 'sha512',
            default => 'sha256',
        };

        $expected = hash_hmac($algo, $rawBody, $secret);

        return hash_equals($expected, strtolower($digest));
    }

    /**
     * Translate a Sumsub applicant payload into one of our internal statuses.
     *
     * @param  array<string, mixed>  $applicant
     */
    public function mapStatus(array $applicant): string
    {
        $reviewStatus = $applicant['reviewStatus'] ?? null;
        $reviewAnswer = $applicant['reviewResult']['reviewAnswer'] ?? null;

        if ($reviewStatus === 'onHold') {
            return KycStatus::ON_HOLD;
        }

        if ($reviewStatus === 'completed') {
            if ($reviewAnswer === 'GREEN') {
                return KycStatus::APPROVED;
            }

            if ($reviewAnswer === 'RED') {
                return ($applicant['reviewResult']['reviewRejectType'] ?? null) === 'FINAL'
                    ? KycStatus::REJECTED
                    : KycStatus::RESET;
            }
        }

        // Nothing has been submitted yet - the applicant only exists because
        // we minted an access token for it.
        if ($reviewStatus === 'init' && empty($applicant['idDocs'])) {
            return KycStatus::NOT_STARTED;
        }

        return KycStatus::PENDING;
    }

    /**
     * @param  array<string, mixed>  $body
     */
    protected function signedRequest(string $method, string $path, array $body = []): Response
    {
        $timestamp = (string) time();
        $payload = $body === [] ? '' : json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $payload = $payload === false ? '' : $payload;

        $request = $this->client()->withHeaders([
            'X-App-Token' => (string) $this->appToken(),
            'X-App-Access-Sig' => $this->signature($timestamp, $method, $path, $payload),
            'X-App-Access-Ts' => $timestamp,
            'Accept' => 'application/json',
        ]);

        $response = match (strtoupper($method)) {
            'POST' => $request->withBody($payload, 'application/json')->post($this->url($path)),
            'PUT' => $request->withBody($payload, 'application/json')->put($this->url($path)),
            'DELETE' => $request->delete($this->url($path)),
            default => $request->get($this->url($path)),
        };

        if ($response->failed()) {
            $this->log('warning', 'Sumsub request failed', [
                'method' => $method,
                'path' => $path,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
        }

        return $response;
    }

    protected function client(): PendingRequest
    {
        $request = Http::timeout((int) config('sumsub.timeout', 15))->acceptJson();

        if (! $this->isConfigured()) {
            throw new RuntimeException('Sumsub is not configured. Set SUMSUB_APP_TOKEN and SUMSUB_SECRET_KEY.');
        }

        return $request;
    }

    protected function ensureEnabled(): void
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('Sumsub is not configured. Set SUMSUB_APP_TOKEN and SUMSUB_SECRET_KEY.');
        }
    }

    protected function url(string $path): string
    {
        return rtrim((string) config('sumsub.base_url', 'https://api.sumsub.com'), '/').$path;
    }

    protected function appToken(): ?string
    {
        return config('sumsub.app_token');
    }

    protected function secretKey(): ?string
    {
        return config('sumsub.secret_key');
    }

    /**
     * @param  array<string, mixed>  $context
     */
    protected function log(string $level, string $message, array $context = []): void
    {
        $channel = config('sumsub.log_channel');

        $logger = $channel ? Log::channel($channel) : Log::getFacadeRoot();

        $logger->{$level}($message, $context);
    }
}
