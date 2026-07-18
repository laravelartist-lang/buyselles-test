<?php

namespace App\Services\Partner;

use App\Models\Product;
use App\Models\SupplierProductDenomination;
use App\Models\SupplierProductMapping;

class PartnerSupplierCostResolver
{
    public function __construct(
        private readonly PartnerProductStockResolver $stockResolver,
    ) {}

    /**
     * Resolve wholesale supplier cost and metadata for a partner catalog product.
     *
     * @return array{
     *     unit_cost: float,
     *     supplier_api_id: int|null,
     *     supplier_name: string|null,
     *     driver: string|null,
     *     supplier_product_mapping_id: int|null,
     *     supplier_product_id: string|null
     * }
     */
    public function resolve(
        Product $product,
        ?int $denominationId = null,
        ?float $customAmount = null,
    ): array {
        $product->loadMissing([
            'supplierMapping.supplierApi',
            'supplierMapping.activeDenominations',
        ]);

        $mapping = $this->resolveMapping($product);
        $denomination = $this->resolveDenomination($mapping, $denominationId);

        if ($mapping === null) {
            return $this->emptyResolution();
        }

        $supplier = $mapping->supplierApi;

        return [
            'unit_cost' => $this->resolveUnitCost($mapping, $denomination, $customAmount),
            'supplier_api_id' => $supplier?->id,
            'supplier_name' => $supplier?->name,
            'driver' => $supplier?->driver,
            'supplier_product_mapping_id' => $mapping->id,
            'supplier_product_id' => $this->resolveSupplierProductId($mapping, $denomination),
        ];
    }

    /**
     * @return array{
     *     id: int|null,
     *     name: string|null,
     *     driver: string|null,
     *     sku: string|null,
     *     mapping_id: int|null
     * }
     */
    public function supplierMetadata(Product $product, ?int $denominationId = null): array
    {
        $resolution = $this->resolve($product, $denominationId);

        if ($resolution['supplier_api_id'] === null) {
            return [
                'id' => null,
                'name' => null,
                'driver' => null,
                'sku' => null,
                'mapping_id' => null,
            ];
        }

        return [
            'id' => $resolution['supplier_api_id'],
            'name' => $resolution['supplier_name'],
            'driver' => $resolution['driver'],
            'sku' => $resolution['supplier_product_id'],
            'mapping_id' => $resolution['supplier_product_mapping_id'],
        ];
    }

    private function resolveMapping(Product $product): ?SupplierProductMapping
    {
        $directTopUpMapping = $product->supplierMapping;

        if ($this->isEligibleDirectTopUpMapping($directTopUpMapping)) {
            return $directTopUpMapping;
        }

        return $this->stockResolver->resolveActiveCodeMapping($product);
    }

    private function resolveDenomination(
        ?SupplierProductMapping $mapping,
        ?int $denominationId,
    ): ?SupplierProductDenomination {
        if ($mapping === null || $denominationId === null) {
            return null;
        }

        if ($mapping->relationLoaded('activeDenominations')) {
            /** @var SupplierProductDenomination|null $denomination */
            $denomination = $mapping->activeDenominations->firstWhere('id', $denominationId);

            return $denomination;
        }

        return $mapping->activeDenominations()
            ->where('id', $denominationId)
            ->first();
    }

    private function resolveUnitCost(
        SupplierProductMapping $mapping,
        ?SupplierProductDenomination $denomination,
        ?float $customAmount,
    ): float {
        if ($mapping->is_direct_topup) {
            return round((float) $mapping->cost_price, 10);
        }

        if ($denomination?->isFixed()) {
            return round((float) ($denomination->cost_price ?? 0), 10);
        }

        if ($denomination?->isVariable()) {
            if ($denomination->cost_price !== null && (float) $denomination->cost_price > 0) {
                return round((float) $denomination->cost_price, 10);
            }

            if ($customAmount !== null && $customAmount > 0) {
                return round($customAmount, 10);
            }

            return round((float) $mapping->cost_price, 10);
        }

        return round((float) $mapping->cost_price, 10);
    }

    private function resolveSupplierProductId(
        SupplierProductMapping $mapping,
        ?SupplierProductDenomination $denomination,
    ): ?string {
        if ($denomination !== null && filled($denomination->supplier_product_id)) {
            return (string) $denomination->supplier_product_id;
        }

        return filled($mapping->supplier_product_id)
            ? (string) $mapping->supplier_product_id
            : null;
    }

    private function isEligibleDirectTopUpMapping(?SupplierProductMapping $mapping): bool
    {
        if ($mapping === null || ! $mapping->is_active || ! $mapping->is_direct_topup) {
            return false;
        }

        return $mapping->supplierApi !== null && $mapping->supplierApi->is_active;
    }

    /**
     * @return array{
     *     unit_cost: float,
     *     supplier_api_id: null,
     *     supplier_name: null,
     *     driver: null,
     *     supplier_product_mapping_id: null,
     *     supplier_product_id: null
     * }
     */
    private function emptyResolution(): array
    {
        return [
            'unit_cost' => 0.0,
            'supplier_api_id' => null,
            'supplier_name' => null,
            'driver' => null,
            'supplier_product_mapping_id' => null,
            'supplier_product_id' => null,
        ];
    }
}
