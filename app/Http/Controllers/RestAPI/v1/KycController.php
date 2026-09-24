<?php

namespace App\Http\Controllers\RestAPI\v1;

use App\Enums\KycUserType;
use App\Http\Controllers\Controller;
use App\Http\Resources\Kyc\KycStatusResource;
use App\Models\User;
use App\Services\Kyc\KycService;
use App\Services\Kyc\SumsubService;
use App\User as AuthenticatedUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Throwable;

/**
 * Customer app KYC endpoints.
 *
 * The same access token endpoint the web storefront uses, so verification
 * state is shared across every channel.
 */
class KycController extends Controller
{
    public function __construct(
        private readonly KycService $kycService,
        private readonly SumsubService $sumsub,
    ) {}

    public function status(Request $request): JsonResponse
    {
        $user = $this->customer($request);

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

    public function token(Request $request): JsonResponse
    {
        $user = $this->customer($request);

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
     * Signed URL the app opens in a WebView to run the Sumsub WebSDK.
     */
    public function launchUrl(Request $request): JsonResponse
    {
        $user = $this->customer($request);

        if (! $user) {
            return response()->json(['message' => translate('login_first')], 401);
        }

        if (! $this->kycService->isEnabled()) {
            return response()->json(['message' => translate('kyc_verification_is_currently_unavailable')], 403);
        }

        $verification = $this->kycService->ensureVerification(KycUserType::CUSTOMER, $user->id, required: true);

        return response()->json([
            'launch_url' => URL::temporarySignedRoute('kyc.launch', now()->addMinutes(30), [
                'userType' => KycUserType::CUSTOMER,
                'userId' => $user->id,
            ]),
            'expires_in' => 1800,
            'kyc' => (new KycStatusResource($verification))->resolve(),
        ]);
    }

    /**
     * The `api` guard is backed by the legacy `App\User` model, so both
     * user models have to be treated as a signed-in customer.
     */
    private function customer(Request $request): User|AuthenticatedUser|null
    {
        $user = $request->user() ?? $request['user'] ?? null;

        if ($user instanceof User || $user instanceof AuthenticatedUser) {
            return $user;
        }

        return null;
    }
}
