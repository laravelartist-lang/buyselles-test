<?php

namespace App\Services\Kyc;

use App\Enums\KycStatus;
use App\Enums\KycUserType;
use App\Jobs\SyncKycVerificationJob;
use App\Models\KycVerification;
use App\Models\Order;
use App\Models\User;
use App\User as AuthenticatedUser;
use App\Utils\OrderManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Single source of truth for KYC state.
 *
 * Both the web storefront and the two mobile apps resolve their KYC status
 * through this service, so a customer who verifies on the web is
 * immediately unblocked inside the app (and vice versa).
 */
class KycService
{
    public function __construct(
        private readonly SumsubService $sumsub,
    ) {}

    public function isEnabled(): bool
    {
        $setting = getWebConfig(name: 'kyc_verification_status');

        if ($setting !== null && $setting !== '') {
            return (bool) $setting && $this->sumsub->isConfigured();
        }

        return $this->sumsub->isEnabled();
    }

    public function purchaseThreshold(): float
    {
        $setting = getWebConfig(name: 'kyc_customer_purchase_threshold');

        if ($setting !== null && $setting !== '' && is_numeric($setting)) {
            return (float) $setting;
        }

        return (float) config('sumsub.customer_threshold', 100);
    }

    public function externalId(string $userType, int $userId): string
    {
        return KycUserType::externalId($userType, $userId);
    }

    public function findVerification(string $userType, int $userId): ?KycVerification
    {
        return KycVerification::query()
            ->where('user_type', $userType)
            ->where('user_id', $userId)
            ->first();
    }

    /**
     * Return the existing verification or create a fresh "not started" one.
     */
    public function ensureVerification(string $userType, int $userId, bool $required = false): KycVerification
    {
        $verification = $this->findVerification($userType, $userId);

        if ($verification) {
            if ($required && $verification->required_at === null) {
                $verification->required_at = now();
                $verification->save();
            }

            return $verification;
        }

        return KycVerification::create([
            'user_type' => $userType,
            'user_id' => $userId,
            'external_user_id' => $this->externalId($userType, $userId),
            'level_name' => $this->sumsub->levelFor($userType),
            'status' => KycStatus::NOT_STARTED,
            'required_at' => $required ? now() : null,
        ]);
    }

    /**
     * Resolve the acting customer's id from whichever user model holds them.
     *
     * The `customer` and `api` guards are backed by the legacy `App\User`
     * model (see config/auth.php) while factories and newer controllers use
     * `App\Models\User`. They are unrelated classes over the same `users`
     * table, so every customer decision has to accept either one - checking
     * for a single class silently lets real customers through.
     */
    public function resolveCustomerId(mixed $user): ?int
    {
        if ($user instanceof User || $user instanceof AuthenticatedUser) {
            return (int) $user->id;
        }

        return null;
    }

    /**
     * Resolve a customer's verification record, arming the requirement as soon
     * as their lifetime purchases already crossed the threshold.
     */
    public function evaluateCustomerStatus(User|AuthenticatedUser $user): KycVerification
    {
        $verification = $this->ensureVerification(KycUserType::CUSTOMER, $user->id);

        if (! $this->isEnabled() || $verification->isApproved()) {
            return $verification;
        }

        if ($verification->required_at === null
            && $this->customerPurchaseTotal($user->id) >= $this->purchaseThreshold()) {
            $verification->required_at = now();
            $verification->save();
        }

        return $verification;
    }

    public function isVerified(string $userType, int $userId): bool
    {
        return (bool) $this->findVerification($userType, $userId)?->isApproved();
    }

    /**
     * Lifetime value of paid, non cancelled orders for a customer.
     */
    public function customerPurchaseTotal(int $customerId): float
    {
        return (float) Order::query()
            ->where('customer_id', $customerId)
            ->where('is_guest', 0)
            ->where('payment_status', 'paid')
            ->whereNotIn('order_status', ['canceled', 'returned', 'failed'])
            ->sum('order_amount');
    }

