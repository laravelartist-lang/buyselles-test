<?php

namespace App\Services\Kyc;

use App\Models\KycVerification;
use Illuminate\Http\Request;

/**
 * Reusable customer checkout gate.
 *
 * Called from the web checkout controllers and from every mobile checkout
 * endpoint so a customer cannot place an order through any channel once the
 * purchase threshold is reached.
 */
class CustomerCheckoutKycGuard
{
    public function __construct(
        private readonly KycService $kycService,
    ) {}

    /**
     * @return array{message: string, verification: KycVerification|null}|null Null when checkout may proceed.
     */
    public function blockReason(mixed $user, Request $request): ?array
    {
        // Guest checkouts resolve to the string 'offline', and the KYC service
        // accepts either of the two user models the guards can hand back.
        if (! $this->kycService->isEnabled() || $this->kycService->resolveCustomerId($user) === null) {
            return null;
        }

        $pendingAmount = $this->kycService->resolvePendingOrderAmount($request);
        $evaluation = $this->kycService->evaluateCustomerCheckout($user, $pendingAmount);

        if ($evaluation['allowed']) {
            return null;
        }

        return [
            'message' => $evaluation['message'],
            'verification' => $evaluation['verification'],
        ];
    }
}
