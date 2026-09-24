<?php

namespace Tests\Unit;

use App\Services\Apple\AppleIapPricePointResolver;
use PHPUnit\Framework\TestCase;

class AppleIapPricePointResolverTest extends TestCase
{
    public function test_it_selects_the_smallest_tier_at_or_above_target_price(): void
    {
        $resolver = new AppleIapPricePointResolver;

        $selected = $resolver->resolveNearestTierAtOrAbove([
            ['id' => 'tier-1', 'customer_price' => 0.99, 'price_tier' => '1'],
            ['id' => 'tier-2', 'customer_price' => 4.99, 'price_tier' => '2'],
            ['id' => 'tier-3', 'customer_price' => 9.99, 'price_tier' => '3'],
        ], 4.99);

        $this->assertSame('tier-2', $selected['id']);
        $this->assertSame(4.99, $selected['customer_price']);
    }

    public function test_it_rounds_up_to_next_tier_when_target_is_between_prices(): void
    {
        $resolver = new AppleIapPricePointResolver;

        $selected = $resolver->resolveNearestTierAtOrAbove([
            ['id' => 'tier-2', 'customer_price' => 4.99, 'price_tier' => '2'],
            ['id' => 'tier-3', 'customer_price' => 9.99, 'price_tier' => '3'],
        ], 5.0);

        $this->assertSame('tier-3', $selected['id']);
    }

    public function test_it_falls_back_to_highest_tier_when_target_exceeds_all_points(): void
    {
        $resolver = new AppleIapPricePointResolver;

        $selected = $resolver->resolveNearestTierAtOrAbove([
            ['id' => 'tier-1', 'customer_price' => 0.99, 'price_tier' => '1'],
            ['id' => 'tier-2', 'customer_price' => 4.99, 'price_tier' => '2'],
        ], 500.0);

        $this->assertSame('tier-2', $selected['id']);
    }
}
