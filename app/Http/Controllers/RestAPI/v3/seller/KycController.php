<?php

namespace App\Http\Controllers\RestAPI\v3\seller;

use App\Enums\KycUserType;
use App\Http\Controllers\Controller;
use App\Http\Resources\Kyc\KycStatusResource;
use App\Models\Seller;
use App\Services\Kyc\KycService;
use App\Services\Kyc\SumsubService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Throwable;

/**
 * Vendor app KYC endpoints.
 *
 * The app receives an access token here and hands it to the Sumsub mobile
 * SDK - it never talks to Sumsub directly.
 */
class KycController extends Controller
{
    public function __construct(
        private readonly KycService $kycService,
        private readonly SumsubService $sumsub,
    ) {}

    public function status(Request $request): JsonResponse
    {
        $vendor = $this->vendor($request);

        if (! $vendor) {
            return response()->json(['message' => translate('login_first')], 401);
        }

        $verification = $this->kycService->ensureVerification(KycUserType::VENDOR, $vendor->id);

        if (! $verification->isApproved()) {
            $verification = $this->kycService->queueSyncFromSumsub($verification);
        }

        return response()->json([
            'kyc' => (new KycStatusResource($verification))->resolve(),
            'seller_status' => $vendor->status,
        ]);
    }

    public function token(Request $request): JsonResponse
    {
        $vendor = $this->vendor($request);

        if (! $vendor) {
            return response()->json(['message' => translate('login_first')], 401);
        }

        if (! $this->kycService->isEnabled()) {
            return response()->json(['message' => translate('kyc_verification_is_currently_unavailable')], 403);
        }

        $verification = $this->kycService->ensureVerification(KycUserType::VENDOR, $vendor->id, required: true);

        if ($verification->isApproved()) {
            return response()->json([
                'already_verified' => true,
                'kyc' => (new KycStatusResource($verification))->resolve(),
            ]);
        }

        try {
            $accessToken = $this->sumsub->generateAccessToken(
                userType: KycUserType::VENDOR,
                userId: $vendor->id,
                applicantIdentifiers: array_filter([
                    'email' => $vendor->email,
                    'phone' => $vendor->phone,
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
     * Signed URL the vendor app opens in a WebView to run the Sumsub WebSDK.
     */
    public function launchUrl(Request $request): JsonResponse
    {
        $vendor = $this->vendor($request);

        if (! $vendor) {
            return response()->json(['message' => translate('login_first')], 401);
        }

        if (! $this->kycService->isEnabled()) {
            return response()->json(['message' => translate('kyc_verification_is_currently_unavailable')], 403);
        }

        $verification = $this->kycService->ensureVerification(KycUserType::VENDOR, $vendor->id, required: true);

        return response()->json([
            'launch_url' => URL::temporarySignedRoute('kyc.launch', now()->addMinutes(30), [
                'userType' => KycUserType::VENDOR,
                'userId' => $vendor->id,
            ]),
            'expires_in' => 1800,
            'kyc' => (new KycStatusResource($verification))->resolve(),
            'seller_status' => $vendor->status,
        ]);
    }

    private function vendor(Request $request): ?Seller
    {
        $vendor = $request['seller'] ?? null;

        return $vendor instanceof Seller ? $vendor : null;
    }
}
