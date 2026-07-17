<?php

namespace App\Services\Partner;

use App\Models\PartnerCatalogItem;
use App\Models\Product;
use App\Models\ResellerApiKey;
use Illuminate\Database\Eloquent\Builder;

class PartnerProductCatalogQuery
{
    public function __construct(
        private readonly PartnerIdentityService $partnerIdentity,
    ) {}

    /**
     * Digital product types eligible for Partner API catalog and orders.
     *
     * @return array<int, string>
     */
    public function eligibleDigitalProductTypes(): array
    {
        return ['ready_product', 'ready_after_sell'];
    }

    /**
     * @return array{
     *     in_house_local: int|null,
     *     supplier_mapped: int|null,
     *     vendor: int|null
     * }
     */
    public function sampleProductIds(): array
    {
        $baseQuery = PartnerCatalogItem::query()
            ->where('is_active', true)
            ->whereHas('catalog', fn (Builder $query) => $query->where('is_active', true));

        $inHouseLocal = (clone $baseQuery)
            ->whereHas('product', fn (Builder $query) => $query
                ->where('added_by', 'admin')
                ->whereDoesntHave('supplierMapping'))
            ->orderBy('id')
            ->value('product_id');

        $supplierMapped = (clone $baseQuery)
            ->whereHas('product.supplierMapping', fn (Builder $query) => $query
                ->where('is_active', true)
                ->where('is_direct_topup', false))
            ->orderBy('id')
            ->value('product_id');

        $vendor = (clone $baseQuery)
            ->whereHas('product', fn (Builder $query) => $query->where('added_by', 'seller'))
            ->orderBy('id')
            ->value('product_id');

        return [
            'in_house_local' => $inHouseLocal !== null ? (int) $inHouseLocal : null,
            'supplier_mapped' => $supplierMapped !== null ? (int) $supplierMapped : null,
            'vendor' => $vendor !== null ? (int) $vendor : null,
        ];
    }

    public function baseQuery(
        ResellerApiKey $resellerKey,
        ?string $fulfillmentType = null,
        ?string $sellerType = null,
    ): Builder {
        $catalog = $this->partnerIdentity->findCatalog($resellerKey);
        $query = PartnerCatalogItem::query()
            ->where('is_active', true)
            ->with([
                'product.supplierMapping.supplierApi',
                'product.supplierMapping.activeDenominations',
                'activeDenominationPrices.denomination',
            ]);

        if ($catalog === null) {
            return $query->whereRaw('1 = 0');
        }

        $query->where('partner_catalog_id', $catalog->id)
            ->whereHas('product', function (Builder $productQuery) use ($sellerType): void {
                $this->applyProductEligibility($productQuery);

                if ($sellerType === 'in_house') {
                    $productQuery->where('added_by', 'admin');
                } elseif ($sellerType === 'vendor') {
                    $productQuery->where('added_by', 'seller');
                }
            });

        if ($fulfillmentType !== null) {
            $this->applyFulfillmentFilter($query, $fulfillmentType);
        }

        return $query;
    }

    public function findEligibleItem(ResellerApiKey $resellerKey, int $productId): ?PartnerCatalogItem
    {
        return $this->baseQuery($resellerKey)
            ->where('product_id', $productId)
            ->first();
    }

    public function findOrderItem(
        ResellerApiKey $resellerKey,
        int $productId,
        bool $lockForUpdate = false,
    ): ?PartnerCatalogItem {
        $query = $this->baseQuery($resellerKey)
            ->where('product_id', $productId);

        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        return $query->first();
    }

    private function applyProductEligibility(Builder $query): void
    {
        $query->withoutGlobalScope(Product::STOREFRONT_SCOPE)
            ->where('product_type', 'digital')
            ->whereIn('digital_product_type', $this->eligibleDigitalProductTypes())
            ->where('status', 1)
            ->where('request_status', 1)
            ->where(function (Builder $builder): void {
                $builder->where('partner_api_only', true)
                    ->orWhere(function (Builder $storefrontQuery): void {
                        $storefrontQuery->where('partner_api_only', false)
                            ->where('added_by', 'admin');
                    });
            });

        $this->applyVendorPartnerApprovalConstraint($query);
    }

    private function applyVendorPartnerApprovalConstraint(Builder $query): void
    {
        $query->where(function (Builder $builder): void {
            $builder->where('added_by', 'admin')
                ->orWhere(function (Builder $vendorQuery): void {
                    $vendorQuery->where('added_by', 'seller')
                        ->where('partner_approved', 1);
                });
        });
    }

    private function applyFulfillmentFilter(Builder $query, string $fulfillmentType): void
    {
        if ($fulfillmentType === 'direct_topup') {
            $query->whereHas('product.supplierMapping', fn (Builder $mappingQuery) => $mappingQuery
                ->where('is_active', true)
                ->where('is_direct_topup', true)
                ->whereHas('supplierApi', fn (Builder $supplierQuery) => $supplierQuery->where('is_active', true)));

            return;
        }

        if ($fulfillmentType === 'supplier_codes') {
            $query->whereHas('product.supplierMapping', fn (Builder $mappingQuery) => $mappingQuery
                ->where('is_active', true)
                ->where('is_direct_topup', false)
                ->whereHas('supplierApi', fn (Builder $supplierQuery) => $supplierQuery->where('is_active', true)));

            return;
        }

        if ($fulfillmentType === 'local_codes') {
            $query->whereDoesntHave('product.supplierMapping', fn (Builder $mappingQuery) => $mappingQuery
                ->where('is_active', true)
                ->whereHas('supplierApi', fn (Builder $supplierQuery) => $supplierQuery->where('is_active', true)));
        }
    }
}
