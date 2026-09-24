<?php

namespace App\Http\Controllers\Vendor;

use App\Enums\KycUserType;
use App\Http\Controllers\Controller;
use App\Http\Resources\Kyc\KycStatusResource;
use App\Models\Seller;
use App\Services\Kyc\KycService;
use App\Services\Kyc\SumsubService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * Vendor KYC. Vendors must be verified before they can list products or
 * request a withdrawal.
 */
class KycController extends Controller
{
    public function __construct(
        private readonly KycService $kycService,
        private readonly SumsubService $sumsub,
    ) {}

    public function index(Request $request): View|RedirectResponse
    {
        $vendor = $this->authenticatedVendor($request);

        if (! $vendor) {
            return redirect()->route('vendor.auth.login');
        }

        $verification = $this->kycService->ensureVerification(KycUserType::VENDOR, $vendor->id);

        if (! $verification->isApproved()) {
            $verification = $this->kycService->queueSyncFromSumsub($verification);
        }

        return view('vendor-views.kyc.index', [
            'vendor' => $vendor,
            'verification' => $verification,
            'kycStatus' => (new KycStatusResource($verification))->resolve(),
            'sumsubConfigured' => $this->kycService->isEnabled(),
        ]);
    }

    public function token(Request $request): JsonResponse
    {
        $vendor = $this->authenticatedVendor($request);

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

    public function status(Request $request): JsonResponse
    {
        $vendor = $this->authenticatedVendor($request);

        if (! $vendor) {
            return response()->json(['message' => translate('login_first')], 401);
        }

        $verification = $this->kycService->ensureVerification(KycUserType::VENDOR, $vendor->id);

        if (! $verification->isApproved()) {
            $verification = $this->kycService->queueSyncFromSumsub($verification);
        }

        return response()->json([
            'kyc' => (new KycStatusResource($verification))->resolve(),
        ]);
    }

    /**
     * Resolve the authenticated vendor from either the web guard or the
     * signed-in session used right after registration.
     */
    private function authenticatedVendor(Request $request): ?Seller
    {
        $vendor = auth('seller')->user();

        if ($vendor instanceof Seller) {
            return $vendor;
        }

        $vendorId = $request->session()->get('kyc_vendor_id');

        return $vendorId ? Seller::find($vendorId) : null;
    }
}
