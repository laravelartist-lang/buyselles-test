<?php

namespace Tests\Feature;

use App\Enums\KycStatus;
use App\Enums\KycUserType;
use App\Http\Controllers\Admin\KycManagementController;
use App\Jobs\SyncKycVerificationJob;
use App\Models\KycVerification;
use App\Models\Seller;
use App\Models\User;
use App\Services\Kyc\KycService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Laravel\Passport\Passport;
use Tests\Concerns\ManagesTestDatabaseSchema;
use Tests\TestCase;

/**
 * Every KYC status surface must read the local mirror and hand the Sumsub
 * refresh to a queued job.
 *
 * A Sumsub request can hold a PHP worker for up to config('sumsub.timeout')
 * seconds and both mobile apps poll these endpoints, so an inline call is what
 * makes one slow applicant stall everybody else. These tests fail if any
 * surface starts talking to Sumsub inside the request cycle again.
 */
class KycSyncQueuedTest extends TestCase
{
    use ManagesTestDatabaseSchema;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();

        config([
            'sumsub.enabled' => true,
            'sumsub.app_token' => 'test-app-token',
            'sumsub.secret_key' => 'test-secret-key',
            'sumsub.webhook_secret' => 'test-webhook-secret',
            'sumsub.levels.customer' => 'customer-kyc',
            'sumsub.levels.vendor' => 'vendor-kyc',
        ]);

