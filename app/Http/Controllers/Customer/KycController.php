<?php

namespace App\Http\Controllers\Customer;

use App\Enums\KycUserType;
use App\Http\Controllers\Controller;
use App\Http\Resources\Kyc\KycStatusResource;
use App\Services\Kyc\KycService;
use App\Services\Kyc\SumsubService;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * Customer KYC pages. The same service backs the mobile app endpoints, so a
 * customer verified here is instantly unblocked inside the app.
 */
class KycController extends Controller
{
    public function __construct(
        private readonly KycService $kycService,
        private readonly SumsubService $sumsub,
    ) {}

    public function index(Request $request): View|RedirectResponse
    {
        $user = auth('customer')->user();

        if (! $user) {
            Toastr::info(translate('login_first_for_next_steps'));

            return redirect()->route('customer.auth.login');
        }

        if (! $this->kycService->isEnabled()) {
            Toastr::warning(translate('kyc_verification_is_currently_unavailable'));

            return redirect('/');
        }

        $verification = $this->kycService->evaluateCustomerStatus($user);

        if (! $verification->isApproved()) {
            $verification = $this->kycService->queueSyncFromSumsub($verification);
        }

        return view(VIEW_FILE_NAMES['user_kyc'], [
            'verification' => $verification,
            'kycStatus' => (new KycStatusResource($verification))->resolve(),
            'threshold' => $this->kycService->purchaseThreshold(),
            'purchaseTotal' => $this->kycService->customerPurchaseTotal($user->id),
        ]);
    }

    /**
     * Mint an SDK access token for the current customer.
     */
    public function token(Request $request): JsonResponse
    {
        $user = auth('customer')->user();

        if (! $user) {
            return response()->json(['message' => translate('login_first')], 401);
        }

        if (! $this->kycService->isEnabled()) {
            return response()->json(['message' => translate('kyc_verification_is_currently_unavailable')], 403);
        }

        $verification = $this->kycService->ensureVerification(KycUserType::CUSTOMER, $user->id, required: true);

        if ($verification->isApproved()) {
            return response()->json([
                'already_verified' => true,
                'kyc' => (new KycStatusResource($verification))->resolve(),
            ]);
        }

        try {
            $accessToken = $this->sumsub->generateAccessToken(
                userType: KycUserType::CUSTOMER,
                userId: $user->id,
                applicantIdentifiers: array_filter([
                    'email' => $user->email,
                    'phone' => $user->phone,
                ]),
            );
        } catch (Throwable $exception) {
            report($exception);

            return response()->json(['message' => translate('unable_to_start_kyc_verification_please_try_again')], 500);
        }

        return response()->json([
            'token' => $accessToken['token'] ?? null,
            'user_id' => $accessToken['userId'] ?? $verification->external_user_id,
            'level_name' => $verification->level_name,
            'kyc' => (new KycStatusResource($verification))->resolve(),
        ]);
    }

    /**
     * Poll the current verification state after the SDK closes.
     */
    public function status(Request $request): JsonResponse
    {
        $user = auth('customer')->user();

        if (! $user) {
            return response()->json(['message' => translate('login_first')], 401);
        }

        $verification = $this->kycService->evaluateCustomerStatus($user);

        if (! $verification->isApproved()) {
            $verification = $this->kycService->queueSyncFromSumsub($verification);
        }

        return response()->json([
            'kyc' => (new KycStatusResource($verification))->resolve(),
            'purchase_total' => $this->kycService->customerPurchaseTotal($user->id),
        ]);
    }
}
