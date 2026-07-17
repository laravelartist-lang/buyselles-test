<?php

namespace App\Services\Partner;

use App\Models\PartnerCatalogItem;
use App\Models\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class GlobalPartnerCatalogQuery
{
    /**
     * Digital product types eligible for the global partner catalog pool.
     *
     * @return array<int, string>
     */
    public function eligibleDigitalProductTypes(): array
    {
        return ['ready_product', 'ready_after_sell'];
    }

    public function baseQuery(): Builder
    {
        return Product::query()
            ->where('added_by', 'admin')
            ->where('product_type', 'digital')
            ->whereIn('digital_product_type', $this->eligibleDigitalProductTypes())
            ->where('status', 1)
            ->where('request_status', 1)
            ->with([
                'supplierMapping.supplierApi',
                'supplierMapping.activeDenominations',
            ]);
    }

    public function paginate(
        ?string $search = null,
        ?int $supplierId = null,
        ?string $fulfillmentType = null,
        int $perPage = 30,
    ): LengthAwarePaginator {
        $query = $this->baseQuery();

        if ($search !== null && trim($search) !== '') {
            $term = trim($search);
            $query->where(function (Builder $builder) use ($term): void {
                $builder->where('name', 'like', "%{$term}%")
                    ->orWhere('code', 'like', "%{$term}%")
                    ->orWhere('id', $term);
            });
        }

        if ($supplierId !== null && $supplierId > 0) {
            $query->whereHas('supplierMapping', fn (Builder $mappingQuery) => $mappingQuery
                ->where('supplier_api_id', $supplierId)
                ->where('is_active', true));
        }

        if ($fulfillmentType !== null) {
            $this->applyFulfillmentFilter($query, $fulfillmentType);
        }

        return $query
            ->withCount([
                'partnerCatalogItems as assigned_partners_count' => function (Builder $itemQuery): void {
                    $itemQuery->whereHas('catalog', fn (Builder $catalogQuery) => $catalogQuery->where('is_active', true));
                },
            ])
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function findEligibleProduct(int $productId): ?Product
    {
        return $this->baseQuery()->whereKey($productId)->first();
    }

    /**
     * @return Collection<int, PartnerCatalogItem>
     */
    public function partnerAssignmentsForProduct(int $productId): Collection
    {
        return PartnerCatalogItem::query()
            ->where('product_id', $productId)
            ->with([
                'catalog.user',
                'catalog.seller',
            ])
            ->orderByDesc('id')
            ->get();
    }

    public function resolveFulfillmentType(Product $product): string
    {
        $mapping = $product->supplierMapping;

        if ($mapping === null || ! $mapping->is_active) {
            return 'local_codes';
        }

        if ($mapping->is_direct_topup) {
            return 'direct_topup';
        }

        return 'supplier_mapped';
    }

    public function resolveReferencePrice(Product $product): float
    {
        $mapping = $product->supplierMapping;

        if ($mapping !== null && $mapping->is_active) {
            return $mapping->getStartingDisplayPrice();
        }

        return (float) $product->unit_price;
    }

    private function applyFulfillmentFilter(Builder $query, string $fulfillmentType): void
    {
        if ($fulfillmentType === 'direct_topup') {
            $query->whereHas('supplierMapping', fn (Builder $mappingQuery) => $mappingQuery
                ->where('is_active', true)
                ->where('is_direct_topup', true)
                ->whereHas('supplierApi', fn (Builder $supplierQuery) => $supplierQuery->where('is_active', true)));

            return;
        }

        if ($fulfillmentType === 'supplier_mapped') {
            $query->whereHas('supplierMapping', fn (Builder $mappingQuery) => $mappingQuery
                ->where('is_active', true)
                ->where('is_direct_topup', false)
                ->whereHas('supplierApi', fn (Builder $supplierQuery) => $supplierQuery->where('is_active', true)));

            return;
        }

        if ($fulfillmentType === 'local_codes') {
            $query->whereDoesntHave('supplierMapping', fn (Builder $mappingQuery) => $mappingQuery
                ->where('is_active', true)
                ->whereHas('supplierApi', fn (Builder $supplierQuery) => $supplierQuery->where('is_active', true)));
        }
    }
}
