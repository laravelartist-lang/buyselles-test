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
 * Blocks vendor app write operations until KYC is approved.
 *
 * Unverified vendors may browse dashboard data (GET + read-only list POSTs)
 * so the app can render normally; mutating routes stay closed until approval.
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

        if ($this->allowsDashboardPreview($request)) {
            return $next($request);
        }

        $verification = $this->kycService->ensureVerification(KycUserType::VENDOR, $seller->id, required: true);

        return response()->json([
            'message' => $this->kycService->vendorBlockedMessage($verification),
            'kyc_required' => true,
            'kyc' => (new KycStatusResource($verification))->resolve(),
        ], 403);
    }

    /**
     * Allow read-only vendor app traffic needed to render the dashboard before KYC.
     */
    private function allowsDashboardPreview(Request $request): bool
    {
        if ($request->isMethod('GET') && ! $this->isBlockedPreviewGetRoute($request)) {
            return true;
        }

        if ($request->isMethod('POST') && $this->isReadOnlyPreviewPostRoute($request)) {
            return true;
        }

        return false;
    }

    /**
     * GET routes that perform destructive or sensitive actions despite using GET.
     *
     * @return array<int, string>
     */
    private function blockedPreviewGetPatterns(): array
    {
        return [
            'api/v3/seller/account-delete',
            'api/v3/seller/products/delete-image',
            'api/v3/seller/products/delete-preview-file',
            'api/v3/seller/products/restock-request-delete',
            'api/v3/seller/delivery-man/delete/*',
            'api/v3/seller/payment-information/delete',
            'api/v3/seller/products/digital-codes/*/decrypt',
        ];
    }

    private function isBlockedPreviewGetRoute(Request $request): bool
    {
        return $request->is(...$this->blockedPreviewGetPatterns());
    }

    /**
     * POST routes that only fetch paginated data (no mutations).
     *
     * @return array<int, string>
     */
    private function readOnlyPreviewPostPatterns(): array
    {
        return [
            'api/v3/seller/orders/list',
            'api/v3/seller/products/restock-request-list',
        ];
    }

    private function isReadOnlyPreviewPostRoute(Request $request): bool
    {
        return $request->is(...$this->readOnlyPreviewPostPatterns());
    }
}
