<?php

namespace Tests\Concerns;

use App\Enums\KycStatus;
use App\Enums\KycUserType;
use App\Models\KycVerification;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

trait ApprovesVendorKycForSellerApiTests
{
    protected function ensureSellerApiKycSchema(): void
    {
        if (Schema::hasTable('kyc_verifications')) {
            return;
        }

        Schema::create('kyc_verifications', function (Blueprint $table): void {
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
    }

    protected function approveVendorKycForSellerApi(int $sellerId): void
    {
        $this->ensureSellerApiKycSchema();

        KycVerification::query()->updateOrCreate(
            [
                'user_type' => KycUserType::VENDOR,
                'user_id' => $sellerId,
            ],
            [
                'external_user_id' => 'vendor_'.$sellerId,
                'level_name' => 'vendor-kyc',
                'status' => KycStatus::APPROVED,
                'required_at' => now()->subDay(),
                'verified_at' => now(),
            ],
        );

        Cache::flush();
    }
}
