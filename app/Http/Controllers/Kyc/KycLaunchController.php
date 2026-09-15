<?php

namespace App\Http\Controllers\Kyc;

use App\Enums\KycUserType;
use App\Http\Controllers\Controller;
use App\Models\Seller;
use App\Models\User;
use App\Services\Kyc\KycService;
use App\Services\Kyc\SumsubService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;
use Throwable;

/**
 * Renders the Sumsub WebSDK for the mobile apps.
 *
 * The apps never receive Sumsub credentials. They ask the API for a short
 * lived, signed launch URL and open it inside a WebView, so the camera based
 * liveness step keeps working without shipping any secret in the client.
 */
class KycLaunchController extends Controller
{
    public function __construct(
        private readonly KycService $kycService,
        private readonly SumsubService $sumsub,
    ) {}

    public function __invoke(string $userType, int $userId): View|JsonResponse
    {
        if (! in_array($userType, KycUserType::all(), true)) {
            abort(404);
        }

        if (! $this->kycService->isEnabled()) {
            return view('kyc.launch-status', [
                'status' => 'unavailable',
                'message' => translate('kyc_verification_is_currently_unavailable'),
            ]);
        }

        $verification = $this->kycService->ensureVerification($userType, $userId, required: true);

        if ($verification->isApproved()) {
            return view('kyc.launch-status', [
                'status' => 'approved',
                'message' => translate('your_account_is_verified'),
            ]);
        }

        try {
            $accessToken = $this->appliableIdentifiers($userType, $userId);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json(['message' => translate('unable_to_start_kyc_verification_please_try_again')], 500);
        }

        return view('kyc.launch', [
            'applicant' => $accessToken,
            'userType' => $userType,
            'userId' => $userId,
            'refreshUrl' => URL::temporarySignedRoute(
                'kyc.launch.token',
                now()->addMinutes(120),
                ['userType' => $userType, 'userId' => $userId],
            ),
        ]);
    }

    /**
     * Hand the WebSDK a fresh access token when the short lived one expires.
     */
    public function token(string $userType, int $userId): JsonResponse
    {
        if (! in_array($userType, KycUserType::all(), true)) {
            return response()->json(['message' => 'Not found'], 404);
        }

        if (! $this->kycService->isEnabled()) {
            return response()->json(['message' => translate('kyc_verification_is_currently_unavailable')], 403);
        }

        try {
            $accessToken = $this->appliableIdentifiers($userType, $userId);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json(['message' => translate('unable_to_start_kyc_verification_please_try_again')], 500);
        }

        return response()->json(['token' => $accessToken['token'] ?? null]);
    }

    /**
     * @return array<string, mixed>
     */
    private function appliableIdentifiers(string $userType, int $userId): array
    {
        $model = $userType === KycUserType::VENDOR
            ? Seller::query()->find($userId)
            : User::query()->find($userId);

        return $this->sumsub->generateAccessToken(
            userType: $userType,
            userId: $userId,
            applicantIdentifiers: array_filter([
                'email' => $model?->email,
                'phone' => $model?->phone,
            ]),
        );
    }
}
