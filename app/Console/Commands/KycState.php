<?php

namespace App\Console\Commands;

use App\Enums\KycUserType;
use App\Models\KycVerification;
use App\Services\Kyc\KycService;
use Illuminate\Console\Command;

/**
 * Inspect the KYC state the application has stored.
 *
 * Handy alongside kyc:simulate-webhook: run it before and after a simulated
 * callback to see exactly which fields the webhook changed.
 */
class KycState extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'kyc:state
        {externalUserId? : Show a single verification by its Sumsub external user id}
        {--user-type= : Filter by customer or vendor}
        {--limit=25 : Maximum number of rows to list}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Inspect the KYC verification state stored by this application';

    /**
     * Execute the console command.
     */
    public function handle(KycService $kycService): int
    {
        $this->reportConfiguration($kycService);

        $externalUserId = $this->argument('externalUserId');

        if ($externalUserId !== null && $externalUserId !== '') {
            return $this->reportOne((string) $externalUserId);
        }

        return $this->reportMany();
    }

    private function reportConfiguration(KycService $kycService): void
    {
        $this->line('  environment:     '.app()->environment());
        $this->line('  app.url:         '.config('app.url'));
        $this->line('  sumsub enabled:  '.($kycService->isEnabled() ? 'yes' : 'no'));
        $this->line('  webhook secret:  '.(config('sumsub.webhook_secret') ? 'set' : 'MISSING'));
        $this->line('  webhook path:    '.(string) config('sumsub.webhook_path', 'webhooks/sumsub'));
        $this->line('  purchase threshold: '.$kycService->purchaseThreshold());
        $this->newLine();
    }

    private function reportOne(string $externalUserId): int
    {
        $verification = KycVerification::query()
            ->where('external_user_id', $externalUserId)
            ->first();

        if ($verification === null) {
            $this->error("No KYC verification for external user id [{$externalUserId}].");

            return self::FAILURE;
        }

        $this->table(['field', 'value'], [
            ['id', $verification->id],
            ['user_type', $verification->user_type],
            ['user_id', $verification->user_id],
            ['external_user_id', $verification->external_user_id],
            ['applicant_id', $verification->applicant_id ?? '-'],
            ['level_name', $verification->level_name],
            ['status', $verification->status],
            ['review_answer', $verification->review_answer ?? '-'],
            ['reject_type', $verification->reject_type ?? '-'],
            ['reject_labels', $verification->reject_labels === null ? '-' : json_encode($verification->reject_labels)],
            ['moderation_comment', $verification->moderation_comment ?? '-'],
            ['required_at', $verification->required_at?->toDateTimeString() ?? '-'],
            ['verified_at', $verification->verified_at?->toDateTimeString() ?? '-'],
            ['last_synced_at', $verification->last_synced_at?->toDateTimeString() ?? '-'],
            ['blocks account', $verification->blocksAccount() ? 'yes' : 'no'],
        ]);

        return self::SUCCESS;
    }

    private function reportMany(): int
    {
        $query = KycVerification::query()->latest('id');

        $userType = (string) $this->option('user-type');

        if ($userType !== '') {
            if (! in_array($userType, KycUserType::all(), true)) {
                $this->error("Unknown --user-type [{$userType}]. Available: ".implode(', ', KycUserType::all()));

                return self::FAILURE;
            }

            $query->where('user_type', $userType);
        }

        $limit = max(1, (int) $this->option('limit'));

        /** @var \Illuminate\Support\Collection<int, KycVerification> $verifications */
        $verifications = $query->limit($limit)->get();

        if ($verifications->isEmpty()) {
            $this->warn('No KYC verifications found yet.');
            $this->line('  Create one with: php artisan kyc:simulate-webhook approved --user-id=1');

            return self::SUCCESS;
        }

        $this->table(
            ['id', 'user', 'external_user_id', 'status', 'answer', 'reject', 'verified_at', 'synced_at'],
            $verifications->map(fn (KycVerification $verification): array => [
                $verification->id,
                $verification->user_type.'#'.$verification->user_id,
                $verification->external_user_id,
                $verification->status,
                $verification->review_answer ?? '-',
                $verification->reject_type ?? '-',
                $verification->verified_at?->toDateTimeString() ?? '-',
                $verification->last_synced_at?->toDateTimeString() ?? '-',
            ])->all()
        );

        return self::SUCCESS;
    }
}
