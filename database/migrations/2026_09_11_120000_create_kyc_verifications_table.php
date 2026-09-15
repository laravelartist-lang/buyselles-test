<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kyc_verifications', function (Blueprint $table): void {
            $table->id();
            $table->string('user_type', 20)->comment('customer or vendor');
            $table->unsignedBigInteger('user_id');
            $table->string('external_user_id', 120)->unique()->comment('Sumsub applicant userId, e.g. customer_42');
            $table->string('applicant_id', 120)->nullable()->index();
            $table->string('level_name', 120);
            $table->string('status', 30)->default('not_started');
            $table->string('review_answer', 20)->nullable()->comment('GREEN, RED or YELLOW');
            $table->string('reject_type', 20)->nullable()->comment('FINAL or RETRY');
            $table->json('reject_labels')->nullable();
            $table->text('moderation_comment')->nullable();
            $table->timestamp('required_at')->nullable()->comment('Set when the account became obliged to complete KYC');
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->json('last_webhook_payload')->nullable();
            $table->timestamps();

            $table->index(['user_type', 'user_id'], 'kyc_verifications_user_idx');
            $table->index(['user_type', 'status'], 'kyc_verifications_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kyc_verifications');
    }
};
