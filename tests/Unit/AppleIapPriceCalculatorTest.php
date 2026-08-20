<?php

namespace Tests\Unit;

use App\Services\Apple\AppleIapPriceCalculator;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class AppleIapPriceCalculatorTest extends TestCase
{
    public function test_it_rejects_using_percent_and_fixed_usd_together(): void
    {
        $calculator = new AppleIapPriceCalculator;

        $this->expectException(InvalidArgumentException::class);

        $calculator->applyMarkup(5.0, 15.0, 1.0);
    }

    public function test_it_applies_percent_markup(): void
    {
        $calculator = new AppleIapPriceCalculator;

        $this->assertSame(5.75, $calculator->applyMarkup(5.0, 15.0, null));
    }

    public function test_it_applies_fixed_usd_markup(): void
    {
        $calculator = new AppleIapPriceCalculator;

        $this->assertSame(7.0, $calculator->applyMarkup(5.0, null, 2.0));
    }

    public function test_it_keeps_price_when_no_markup_is_provided(): void
    {
        $calculator = new AppleIapPriceCalculator;

        $this->assertSame(5.0, $calculator->applyMarkup(5.0, null, null));
    }
}
