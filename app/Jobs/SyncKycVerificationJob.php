<?php

namespace App\Jobs;

use App\Models\KycVerification;
use App\Services\Kyc\KycService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Pulls the latest applicant state for a single verification from Sumsub.
 *
 * This deliberately runs on the queue: a Sumsub request can hold the worker
 * for up to config('sumsub.timeout') seconds, and the status endpoints are
 * polled by both mobile apps. Syncing inline would occupy PHP workers and
 * stall every other user, so requests only read the local mirror and ask for
 * a refresh.
 */
class SyncKycVerificationJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [10, 60, 300];

    /**
     * Release the uniqueness lock after this many seconds so a dropped worker
     * can never block later refreshes for the same verification forever.
     */
    public int $uniqueFor = 300;

    public function __construct(
        private readonly int $verificationId,
    ) {}

    public function uniqueId(): string
    {
        return 'sync-kyc-verification-'.$this->verificationId;
    }

    public function handle(KycService $kycService): void
    {
        $verification = KycVerification::find($this->verificationId);

        if (! $verification) {
            Log::info('Skipped KYC sync for a verification that no longer exists', [
                'verification_id' => $this->verificationId,
            ]);

            return;
        }

        $kycService->syncFromSumsub($verification);
    }
}