    /**
     * Value of the carts the customer is currently checking out.
     */
    public function resolvePendingOrderAmount(Request $request): float
    {
        try {
            $vendorWiseCartList = OrderManager::processOrderGenerateData(data: [
                'coupon_code' => $request->input('coupon_code', session('coupon_code') ?? ''),
                'requestObj' => $request,
            ]);
        } catch (Throwable $exception) {
            Log::warning('Unable to resolve pending order amount for KYC evaluation', [
                'message' => $exception->getMessage(),
            ]);

            return 0.0;
        }

        $collection = collect($vendorWiseCartList);
        $amount = (float) (
            $collection->sum('order_amount_with_tax')
            - $collection->sum('refer_and_earn_discount')
        );

        return max($amount, 0.0);
    }

    /**
     * Whether a customer must complete KYC, taking into account the amount
     * they are about to spend.
     *
     * `$user` is typed as mixed because guest checkouts resolve to the string
     * 'offline' rather than a User instance.
     */
    public function requiresCustomerVerification(mixed $user, float $pendingAmount = 0.0): bool
    {
        $customerId = $this->resolveCustomerId($user);

        if (! $this->isEnabled() || $customerId === null) {
            return false;
        }

        $verification = $this->findVerification(KycUserType::CUSTOMER, $customerId);

        if ($verification?->isApproved()) {
            return false;
        }

        // Once triggered the requirement is sticky - refunds or cancelled
        // orders must not unlock the account again.
        if ($verification?->required_at !== null) {
            return true;
        }

        return ($this->customerPurchaseTotal($customerId) + $pendingAmount) >= $this->purchaseThreshold();
    }

    /**
     * Decide whether a customer may place an order.
     *
     * @return array{
     *     allowed: bool,
     *     required: bool,
     *     message: string|null,
     *     verification: KycVerification|null,
     *     purchase_total: float,
     *     pending_amount: float,
     *     threshold: float
     * }
     */
    public function evaluateCustomerCheckout(mixed $user, float $pendingAmount = 0.0): array
    {
        $threshold = $this->purchaseThreshold();
        $customerId = $this->resolveCustomerId($user);
        $purchaseTotal = $customerId === null ? 0.0 : $this->customerPurchaseTotal($customerId);

        $required = $this->requiresCustomerVerification($user, $pendingAmount);

        if (! $required || $customerId === null) {
            return [
                'allowed' => true,
                'required' => false,
                'message' => null,
                'verification' => null,
                'purchase_total' => $purchaseTotal,
                'pending_amount' => $pendingAmount,
                'threshold' => $threshold,
            ];
        }

        $verification = $this->ensureVerification(KycUserType::CUSTOMER, $customerId, required: true);

        return [
            'allowed' => false,
            'required' => true,
            'message' => $this->customerBlockedMessage($verification),
            'verification' => $verification,
            'purchase_total' => $purchaseTotal,
            'pending_amount' => $pendingAmount,
            'threshold' => $threshold,
        ];
    }

    public function customerBlockedMessage(KycVerification $verification): string
    {
        return match ($verification->status) {
            KycStatus::APPROVED => translate('your_account_is_verified'),
            KycStatus::PENDING, KycStatus::ON_HOLD => translate('your_kyc_verification_is_under_review'),
            KycStatus::REJECTED, KycStatus::RESET => translate('your_kyc_verification_was_rejected_please_submit_again'),
            default => translate('you_need_to_verify_your_account_before_placing_more_orders'),
        };
    }

    /**
     * Vendors are gated on every product listing and withdrawal action.
     *
     * @return array{allowed: bool, message: string|null, verification: KycVerification|null}
     */
    public function evaluateVendorAccess(int $vendorId): array
    {
        if (! $this->isEnabled()) {
            return ['allowed' => true, 'message' => null, 'verification' => null];
        }

        $verification = $this->findVerification(KycUserType::VENDOR, $vendorId);

        if ($verification?->isApproved()) {
            return ['allowed' => true, 'message' => null, 'verification' => $verification];
        }

        return [
            'allowed' => false,
            'message' => $verification
                ? $this->vendorBlockedMessage($verification)
                : translate('please_complete_your_kyc_verification_to_continue'),
            'verification' => $verification,
        ];
    }

