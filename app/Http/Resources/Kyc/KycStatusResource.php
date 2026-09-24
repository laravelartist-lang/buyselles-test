<?php

namespace App\Http\Resources\Kyc;

use App\Enums\KycStatus;
use App\Models\KycVerification;
use App\Services\Kyc\KycService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The single response shape consumed by the web storefront, the customer app
 * and the vendor app, so every client reacts to KYC state identically.
 *
 * @mixin KycVerification
 */
class KycStatusResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var KycVerification|null $verification */
        $verification = $this->resource;

        $kycService = app(KycService::class);

        $status = $verification?->status ?? KycStatus::NOT_STARTED;
        $isVerified = $status === KycStatus::APPROVED;

        return [
            'required' => (bool) $verification?->required_at,
            'status' => $status,
            'is_verified' => $isVerified,
            'is_in_progress' => in_array($status, KycStatus::inProgressStatuses(), true),
            'needs_resubmission' => in_array($status, [KycStatus::REJECTED, KycStatus::RESET], true),
            'applicant_id' => $verification?->applicant_id,
            'level_name' => $verification?->level_name ?? config('sumsub.levels.customer'),
            'rejection_reason' => $verification?->moderation_comment,
            'reject_labels' => $verification?->reject_labels ?? [],
            'verified_at' => $verification?->verified_at?->toIso8601String(),
            'required_at' => $verification?->required_at?->toIso8601String(),
            'threshold' => $kycService->purchaseThreshold(),
            'verification_enabled' => $kycService->isEnabled(),
        ];
    }
}
