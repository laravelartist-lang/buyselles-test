<?php

namespace App\Enums;

enum KycStatus
{
    /** No applicant exists yet - KYC has not been started. */
    const NOT_STARTED = 'not_started';

    /** An applicant exists and the user is part way through the flow. */
    const PENDING = 'pending';

    /** Sumsub placed the applicant on hold and requires manual review. */
    const ON_HOLD = 'on_hold';

    /** Verification passed. */
    const APPROVED = 'approved';

    /** Verification was rejected and the user must start again. */
    const REJECTED = 'rejected';

    /** The applicant was reset and the user must re-submit documents. */
    const RESET = 'reset';

    /**
     * @return array<int, string>
     */
    public static function all(): array
    {
        return [
            self::NOT_STARTED,
            self::PENDING,
            self::ON_HOLD,
            self::APPROVED,
            self::REJECTED,
            self::RESET,
        ];
    }

    /**
     * Statuses that allow the account to keep transacting.
     *
     * @return array<int, string>
     */
    public static function verifiedStatuses(): array
    {
        return [self::APPROVED];
    }

    /**
     * Statuses where the applicant is still being processed.
     *
     * @return array<int, string>
     */
    public static function inProgressStatuses(): array
    {
        return [self::PENDING, self::ON_HOLD];
    }
}
