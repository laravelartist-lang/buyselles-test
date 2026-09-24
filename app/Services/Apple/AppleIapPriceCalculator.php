<?php

namespace App\Services\Apple;

use App\Models\Product;
use App\Utils\BackEndHelper;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use Throwable;

class AppleIapPriceCalculator
{
    private bool $currencyFallbackUsed = false;

    public function assertMarkupOptions(?float $percentMarkup, ?float $fixedUsdMarkup): void
    {
        if ($percentMarkup !== null && $fixedUsdMarkup !== null) {
            throw new InvalidArgumentException('Use either --percent or --fixed-usd, not both.');
        }

        if ($percentMarkup !== null && $percentMarkup < 0) {
            throw new InvalidArgumentException('The --percent value must be zero or greater.');
        }

        if ($fixedUsdMarkup !== null && $fixedUsdMarkup < 0) {
            throw new InvalidArgumentException('The --fixed-usd value must be zero or greater.');
        }
    }

    public function currencyFallbackWasUsed(): bool
    {
        return $this->currencyFallbackUsed;
    }

    public function resetCurrencyFallbackFlag(): void
    {
        $this->currencyFallbackUsed = false;
    }

    /**
     * @return array{
     *     buyselles_price_usd: float,
     *     target_usd: float,
     *     markup_mode: 'none'|'percent'|'fixed_usd',
     *     markup_value: float|null
     * }
     */
    public function calculateForProduct(Product $product, ?float $percentMarkup, ?float $fixedUsdMarkup): array
    {
        $this->assertMarkupOptions($percentMarkup, $fixedUsdMarkup);

        $buysellesPriceUsd = $this->resolveBuysellesPriceUsd($product);
        $targetUsd = $this->applyMarkup($buysellesPriceUsd, $percentMarkup, $fixedUsdMarkup);

        if ($percentMarkup !== null) {
            return [
                'buyselles_price_usd' => $buysellesPriceUsd,
                'target_usd' => $targetUsd,
                'markup_mode' => 'percent',
                'markup_value' => $percentMarkup,
            ];
        }

        if ($fixedUsdMarkup !== null) {
            return [
                'buyselles_price_usd' => $buysellesPriceUsd,
                'target_usd' => $targetUsd,
                'markup_mode' => 'fixed_usd',
                'markup_value' => $fixedUsdMarkup,
            ];
        }

        return [
            'buyselles_price_usd' => $buysellesPriceUsd,
            'target_usd' => $targetUsd,
            'markup_mode' => 'none',
            'markup_value' => null,
        ];
    }

    public function applyMarkup(float $buysellesPriceUsd, ?float $percentMarkup, ?float $fixedUsdMarkup): float
    {
        $this->assertMarkupOptions($percentMarkup, $fixedUsdMarkup);

        if ($fixedUsdMarkup !== null) {
            return round($buysellesPriceUsd + $fixedUsdMarkup, 2);
        }

        if ($percentMarkup !== null) {
            return round($buysellesPriceUsd * (1 + ($percentMarkup / 100)), 2);
        }

        return round($buysellesPriceUsd, 2);
    }

    public function canConvertCurrency(): bool
    {
        if (! Schema::hasTable('business_settings')) {
            return false;
        }

        if (! Schema::hasTable('currencies')) {
            return false;
        }

        return true;
    }

    public function resolveBuysellesPriceUsd(Product $product): float
    {
        $localPrice = $product->getLocalUnitPrice();

        if (! $this->canConvertCurrency()) {
            $this->currencyFallbackUsed = true;

            return round($localPrice, 2);
        }

        try {
            return round((float) BackEndHelper::currency_to_usd($localPrice), 2);
        } catch (QueryException|Throwable) {
            $this->currencyFallbackUsed = true;

            return round($localPrice, 2);
        }
    }
}
