<?php

namespace App\Services\Partner;

use App\Models\PartnerCatalogDenominationPrice;
use App\Models\PartnerCatalogItem;
use App\Models\SupplierProductDenomination;
use App\Models\SupplierProductMapping;
use InvalidArgumentException;

class PartnerCatalogPricingService
{
    /**
     * @return array{
     *     price_type: string,
     *     currency: string,
     *     unit_price: float,
     *     quantity: int,
     *     subtotal: float,
     *     denomination_id: int|null,
     *     custom_amount: float|null
     * }
     */
    public function quote(
        PartnerCatalogItem $catalogItem,
        int $quantity = 1,
        ?int $denominationId = null,
        ?float $customAmount = null,
    ): array {
        if (! $catalogItem->is_active || $quantity < 1) {
            throw new InvalidArgumentException('Catalog item is inactive or quantity is invalid.');
        }

        $catalogItem->loadMissing([
            'product.supplierMapping.supplierApi',
            'product.supplierMapping.activeDenominations',
            'activeDenominationPrices.denomination',
        ]);

        $mapping = $catalogItem->product?->supplierMapping;

        if ($mapping?->is_direct_topup) {
            if ($quantity !== 1 || $denominationId !== null || $customAmount !== null) {
                throw new InvalidArgumentException('Direct top-up products require one fixed bundle per order.');
            }

            return $this->buildQuote(
                catalogItem: $catalogItem,
                priceType: 'direct_topup_bundle',
                unitPrice: $this->requiredPartnerPrice($catalogItem),
                quantity: 1,
            );
        }

        if ($denominationId !== null) {
            return $this->quoteDenomination(
                catalogItem: $catalogItem,
                mapping: $mapping,
                denominationId: $denominationId,
                quantity: $quantity,
                customAmount: $customAmount,
            );
        }

        if ($mapping !== null && $mapping->activeDenominations->isNotEmpty()) {
            throw new InvalidArgumentException('A supplier denomination is required for this product.');
        }

        if ($customAmount !== null) {
            throw new InvalidArgumentException('Custom amount is not supported for this product.');
        }

        return $this->buildQuote(
            catalogItem: $catalogItem,
            priceType: 'fixed',
            unitPrice: $this->requiredPartnerPrice($catalogItem),
            quantity: $quantity,
        );
    }

    private function quoteDenomination(
        PartnerCatalogItem $catalogItem,
        ?SupplierProductMapping $mapping,
        int $denominationId,
        int $quantity,
        ?float $customAmount,
    ): array {
        if ($mapping === null) {
            throw new InvalidArgumentException('This product has no active supplier mapping.');
        }

        /** @var SupplierProductDenomination|null $denomination */
        $denomination = $mapping->activeDenominations->firstWhere('id', $denominationId);

        if ($denomination === null) {
            throw new InvalidArgumentException('The selected denomination is not available for this product.');
        }

        if ($denomination->isFixed()) {
            if ($customAmount !== null) {
                throw new InvalidArgumentException('Custom amount is not accepted for a fixed denomination.');
            }

            /** @var PartnerCatalogDenominationPrice|null $price */
            $price = $catalogItem->activeDenominationPrices
                ->firstWhere('supplier_product_denomination_id', $denomination->id);

            if ($price === null || (float) $price->partner_price <= 0) {
                throw new InvalidArgumentException('This denomination has no partner price.');
            }

            return $this->buildQuote(
                catalogItem: $catalogItem,
                priceType: 'fixed_denomination',
                unitPrice: (float) $price->partner_price,
                quantity: $quantity,
                denominationId: $denomination->id,
            );
        }

        if ($customAmount === null || $customAmount <= 0) {
            throw new InvalidArgumentException('A custom amount is required for this denomination.');
        }

        $minimum = (float) ($denomination->min_face_value ?? 0);
        $maximum = (float) ($denomination->max_face_value ?? 0);

        if (($minimum > 0 && $customAmount < $minimum) || ($maximum > 0 && $customAmount > $maximum)) {
            throw new InvalidArgumentException('The custom amount is outside the allowed range.');
        }

        if (! $catalogItem->hasVariablePriceFormula()) {
            throw new InvalidArgumentException('This product has no partner pricing formula.');
        }

        $formulaValue = (float) $catalogItem->variable_price_value;
        $unitPrice = $catalogItem->variable_price_type === PartnerCatalogItem::VARIABLE_PRICE_PERCENT
            ? $customAmount * (1 + ($formulaValue / 100))
            : $customAmount + $formulaValue;

        return $this->buildQuote(
            catalogItem: $catalogItem,
            priceType: 'variable_denomination',
            unitPrice: $unitPrice,
            quantity: $quantity,
            denominationId: $denomination->id,
            customAmount: $customAmount,
        );
    }

    private function requiredPartnerPrice(PartnerCatalogItem $catalogItem): float
    {
        $partnerPrice = (float) ($catalogItem->partner_price ?? 0);

        if ($partnerPrice <= 0) {
            throw new InvalidArgumentException('This product has no partner price.');
        }

        return $partnerPrice;
    }

    /**
     * @return array{
     *     price_type: string,
     *     currency: string,
     *     unit_price: float,
     *     quantity: int,
     *     subtotal: float,
     *     denomination_id: int|null,
     *     custom_amount: float|null
     * }
     */
    private function buildQuote(
        PartnerCatalogItem $catalogItem,
        string $priceType,
        float $unitPrice,
        int $quantity,
        ?int $denominationId = null,
        ?float $customAmount = null,
    ): array {
        $roundedUnitPrice = round($unitPrice, 10);

        return [
            'price_type' => $priceType,
            'currency' => strtoupper($catalogItem->currency),
            'unit_price' => $roundedUnitPrice,
            'quantity' => $quantity,
            'subtotal' => round($roundedUnitPrice * $quantity, 10),
            'denomination_id' => $denominationId,
            'custom_amount' => $customAmount,
        ];
    }
}
