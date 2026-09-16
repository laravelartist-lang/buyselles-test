<?php

namespace App\Console\Commands;

use App\Enums\KycUserType;
use App\Models\KycVerification;
use App\Services\Kyc\KycService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * Fire a correctly signed Sumsub webhook at the local application.
 *
 * This exists so the KYC callback path can be exercised end to end - routing,
 * the CSRF-exempt webhook route, HMAC signature verification, status mapping
 * and persistence - without waiting for Sumsub and without touching a live
 * environment. It never calls the Sumsub API; it only posts to the URL you
 * point it at, which defaults to this application's own webhook route.
 */
class KycSimulateWebhook extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'kyc:simulate-webhook
        {scenario : approved|rejected|reset|on_hold|pending|started}
        {--verification= : Target an existing kyc_verifications row by id}
        {--user-type=customer : customer or vendor, used with --user-id}
        {--user-id= : Target this user; the verification row is created when missing}
        {--reject-label=* : Reject label to attach, e.g. FORGERY (repeatable)}
        {--comment= : moderationComment stored with a rejection}
        {--url= : Override the webhook URL, defaults to app.url + the configured webhook path}
        {--insecure : Skip TLS verification (useful for self signed local certificates)}
        {--dry-run : Print the signed request instead of sending it}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send a signed Sumsub webhook to the local app so KYC status changes can be tested safely';

    /**
     * @var array<int, string>
     */
    private const SCENARIOS = ['approved', 'rejected', 'reset', 'on_hold', 'pending', 'started'];

    /**
     * Execute the console command.
     */
    public function handle(KycService $kycService): int
    {
        $scenario = (string) $this->argument('scenario');

        if (! in_array($scenario, self::SCENARIOS, true)) {
            $this->error("Unknown scenario [{$scenario}]. Available: ".implode(', ', self::SCENARIOS));

            return self::FAILURE;
        }

        $verification = $this->resolveVerification($kycService);

        if ($verification === null) {
            return self::FAILURE;
        }

        $secret = (string) config('sumsub.webhook_secret');

        if ($secret === '') {
            $this->error('SUMSUB_WEBHOOK_SECRET is not configured, so no webhook can be signed.');

            return self::FAILURE;
        }

        $this->reportState('before', $verification);

        $body = json_encode(
            $this->payloadFor($scenario, $verification->external_user_id),
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );

        if ($body === false) {
            $this->error('Unable to encode the simulated payload.');

            return self::FAILURE;
        }

        $digest = hash_hmac('sha256', $body, $secret);
        $url = $this->webhookUrl();

        $this->newLine();
        $this->line('  POST '.$url);
        $this->line('  scenario: '.$scenario.'   externalUserId: '.$verification->external_user_id);

        if ($this->option('dry-run')) {
            $this->line('  X-Payload-Digest: '.$digest);
            $this->line('  body: '.$body);
            $this->newLine();
            $this->warn('Dry run: nothing was sent.');

            return self::SUCCESS;
        }

        $request = Http::withHeaders([
            'X-Payload-Digest' => $digest,
            'X-Payload-Digest-Alg' => 'HMAC_SHA256_HEX',
            'Accept' => 'application/json',
        ]);

        if ($this->option('insecure') || $this->isLocalHost($url)) {
            $request = $request->withOptions(['verify' => false]);
        }

        $response = $request->withBody($body, 'application/json')->post($url);

        $this->line('  response: HTTP '.$response->status().' '.trim($response->body()));
        $this->newLine();

        if ($response->status() === 401) {
            $this->error('The app rejected the signature. The local SUMSUB_WEBHOOK_SECRET probably differs from the one used to sign.');
        }

        if ($response->redirect()) {
            $this->warn('The local site redirected the request. Pass --url with the final https URL.');
        }

        $this->reportState('after', $verification->refresh());

        return $response->successful() ? self::SUCCESS : self::FAILURE;
    }

    /**
     * Resolve the verification to act on, creating it when --user-id is given.
     */
    private function resolveVerification(KycService $kycService): ?KycVerification
    {
        $id = $this->option('verification');

        if ($id !== null && $id !== '') {
            $verification = KycVerification::query()->find((int) $id);

            if ($verification === null) {
                $this->error("No kyc_verifications row with id [{$id}].");

                return null;
            }

            return $verification;
        }

        $userId = $this->option('user-id');

        if ($userId !== null && $userId !== '') {
            $userType = (string) $this->option('user-type');

            if (! in_array($userType, KycUserType::all(), true)) {
                $this->error("Unknown --user-type [{$userType}]. Available: ".implode(', ', KycUserType::all()));

                return null;
            }

            return $kycService->ensureVerification($userType, (int) $userId, true);
        }

        $verification = KycVerification::query()->latest('id')->first();

        if ($verification === null) {
            $this->error('There are no KYC verifications yet.');
            $this->line('  Create one with: php artisan kyc:simulate-webhook approved --user-id=1');

            return null;
        }

        $this->warn('No target given, using the most recent verification (id '.$verification->id.').');

        return $verification;
    }

    /**
     * Build a payload shaped like the one Sumsub posts for the given scenario.
     *
     * @return array<string, mixed>
     */
    private function payloadFor(string $scenario, string $externalUserId): array
    {
        $base = [
            'applicantId' => 'sim-'.$externalUserId,
            'externalUserId' => $externalUserId,
            'inspectionId' => 1,
            'correlationId' => 'sim-'.now()->getTimestamp(),
        ];

        /** @var array<int, string> $labels */
        $labels = $this->option('reject-label');
        $comment = $this->option('comment');

        return match ($scenario) {
            'approved' => $base + [
                'type' => 'applicantReviewed',
                'reviewStatus' => 'completed',
                'reviewResult' => [
                    'reviewAnswer' => 'GREEN',
                    'rejectLabels' => [],
                ],
            ],
            'rejected' => $base + [
                'type' => 'applicantReviewed',
                'reviewStatus' => 'completed',
                'reviewResult' => [
                    'reviewAnswer' => 'RED',
                    'reviewRejectType' => 'FINAL',
                    'rejectLabels' => $labels !== [] ? $labels : ['FORGERY'],
                    'moderationComment' => $comment ?: 'Simulated final rejection',
                ],
            ],
            'reset' => $base + [
                'type' => 'applicantReviewed',
                'reviewStatus' => 'completed',
                'reviewResult' => [
                    'reviewAnswer' => 'RED',
                    'reviewRejectType' => 'RETRY',
                    'rejectLabels' => $labels !== [] ? $labels : ['DOCUMENT_PAGE_MISSING'],
                    'moderationComment' => $comment ?: 'Simulated retry request',
                ],
            ],
            'on_hold' => $base + [
                'type' => 'applicantOnHold',
                'reviewStatus' => 'onHold',
            ],
            'pending' => $base + [
                'type' => 'applicantPending',
                'reviewStatus' => 'pending',
            ],
            'started' => $base + [
                'type' => 'applicantCreated',
                'reviewStatus' => 'init',
                'idDocs' => [],
            ],
        };
    }

    /**
     * The URL the webhook is posted to.
     */
    private function webhookUrl(): string
    {
        $override = (string) $this->option('url');

        if ($override !== '') {
            return $override;
        }

        $base = rtrim((string) config('app.url'), '/');
        $path = ltrim((string) config('sumsub.webhook_path', 'webhooks/sumsub'), '/');
        $url = $base.'/'.$path;

        // Valet and friends usually force HTTPS and a 301 on POST would be
        // downgraded to a GET, so upgrade the scheme for local .test hosts.
        if (str_starts_with($url, 'http://') && str_ends_with($this->hostOf($url), '.test')) {
            $url = 'https://'.substr($url, strlen('http://'));
        }

        return $url;
    }

    private function hostOf(string $url): string
    {
        return (string) (parse_url($url, PHP_URL_HOST) ?: '');
    }

    private function isLocalHost(string $url): bool
    {
        $host = $this->hostOf($url);

        return $host === 'localhost' || $host === '127.0.0.1' || str_ends_with($host, '.test');
    }

    /**
     * Print the stored KYC state of a verification.
     */
    private function reportState(string $when, KycVerification $verification): void
    {
        $this->line(sprintf(
            '  %-6s status=%s review_answer=%s reject_type=%s verified_at=%s synced_at=%s',
            $when.':',
            $verification->status,
            $verification->review_answer ?? '-',
            $verification->reject_type ?? '-',
            $verification->verified_at?->toDateTimeString() ?? '-',
            $verification->last_synced_at?->toDateTimeString() ?? '-',
        ));
    }
}
