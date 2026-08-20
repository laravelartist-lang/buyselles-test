<?php

namespace App\Services\Apple;

use RuntimeException;

class AppleIapPricePointResolver
{
    /**
     * @param  array<int, array{id: string, customer_price: float, price_tier: string|null}>  $pricePoints
     * @return array{id: string, customer_price: float, price_tier: string|null}
     */
    public function resolveNearestTierAtOrAbove(array $pricePoints, float $targetUsd): array
    {
        if ($pricePoints === []) {
            throw new RuntimeException('No App Store price points were returned for this in-app purchase.');
        }

        usort(
            $pricePoints,
            fn (array $left, array $right): int => $left['customer_price'] <=> $right['customer_price'],
        );

        foreach ($pricePoints as $pricePoint) {
            if ($pricePoint['customer_price'] >= $targetUsd) {
                return $pricePoint;
            }
        }

        return $pricePoints[array_key_last($pricePoints)];
    }
}