    public function vendorBlockedMessage(KycVerification $verification): string
    {
        return match ($verification->status) {
            KycStatus::PENDING, KycStatus::ON_HOLD => translate('your_kyc_verification_is_under_review'),
            KycStatus::REJECTED, KycStatus::RESET => translate('your_kyc_verification_was_rejected_please_submit_again'),
            default => translate('please_complete_your_kyc_verification_to_continue'),
        };
    }

    /**
     * Refresh the stored status from Sumsub. Safe to call on every poll.
     */
    public function syncFromSumsub(KycVerification $verification): KycVerification
    {
        if (! $this->sumsub->isConfigured()) {
            return $verification;
        }

        try {
            $applicant = $this->sumsub->getApplicantByExternalUserId($verification->external_user_id);
        } catch (Throwable $exception) {
            Log::warning('Unable to sync KYC status from Sumsub', [
                'external_user_id' => $verification->external_user_id,
                'message' => $exception->getMessage(),
            ]);

            return $verification;
        }

        if ($applicant === null) {
            return $verification;
        }

        return $this->applyApplicantPayload($verification, $applicant);
    }

    /**
     * Ask for a Sumsub refresh without blocking the current request.
     *
     * Every surface (web storefront, customer app, vendor app and the admin
     * panel) polls this, so the API call is handed to SyncKycVerificationJob.
     * The local mirror is returned untouched and the caller - and its own
     * polling loop - picks the new state up once the job has run.
     *
     * Callers decide when a refresh is worth asking for; the polling surfaces
     * skip verifications that are already approved, while the admin Sync
     * action forces one.
     */
    public function queueSyncFromSumsub(KycVerification $verification): KycVerification
    {
        if (! $this->sumsub->isConfigured()) {
            return $verification;
        }

        SyncKycVerificationJob::dispatch($verification->id);

        return $verification;
    }

    /**
     * Apply an applicant payload (from polling or from a webhook).
     *
     * @param  array<string, mixed>  $payload
     */
    public function applyApplicantPayload(KycVerification $verification, array $payload): KycVerification
    {
        $status = $this->sumsub->mapStatus($payload);

        $verification->applicant_id = $payload['applicantId'] ?? $payload['id'] ?? $verification->applicant_id;
        $verification->level_name = $payload['levelName'] ?? $verification->level_name;
        $verification->status = $status;
        $verification->review_answer = $payload['reviewResult']['reviewAnswer'] ?? null;
        $verification->reject_type = $payload['reviewResult']['reviewRejectType'] ?? null;
        $verification->reject_labels = $payload['reviewResult']['rejectLabels'] ?? null;
        $verification->moderation_comment = $payload['reviewResult']['moderationComment'] ?? null;
        $verification->last_synced_at = now();
        $verification->last_webhook_payload = $payload;

        if ($status === KycStatus::APPROVED && $verification->verified_at === null) {
            $verification->verified_at = now();
        }

        $verification->save();

        return $verification;
    }

    /**
     * Handle a verified webhook delivery from Sumsub.
     *
     * @param  array<string, mixed>  $payload
     */
    public function handleWebhookPayload(array $payload): ?KycVerification
    {
        $externalUserId = $payload['externalUserId'] ?? null;

        if (empty($externalUserId)) {
            return null;
        }

        $verification = KycVerification::query()
            ->where('external_user_id', $externalUserId)
            ->first();

        if (! $verification) {
            Log::warning('Received Sumsub webhook for an unknown applicant', [
                'external_user_id' => $externalUserId,
                'type' => $payload['type'] ?? null,
            ]);

            return null;
        }

        return $this->applyApplicantPayload($verification, $payload);
    }
}
