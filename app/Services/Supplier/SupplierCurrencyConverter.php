<?php

namespace App\Services\Supplier;

use App\Utils\Convert;

class SupplierCurrencyConverter
{
    /**
     * Convert an amount from the given currency into USD using app exchange rates.
     */
    public function toUsd(float $amount, string $fromCurrency): float
    {
        $fromCurrency = strtoupper(trim($fromCurrency));

        if ($fromCurrency === 'USD' || $amount == 0.0) {
            return $this->round($amount);
        }

        return $this->round((float) Convert::usdPaymentModule($amount, $fromCurrency));
    }

    /**
     * Convert a USD amount into another currency using app exchange rates.
     */
    public function fromUsd(float $amount, string $toCurrency): float
    {
        $toCurrency = strtoupper(trim($toCurrency));

        if ($toCurrency === 'USD' || $amount == 0.0) {
            return $this->round($amount);
        }

        return $this->round((float) usdToAnotherCurrencyConverter($toCurrency, $amount));
    }

    /**
     * Convert between any two supported currencies.
     */
    public function convertBetween(float $amount, string $fromCurrency, string $toCurrency): float
    {
        $fromCurrency = strtoupper(trim($fromCurrency));
        $toCurrency = strtoupper(trim($toCurrency));

        if ($fromCurrency === $toCurrency) {
            return $this->round($amount);
        }

        $usdAmount = $this->toUsd($amount, $fromCurrency);

        return $this->fromUsd($usdAmount, $toCurrency);
    }

    /**
     * Normalize a supplier stock/API price into mapping storage format (USD for admin).
     *
     * @param  array<string, mixed>|null  $supplierSettings
     * @return array{cost_price: float, cost_currency: string}
     */
    public function resolveMappingCost(float $rawPrice, string $stockCurrency, ?array $supplierSettings = null): array
    {
        $sourceCurrency = strtoupper(trim((string) ($supplierSettings['source_currency'] ?? $stockCurrency)));

        if ($sourceCurrency !== '' && $sourceCurrency !== 'USD') {
            return [
                'cost_price' => $this->toUsd($rawPrice, $sourceCurrency),
                'cost_currency' => 'USD',
            ];
        }

        return [
            'cost_price' => $this->round($rawPrice),
            'cost_currency' => strtoupper(trim($stockCurrency)) ?: 'USD',
        ];
    }

    /**
     * Normalize a supplier API price to USD when a source currency is configured.
     *
     * @return array{price: float, currency: string}
     */
    public function convertPrice(float $amount, ?string $sourceCurrency): array
    {
        $sourceCurrency = $sourceCurrency ? strtoupper(trim($sourceCurrency)) : null;

        if ($sourceCurrency === null || $sourceCurrency === '' || $sourceCurrency === 'USD') {
            return [
                'price' => $this->round($amount),
                'currency' => 'USD',
            ];
        }

        return [
            'price' => $this->toUsd($amount, $sourceCurrency),
            'currency' => 'USD',
        ];
    }

    private function round(float $amount): float
    {
        $decimalPointSettings = getWebConfig('decimal_point_settings') ?? 2;

        return round($amount, (int) $decimalPointSettings);
    }
}
