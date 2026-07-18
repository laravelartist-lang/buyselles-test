<?php

namespace App\Services\Partner;

use App\Models\PartnerCatalogItem;
use App\Services\CustomerServiceFeeService;

class PartnerOrderQuoteService
{
    public function __construct(
        private readonly PartnerCatalogPricingService $catalogPricing,
        private readonly PartnerSupplierCostResolver $supplierCostResolver,
        private readonly CustomerServiceFeeService $serviceFeeService,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function quote(
        PartnerCatalogItem $catalogItem,
        int $quantity = 1,
        ?int $denominationId = null,
        ?float $customAmount = null,
    ): array {
        $catalogQuote = $this->catalogPricing->quote(
            catalogItem: $catalogItem,
            quantity: $quantity,
            denominationId: $denominationId,
            customAmount: $customAmount,
        );

        $catalogSubtotal = (float) $catalogQuote['subtotal'];
        $costResolution = $this->supplierCostResolver->resolve(
            product: $catalogItem->product,
            denominationId: $catalogQuote['denomination_id'],
            customAmount: $catalogQuote['custom_amount'],
        );

        $supplierCostTotal = round($costResolution['unit_cost'] * $quantity, 10);
        $serviceFee = $this->serviceFeeService->calculate($catalogSubtotal);
        $total = round($catalogSubtotal + $serviceFee, 10);

        return array_merge($catalogQuote, [
            'catalog_subtotal' => $catalogSubtotal,
            'service_fee' => $serviceFee,
            'service_fee_type' => $this->serviceFeeService->getType(),
            'total' => $total,
            'supplier_cost_total' => $supplierCostTotal,
            'admin_margin' => round($catalogSubtotal - $supplierCostTotal, 10),
            'supplier' => $costResolution['supplier_api_id'] !== null
                ? [
                    'id' => $costResolution['supplier_api_id'],
                    'name' => $costResolution['supplier_name'],
                    'driver' => $costResolution['driver'],
                    'mapping_id' => $costResolution['supplier_product_mapping_id'],
                    'sku' => $costResolution['supplier_product_id'],
                ]
                : null,
        ]);
    }
}
