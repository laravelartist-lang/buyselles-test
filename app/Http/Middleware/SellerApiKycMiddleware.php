<?php

namespace App\Http\Middleware;

use App\Enums\KycUserType;
use App\Http\Resources\Kyc\KycStatusResource;
use App\Models\Seller;
use App\Services\Kyc\KycService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Blocks every vendor app endpoint until KYC is approved, except the KYC
 * endpoints themselves.
 */
class SellerApiKycMiddleware
{
    public function __construct(
        private readonly KycService $kycService,
    ) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response|\Illuminate\Http\RedirectResponse)  $next
     */
    public function handle(Request $request, Closure $next): mixed
    {
        $seller = $request['seller'] ?? null;

        if (! $seller instanceof Seller || ! $this->kycService->isEnabled()) {
            return $next($request);
        }

        if ($this->kycService->isVerified(KycUserType::VENDOR, $seller->id)) {
            return $next($request);
        }

        if ($request->is('api/v3/seller/kyc', 'api/v3/seller/kyc/*')) {
            return $next($request);
        }

        $verification = $this->kycService->ensureVerification(KycUserType::VENDOR, $seller->id, required: true);

        return response()->json([
            'message' => $this->kycService->vendorBlockedMessage($verification),
            'kyc_required' => true,
            'kyc' => (new KycStatusResource($verification))->resolve(),
        ], 403);
    }
}