        $this->recreateTable('business_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('type')->nullable();
            $table->longText('value')->nullable();
            $table->timestamps();
        });

        $this->recreateTable('users', function (Blueprint $table): void {
            $table->id();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->boolean('is_active')->default(1);
            $table->timestamps();
        });

        $this->recreateTable('sellers', function (Blueprint $table): void {
            $table->id();
            $table->string('f_name')->nullable();
            $table->string('l_name')->nullable();
            $table->string('email')->nullable();
            $table->string('status')->default('pending');
            $table->string('auth_token')->nullable();
            $table->timestamps();
        });

        $this->recreateTable('orders', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->unsignedBigInteger('seller_id')->nullable();
            $table->boolean('is_guest')->default(0);
            $table->decimal('order_amount', 24, 2)->default(0);
            $table->string('payment_status')->default('unpaid');
            $table->string('order_status')->default('pending');
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
        $this->storeSetting('kyc_verification_status', '1');

        Cache::flush();
    }

    public function test_the_customer_app_poll_returns_the_mirror_while_the_refresh_waits_on_the_queue(): void
    {
        Queue::fake();
        Http::fake(['api.sumsub.com/*' => Http::response($this->approvedApplicant(), 200)]);

        $user = $this->customer();

        Passport::actingAs($user, ['*'], 'api');

        $response = $this->withHeader('Authorization', 'Bearer test-token')
            ->getJson('api/v1/customer/kyc/status');

        $response->assertOk();

        // Sumsub would say "approved" - the response must still be the local
        // mirror, which proves the API call did not happen inside the request.
        $response->assertJsonPath('kyc.status', KycStatus::NOT_STARTED);

        Http::assertNothingSent();

        Queue::assertPushed(
            SyncKycVerificationJob::class,
            fn (SyncKycVerificationJob $job): bool => $job->uniqueId() === 'sync-kyc-verification-'.$this->verificationIdFor($user)
        );

        $this->runQueuedSyncs();

        Passport::actingAs($user, ['*'], 'api');

        $this->withHeader('Authorization', 'Bearer test-token')
            ->getJson('api/v1/customer/kyc/status')
            ->assertJsonPath('kyc.status', KycStatus::APPROVED);
    }

    public function test_the_web_customer_status_poll_queues_the_refresh(): void
    {
        Queue::fake();
        Http::fake();

        $user = $this->customer();

        $this->actingAs($user, 'customer')
            ->getJson(route('customer.kyc.status'))
            ->assertOk();

        Http::assertNothingSent();
        Queue::assertPushed(SyncKycVerificationJob::class);
    }

    public function test_the_vendor_panel_status_poll_queues_the_refresh(): void
    {
        Queue::fake();
        Http::fake();

        $seller = $this->vendor();

        $this->actingAs($seller, 'seller')
            ->getJson(route('vendor.kyc.status'))
            ->assertOk();

        Http::assertNothingSent();
        Queue::assertPushed(SyncKycVerificationJob::class);
    }

    public function test_the_vendor_app_status_poll_queues_the_refresh(): void
    {
        Queue::fake();
        Http::fake();

        $seller = $this->vendor();

        $this->withHeader('Authorization', 'Bearer '.$seller->auth_token)
            ->getJson('api/v3/seller/kyc/status')
            ->assertOk();

        Http::assertNothingSent();
        Queue::assertPushed(SyncKycVerificationJob::class);
    }

    public function test_no_refresh_is_queued_for_a_verification_that_is_already_approved(): void
    {
        Queue::fake();
        Http::fake();

        $user = $this->customer();
        $this->verification(KycUserType::CUSTOMER, $user->id, KycStatus::APPROVED);

        $this->actingAs($user, 'customer')
            ->getJson(route('customer.kyc.status'))
            ->assertOk();

        Queue::assertNothingPushed();
        Http::assertNothingSent();
    }

    /**
     * The polling surfaces skip approved verifications, but the admin Sync
     * action exists to force a refresh - including on an approved applicant,
     * which is exactly the case that would otherwise never be looked at again.
     */
    public function test_the_admin_sync_action_still_queues_a_refresh_for_an_approved_verification(): void
    {
        Queue::fake();
        Http::fake();

        $user = $this->customer();
        $verification = $this->verification(KycUserType::CUSTOMER, $user->id, KycStatus::APPROVED);

        app(KycManagementController::class)->sync($verification->id);

        Queue::assertPushed(
            SyncKycVerificationJob::class,
            fn (SyncKycVerificationJob $job): bool => $job->uniqueId() === 'sync-kyc-verification-'.$verification->id
        );
        Http::assertNothingSent();
    }

    public function test_no_refresh_is_queued_when_sumsub_is_not_configured(): void
    {
        Queue::fake();
        Http::fake();

        config(['sumsub.secret_key' => null]);

        $user = $this->customer();

        $this->actingAs($user, 'customer')
            ->getJson(route('customer.kyc.status'))
            ->assertOk();

        Queue::assertNothingPushed();
        Http::assertNothingSent();
    }

    public function test_the_job_applies_the_sumsub_state_to_the_local_mirror(): void
    {
        Http::fake(['api.sumsub.com/*' => Http::response($this->approvedApplicant(), 200)]);

        $user = $this->customer();
        $verification = $this->verification(KycUserType::CUSTOMER, $user->id, KycStatus::NOT_STARTED);

        $this->assertNull($verification->last_synced_at);

        (new SyncKycVerificationJob($verification->id))->handle(app(KycService::class));

        $verification->refresh();

        $this->assertSame(KycStatus::APPROVED, $verification->status);
        $this->assertSame('GREEN', $verification->review_answer);
        $this->assertNotNull($verification->verified_at);
        $this->assertNotNull($verification->last_synced_at);
        $this->assertSame('app-123', $verification->applicant_id);
        $this->assertSame('completed', $verification->last_webhook_payload['reviewStatus']);

        Http::assertSent(fn ($request): bool => str_contains($request->url(), 'customer_'.$user->id));
    }

    /**
     * getApplicantByExternalUserId() returns the applicant resource, which
     * carries the id under a different key than the webhook does. Both shapes
     * have to land in the same column or the admin Reset action breaks again.
     */
    public function test_the_job_accepts_the_applicant_id_from_the_api_payload_shape(): void
    {
        $applicant = $this->approvedApplicant();
        unset($applicant['applicantId']);
        $applicant['id'] = 'app-456';

        Http::fake(['api.sumsub.com/*' => Http::response($applicant, 200)]);

        $user = $this->customer();
        $verification = $this->verification(KycUserType::CUSTOMER, $user->id, KycStatus::NOT_STARTED);

        (new SyncKycVerificationJob($verification->id))->handle(app(KycService::class));

        $verification->refresh();

        $this->assertSame(KycStatus::APPROVED, $verification->status);
        $this->assertSame('app-456', $verification->applicant_id);
    }

    public function test_the_job_skips_a_verification_that_was_deleted_before_it_ran(): void
    {
        Http::fake();

        $user = $this->customer();
        $verification = $this->verification(KycUserType::CUSTOMER, $user->id, KycStatus::NOT_STARTED);
        $verificationId = $verification->id;

        $verification->delete();

        (new SyncKycVerificationJob($verificationId))->handle(app(KycService::class));

        Http::assertNothingSent();
    }

    public function test_the_job_is_unique_per_verification_and_expires_its_lock(): void
    {
        $job = new SyncKycVerificationJob(42);

        $this->assertSame('sync-kyc-verification-42', $job->uniqueId());
        $this->assertNotSame($job->uniqueId(), (new SyncKycVerificationJob(43))->uniqueId());
        $this->assertGreaterThan(0, $job->uniqueFor);
    }

    /**
     * The guarantee only holds while no request cycle reaches Sumsub directly,
     * so this pins it down statically: SyncKycVerificationJob is the single
     * caller of the blocking sync in the whole application.
     */
    public function test_the_only_caller_of_the_blocking_sync_is_the_queued_job(): void
    {
        $offenders = [];

        foreach (File::allFiles(app_path()) as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            if (str_contains($file->getContents(), '->syncFromSumsub(')) {
                $offenders[] = str_replace(base_path().'/', '', $file->getPathname());
            }
        }

        $this->assertSame(
            ['app/Jobs/SyncKycVerificationJob.php'],
            $offenders,
            'A request cycle is calling Sumsub inline again. Use KycService::queueSyncFromSumsub() instead.'
        );
    }

    private function runQueuedSyncs(): void
    {
        KycVerification::query()->each(function (KycVerification $verification): void {
            (new SyncKycVerificationJob($verification->id))->handle(app(KycService::class));
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function approvedApplicant(): array
    {
        return [
            'applicantId' => 'app-123',
            'reviewStatus' => 'completed',
            'reviewResult' => [
                'reviewAnswer' => 'GREEN',
                'reviewRejectType' => null,
            ],
            'idDocs' => [
                ['idDocType' => 'PASSPORT', 'country' => 'NLD'],
            ],
        ];
    }

    private function verificationIdFor(User $user): int
    {
        return (int) KycVerification::query()->where('user_id', $user->id)->value('id');
    }

    private function customer(): User
    {
        $user = new User;
        $user->email = 'buyer-'.uniqid().'@example.com';
        $user->is_active = 1;
        $user->save();

        return $user;
    }

    private function vendor(): Seller
    {
        return Seller::create([
            'f_name' => 'Test',
            'l_name' => 'Vendor',
            'email' => 'vendor-'.uniqid().'@example.com',
            'status' => 'approved',
            'auth_token' => bin2hex(random_bytes(24)),
        ]);
    }

    private function verification(string $userType, int $userId, string $status): KycVerification
    {
        return KycVerification::create([
            'user_type' => $userType,
            'user_id' => $userId,
            'external_user_id' => $userType.'_'.$userId,
            'level_name' => $userType.'-kyc',
            'status' => $status,
            'required_at' => $status === KycStatus::APPROVED ? now()->subDay() : now(),
            'verified_at' => $status === KycStatus::APPROVED ? now() : null,
        ]);
    }

    private function storeSetting(string $type, string $value): void
    {
        DB::table('business_settings')->updateOrInsert(
            ['type' => $type],
            ['value' => $value, 'updated_at' => now(), 'created_at' => now()],
        );

        Cache::flush();
    }
}
