<?php

namespace App\Services\Kyc;

use App\Models\KycVerification;
use App\Models\User;
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
        if (! $this->kycService->isEnabled() || ! $user instanceof User) {
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
