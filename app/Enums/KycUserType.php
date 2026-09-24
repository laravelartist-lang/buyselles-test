<?php

namespace App\Enums;

enum KycUserType
{
    const CUSTOMER = 'customer';

    const VENDOR = 'vendor';

    /**
     * @return array<int, string>
     */
    public static function all(): array
    {
        return [self::CUSTOMER, self::VENDOR];
    }

    /**
     * Prefix used to build the Sumsub external userId so that a customer
     * and a vendor sharing the same primary key never collide.
     */
    public static function externalId(string $userType, int $userId): string
    {
        return $userType.'_'.$userId;
    }
}
