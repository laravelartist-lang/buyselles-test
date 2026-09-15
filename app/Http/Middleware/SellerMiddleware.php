<?php

namespace App\Http\Middleware;

use App\Enums\KycUserType;
use App\Services\Kyc\KycService;
use Closure;
use Devrabiul\ToastMagic\Facades\ToastMagic;
use Illuminate\Http\Request;

class SellerMiddleware
{
    public function __construct(
        private readonly KycService $kycService,
    ) {}

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): mixed
    {
        if (! auth('seller')->check()) {
            return redirect()->route('vendor.auth.login');
        }

        $seller = auth('seller')->user();

        /*
         * A vendor whose identity is not yet verified may sign in, but the
         * only page they can reach is the KYC screen - this covers product
         * listing, withdrawals and every other dashboard route at once.
         */
        if ($this->kycService->isEnabled() && ! $this->kycService->isVerified(KycUserType::VENDOR, $seller->id)) {
            if ($request->routeIs('vendor.kyc.*')) {
                return $next($request);
            }

            ToastMagic::warning(translate('please_complete_your_kyc_verification_to_continue'));

            return redirect()->route('vendor.kyc.index');
        }

        if ($seller->status === 'approved') {
            return $next($request);
        }

        auth()->guard('seller')->logout();
        ToastMagic::error(translate('your_account_is_in_review_process'));

        return redirect()->route('vendor.auth.login');
    }
}
