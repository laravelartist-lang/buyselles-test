<?php

namespace App\Services\DirectTopUp;

use App\Models\Product;
use App\Models\SupplierProductMapping;
use InvalidArgumentException;

class DirectTopUpService
{
    public function isDirectTopUpProduct(Product $product): bool
    {
        if ($product->product_type !== 'digital') {
            return false;
        }

        $mapping = $this->getMapping($product);

        return $mapping !== null
            && (bool) $mapping->is_direct_topup
            && (bool) ($mapping->supplierApi?->supports_direct_top_up ?? false);
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

        $mapping = $this->getMapping($product);

        if ($mapping === null) {
            return;
        }

        if (empty(trim((string) $mapping->direct_topup_account_label))) {
            throw new InvalidArgumentException(translate('direct_topup_account_label_is_required'));
        }

        $min = (float) $mapping->direct_topup_min_quantity;
        $max = (float) $mapping->direct_topup_max_quantity;

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

        $mapping = $this->getMapping($product);
        $min = $mapping ? (float) $mapping->direct_topup_min_quantity : 0;
        $max = $mapping ? (float) $mapping->direct_topup_max_quantity : 0;

        if ($quantity <= 0) {
            $errors['direct_topup_quantity'] = translate('direct_topup_quantity_must_be_positive');
        } elseif ($quantity < $min || $quantity > $max) {
            $errors['direct_topup_quantity'] = translate('direct_topup_quantity_out_of_range').' '.$min.' - '.$max;
        }

        return $errors;
    }

    /**
     * Get the per-unit sell price using the centralized effective price logic.
     * Falls back to direct_topup_price_per_unit from the mapping.
     */
    public function getPricePerUnit(Product $product): float
    {
        $mapping = $this->getMapping($product);

        if ($mapping && (float) $mapping->direct_topup_price_per_unit > 0) {
            return (float) $mapping->direct_topup_price_per_unit;
        }

        return $product->getEffectiveSellPrice();
    }

    public function calculateTotalPrice(Product $product, float $quantity): float
    {
        $this->validateConfiguration($product);

        return round($quantity * $this->getPricePerUnit($product), 2);
    }

    public function calculateQuantityFromPrice(Product $product, float $price): float
    {
        $this->validateConfiguration($product);

        $mapping = $this->getMapping($product);
        $pricePerUnit = $this->getPricePerUnit($product);
        $min = $mapping ? (float) $mapping->direct_topup_min_quantity : 0;
        $max = $mapping ? (float) $mapping->direct_topup_max_quantity : 0;

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

        $mapping = $this->getMapping($product);

        if ($mapping === null) {
            return null;
        }

        return [
            'enabled' => true,
            'account_label' => (string) $mapping->direct_topup_account_label,
            'min_quantity' => (float) $mapping->direct_topup_min_quantity,
            'max_quantity' => (float) $mapping->direct_topup_max_quantity,
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

    private function getMapping(Product $product): ?SupplierProductMapping
    {
        if ($product->relationLoaded('supplierMapping')) {
            return $product->supplierMapping;
        }

        if ($product->relationLoaded('mapping')) {
            return $product->mapping;
        }

        return SupplierProductMapping::query()
            ->where('product_id', $product->id)
            ->where('is_active', true)
            ->with('supplierApi')
            ->first();
    }
}
