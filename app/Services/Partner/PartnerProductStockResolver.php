<?php

namespace App\Services\Partner;

use App\Models\DigitalProductCode;
use App\Models\Product;
use App\Models\SupplierProductMapping;
use App\Services\Supplier\SupplierManager;
use Illuminate\Database\Eloquent\Builder;

class PartnerProductStockResolver
{
    public function __construct(
        private readonly SupplierManager $supplierManager,
    ) {}

    public function resolve(Product $product): int
    {
        $mapping = $this->resolveActiveCodeMapping($product);

        if ($mapping !== null) {
            return max(0, $this->supplierManager->getAvailableStockForMapping($mapping));
        }

        return $this->localAvailableCount((int) $product->id);
    }

    public function localAvailableCount(int $productId): int
    {
        return DigitalProductCode::query()
            ->where('product_id', $productId)
            ->where('status', 'available')
            ->where('is_active', true)
            ->where(function (Builder $query): void {
                $query->whereNull('expiry_date')
                    ->orWhereDate('expiry_date', '>=', now()->toDateString());
            })
            ->count();
    }

    public function resolveActiveCodeMapping(Product $product): ?SupplierProductMapping
    {
        if ($product->relationLoaded('supplierMapping')) {
            $mapping = $product->supplierMapping;

            if ($this->isEligibleCodeMapping($mapping)) {
                return $mapping;
            }

            return null;
        }

        return SupplierProductMapping::query()
            ->where('product_id', $product->id)
            ->where('is_active', true)
            ->where('is_direct_topup', false)
            ->whereHas('supplierApi', fn (Builder $query) => $query->where('is_active', true))
            ->orderBy('priority')
            ->with('supplierApi')
            ->first();
    }

    private function isEligibleCodeMapping(?SupplierProductMapping $mapping): bool
    {
        if ($mapping === null || ! $mapping->is_active || $mapping->is_direct_topup) {
            return false;
        }

        return $mapping->supplierApi !== null && $mapping->supplierApi->is_active;
    }
}
