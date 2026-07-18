<?php

namespace App\Services\Partner;

use App\Models\PartnerCatalogItem;
use App\Models\SupplierProductDenomination;
use App\Services\DirectTopUp\DirectTopUpService;

class PartnerOrderRequirementsResolver
{
    public function __construct(
        private readonly DirectTopUpService $directTopUpService,
        private readonly PartnerProductStockResolver $stockResolver,
    ) {}

    /**
     * @return array{
     *     fulfillment_type: string,
     *     pricing_type: string,
     *     required: array<int, string>,
     *     optional: array<int, string>,
     *     conditional: array<string, string>
     * }
     */
    public function resolve(PartnerCatalogItem $catalogItem): array
    {
        $catalogItem->loadMissing([
            'product.supplierMapping.supplierApi',
            'product.supplierMapping.activeDenominations',
        ]);

        $product = $catalogItem->product;
        $fulfillmentType = $this->resolveFulfillmentType($product);
        $pricingType = $this->resolvePricingType($catalogItem, $fulfillmentType);

        $required = ['product_id', 'quantity'];
        $optional = ['reference'];
        $conditional = [
            'expected_total' => 'Recommended when pricing.type is denominations or fulfillment_type is direct_topup. Copy data.total from quote.',
        ];

        if ($fulfillmentType === 'direct_topup') {
            $required[] = 'direct_topup_account_id';
            $conditional['direct_topup_account_id'] = 'Required when fulfillment_type is direct_topup.';
        } else {
            $conditional['direct_topup_account_id'] = 'Not used for this product. Only required for direct_topup fulfillment.';
        }

        if ($pricingType === 'denominations') {
            $required[] = 'supplier_denomination_id';
            $conditional['supplier_denomination_id'] = 'Required when pricing.type is denominations.';
            $conditional['custom_amount'] = 'Required when the selected denomination type is variable.';
        } else {
            $mapping = $catalogItem->product->supplierMapping;
            $hasOptionalDenominations = $mapping !== null
                && ! $mapping->requiresDenominationSelection()
                && ($mapping->activeDenominations ?? collect())->isNotEmpty();

            if ($hasOptionalDenominations) {
                $optional[] = 'supplier_denomination_id';
                $conditional['supplier_denomination_id'] = 'Optional. Use to select a specific face value when fixed denominations are synced.';
            } else {
                $conditional['supplier_denomination_id'] = 'Not used for this product.';
            }

            $conditional['custom_amount'] = 'Not used unless pricing.type is denominations and the denomination is variable.';
        }

        return [
            'fulfillment_type' => $fulfillmentType,
            'pricing_type' => $pricingType,
            'required' => array_values(array_unique($required)),
            'optional' => array_values(array_unique($optional)),
            'conditional' => $conditional,
        ];
    }

    /**
     * @return array<int, string>
     */
    public function requiredFieldsForRequest(
        PartnerCatalogItem $catalogItem,
        ?int $denominationId = null,
    ): array {
        $requirements = $this->resolve($catalogItem);
        $required = $requirements['required'];

        if ($requirements['pricing_type'] === 'denominations' && $denominationId !== null) {
            $denomination = $this->findDenomination($catalogItem, $denominationId);

            if ($denomination?->isVariable()) {
                $required[] = 'custom_amount';
            }
        }

        return array_values(array_unique($required));
    }

    /**
     * @return array<int, string>
     */
    public function notApplicableFieldsWhenProvided(
        PartnerCatalogItem $catalogItem,
        ?int $denominationId = null,
    ): array {
        $requirements = $this->resolve($catalogItem);
        $notApplicable = [];

        if ($requirements['fulfillment_type'] !== 'direct_topup') {
            $notApplicable[] = 'direct_topup_account_id';
        }

        if ($requirements['pricing_type'] !== 'denominations') {
            $notApplicable[] = 'custom_amount';
        } elseif ($denominationId !== null) {
            $denomination = $this->findDenomination($catalogItem, $denominationId);

            if ($denomination?->isFixed()) {
                $notApplicable[] = 'custom_amount';
            }
        }

        return $notApplicable;
    }

    public function isDirectTopUpProduct(PartnerCatalogItem $catalogItem): bool
    {
        return $this->directTopUpService->isDirectTopUpProduct($catalogItem->product);
    }

    private function resolveFulfillmentType(\App\Models\Product $product): string
    {
        if ($this->directTopUpService->isDirectTopUpProduct($product)) {
            return 'direct_topup';
        }

        return $this->stockResolver->resolveActiveCodeMapping($product) !== null
            ? 'supplier_codes'
            : 'local_codes';
    }

    private function resolvePricingType(PartnerCatalogItem $catalogItem, string $fulfillmentType): string
    {
        if ($fulfillmentType === 'direct_topup') {
            return 'direct_topup_bundle';
        }

        $mapping = $catalogItem->product->supplierMapping;

        if ($mapping !== null && $mapping->requiresDenominationSelection()) {
            return 'denominations';
        }

        return 'fixed';
    }

    private function findDenomination(
        PartnerCatalogItem $catalogItem,
        int $denominationId,
    ): ?SupplierProductDenomination {
        $mapping = $catalogItem->product->supplierMapping;

        if ($mapping === null) {
            return null;
        }

        $mapping->loadMissing('activeDenominations');

        /** @var SupplierProductDenomination|null $denomination */
        $denomination = $mapping->activeDenominations->firstWhere('id', $denominationId);

        return $denomination;
    }
}
