<?php

namespace App\Services\DirectTopUp;

use App\Models\Product;
use App\Models\SupplierProductMapping;
use InvalidArgumentException;

class DirectTopUpService
{
    public function isDirectTopUpProduct(Product $product): bool
    {
        return $product->product_type === 'digital'
            && (bool) $product->is_direct_topup;
    }

    public function hasActiveSupplierMapping(Product $product): bool
    {
        return SupplierProductMapping::query()
            ->where('product_id', $product->id)
            ->where('is_active', true)
            ->exists();
    }

    public function canAddToCart(Product $product): bool
    {
        if (! $this->isDirectTopUpProduct($product)) {
            return false;
        }

        if (! $this->hasActiveSupplierMapping($product)) {
            return false;
        }

        try {
            $this->validateConfiguration($product);

            return true;
        } catch (InvalidArgumentException) {
            return false;
        }
    }

    /**
     * @throws InvalidArgumentException
     */
    public function validateConfiguration(Product $product): void
    {
        if (! $this->isDirectTopUpProduct($product)) {
            return;
        }

        if (empty(trim((string) $product->direct_topup_account_label))) {
            throw new InvalidArgumentException(translate('direct_topup_account_label_is_required'));
        }

        $min = (float) $product->direct_topup_min_quantity;
        $max = (float) $product->direct_topup_max_quantity;

        if ($min <= 0 || $max <= 0) {
            throw new InvalidArgumentException(translate('direct_topup_configuration_invalid'));
        }

        if ($min > $max) {
            throw new InvalidArgumentException(translate('direct_topup_min_must_be_less_than_max'));
        }
    }

    /**
     * @return array<string, string>
     */
    public function validatePurchase(Product $product, string $accountId, float $quantity): array
    {
        $errors = [];

        if (! $this->isDirectTopUpProduct($product)) {
            $errors['product'] = translate('product_is_not_direct_topup');

            return $errors;
        }

        if (! $this->hasActiveSupplierMapping($product)) {
            $errors['supplier'] = translate('direct_topup_requires_supplier_mapping');
        }

        try {
            $this->validateConfiguration($product);
        } catch (InvalidArgumentException $e) {
            $errors['configuration'] = $e->getMessage();
        }

        $accountId = trim($accountId);

        if ($accountId === '') {
            $errors['direct_topup_account_id'] = translate('direct_topup_account_id_is_required');
        } elseif (strlen($accountId) > 255) {
            $errors['direct_topup_account_id'] = translate('direct_topup_account_id_too_long');
        } elseif (! preg_match('/^[a-zA-Z0-9_\-\.@]+$/u', $accountId)) {
            $errors['direct_topup_account_id'] = translate('direct_topup_account_id_invalid_format');
        }

        $min = (float) $product->direct_topup_min_quantity;
        $max = (float) $product->direct_topup_max_quantity;

        if ($quantity <= 0) {
            $errors['direct_topup_quantity'] = translate('direct_topup_quantity_must_be_positive');
        } elseif ($quantity < $min || $quantity > $max) {
            $errors['direct_topup_quantity'] = translate('direct_topup_quantity_out_of_range').' '.$min.' - '.$max;
        }

        return $errors;
    }

    /**
     * Get the per-unit sell price derived from the active supplier mapping.
     * Falls back to direct_topup_price_per_unit if no mapping exists.
     */
    public function getPricePerUnit(Product $product): float
    {
        $mapping = SupplierProductMapping::query()
            ->where('product_id', $product->id)
            ->where('is_active', true)
            ->whereHas('supplierApi', fn ($q) => $q->where('is_active', true))
            ->first();

        if ($mapping) {
            return $mapping->calculateSellPrice();
        }

        return (float) $product->direct_topup_price_per_unit;
    }

    public function calculateTotalPrice(Product $product, float $quantity): float
    {
        $this->validateConfiguration($product);

        return round($quantity * $this->getPricePerUnit($product), 2);
    }

    public function calculateQuantityFromPrice(Product $product, float $price): float
    {
        $this->validateConfiguration($product);

        $pricePerUnit = $this->getPricePerUnit($product);
        $min = (float) $product->direct_topup_min_quantity;
        $max = (float) $product->direct_topup_max_quantity;

        if ($pricePerUnit <= 0) {
            return $min;
        }

        $quantity = floor($price / $pricePerUnit);

        if ($quantity < $min) {
            return $min;
        }

        if ($quantity > $max) {
            return $max;
        }

        return (float) $quantity;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function buildApiPayload(Product $product): ?array
    {
        if (! $this->isDirectTopUpProduct($product)) {
            return null;
        }

        try {
            $this->validateConfiguration($product);
        } catch (InvalidArgumentException) {
            return null;
        }

        return [
            'enabled' => true,
            'account_label' => (string) $product->direct_topup_account_label,
            'min_quantity' => (float) $product->direct_topup_min_quantity,
            'max_quantity' => (float) $product->direct_topup_max_quantity,
            'price_per_unit' => $this->getPricePerUnit($product),
            'currency' => getWebConfig(name: 'currency_code') ?? 'USD',
        ];
    }

    public function sanitizeAccountIdForLogging(string $accountId): string
    {
        $accountId = trim($accountId);

        if (strlen($accountId) <= 4) {
            return '****';
        }

        return substr($accountId, 0, 2).str_repeat('*', max(0, strlen($accountId) - 4)).substr($accountId, -2);
    }
}
