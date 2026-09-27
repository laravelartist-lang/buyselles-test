<?php

namespace App\Services\Partner;

use App\Models\PartnerCatalogItem;
use App\Models\Product;
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
     *     required: list<string>,
     *     optional: list<string>,
     *     conditional: array<string, string>
     * }
     */
    public function resolve(PartnerCatalogItem $catalogItem): array
    {
        $catalogItem->loadMissing([
            'product.supplierMapping.activeDenominations',
        ]);

        $product = $catalogItem->product;
        $fulfillmentType = $this->fulfillmentType($product);
        $pricingType = $this->pricingType($catalogItem);

        $required = ['product_id', 'quantity'];
        $optional = ['reference', 'expected_total'];
        $conditional = [
            'supplier_denomination_id' => 'Required when pricing.type is denominations.',
            'custom_amount' => 'Required when the selected denomination type is variable.',
            'direct_topup_account_id' => $fulfillmentType === 'direct_topup'
                ? 'Required for direct top-up fulfillment.'
                : 'Not used for this product. Only required for direct_topup fulfillment.',
        ];

        if ($fulfillmentType === 'direct_topup') {
            $required[] = 'direct_topup_account_id';
        }

        if ($pricingType === 'denominations') {
            $required[] = 'supplier_denomination_id';
        }

        return [
            'fulfillment_type' => $fulfillmentType,
            'pricing_type' => $pricingType,
            'required' => $required,
            'optional' => $optional,
            'conditional' => $conditional,
        ];
    }

    private function fulfillmentType(Product $product): string
    {
        if ($this->directTopUpService->isDirectTopUpProduct($product)) {
            return 'direct_topup';
        }

        return $this->stockResolver->resolveActiveCodeMapping($product) !== null
            ? 'supplier_codes'
            : 'local_codes';
    }

    private function pricingType(PartnerCatalogItem $catalogItem): string
    {
        $mapping = $catalogItem->product->supplierMapping;

        if ($mapping?->is_direct_topup) {
            return 'direct_topup_bundle';
        }

        if ($mapping !== null && $mapping->requiresDenominationSelection()) {
            return 'denominations';
        }

        return 'fixed';
    }
}
