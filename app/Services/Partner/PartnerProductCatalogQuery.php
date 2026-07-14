<?php

namespace App\Services\Partner;

use App\Models\Product;
use App\Models\SupplierProductMapping;
use Illuminate\Database\Eloquent\Builder;

class PartnerProductCatalogQuery
{
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
        $inHouseLocal = $this->baseQuery(includeVendor: false)
            ->whereDoesntHave('supplierMapping', function (Builder $query): void {
                $query->where('is_active', true)
                    ->where('is_direct_topup', false)
                    ->whereHas('supplierApi', fn (Builder $supplierQuery) => $supplierQuery->where('is_active', true));
            })
            ->orderBy('id')
            ->value('id');

        $supplierMapped = $this->baseQuery(includeVendor: false)
            ->whereHas('supplierMapping', function (Builder $query): void {
                $query->where('is_active', true)
                    ->where('is_direct_topup', false)
                    ->whereHas('supplierApi', fn (Builder $supplierQuery) => $supplierQuery->where('is_active', true));
            })
            ->orderBy('id')
            ->value('id');

        $vendor = $this->baseQuery(includeVendor: true)
            ->where('added_by', 'seller')
            ->orderBy('id')
            ->value('id');

        return [
            'in_house_local' => $inHouseLocal !== null ? (int) $inHouseLocal : null,
            'supplier_mapped' => $supplierMapped !== null ? (int) $supplierMapped : null,
            'vendor' => $vendor !== null ? (int) $vendor : null,
        ];
    }

    public function baseQuery(
        bool $includeVendor = false,
        ?string $fulfillmentType = null,
        ?string $sellerType = null,
    ): Builder {
        $query = Product::query()
            ->where('product_type', 'digital')
            ->whereIn('digital_product_type', $this->eligibleDigitalProductTypes())
            ->where('status', 1)
            ->where('request_status', 1)
            ->whereNotExists(function ($subQuery): void {
                $subQuery->selectRaw('1')
                    ->from('supplier_product_mappings')
                    ->whereColumn('supplier_product_mappings.product_id', 'products.id')
                    ->where('supplier_product_mappings.is_active', true)
                    ->where('supplier_product_mappings.is_direct_topup', true)
                    ->whereExists(function ($supplierQuery): void {
                        $supplierQuery->selectRaw('1')
                            ->from('supplier_apis')
                            ->whereColumn('supplier_apis.id', 'supplier_product_mappings.supplier_api_id')
                            ->where('supplier_apis.is_active', true);
                    });
            });

        if ($sellerType === 'in_house') {
            $query->where('added_by', 'admin');
        } elseif ($sellerType === 'vendor') {
            $query->where('added_by', 'seller');
            $query->where('partner_approved', 1);
        } elseif ($includeVendor) {
            $query->whereIn('added_by', ['admin', 'seller']);
            $this->applyVendorPartnerApprovalConstraint($query);
        } else {
            $query->where('added_by', 'admin');
        }

        if ($fulfillmentType === 'supplier_codes') {
            $query->whereExists(function ($subQuery): void {
                $subQuery->selectRaw('1')
                    ->from('supplier_product_mappings')
                    ->whereColumn('supplier_product_mappings.product_id', 'products.id')
                    ->where('supplier_product_mappings.is_active', true)
                    ->where('supplier_product_mappings.is_direct_topup', false)
                    ->whereExists(function ($supplierQuery): void {
                        $supplierQuery->selectRaw('1')
                            ->from('supplier_apis')
                            ->whereColumn('supplier_apis.id', 'supplier_product_mappings.supplier_api_id')
                            ->where('supplier_apis.is_active', true);
                    });
            });
        } elseif ($fulfillmentType === 'local_codes') {
            $query->whereNotExists(function ($subQuery): void {
                $subQuery->selectRaw('1')
                    ->from('supplier_product_mappings')
                    ->whereColumn('supplier_product_mappings.product_id', 'products.id')
                    ->where('supplier_product_mappings.is_active', true)
                    ->where('supplier_product_mappings.is_direct_topup', false)
                    ->whereExists(function ($supplierQuery): void {
                        $supplierQuery->selectRaw('1')
                            ->from('supplier_apis')
                            ->whereColumn('supplier_apis.id', 'supplier_product_mappings.supplier_api_id')
                            ->where('supplier_apis.is_active', true);
                    });
            });
        }

        return $query;
    }

    public function findEligibleProduct(
        int $id,
        bool $includeVendor = false,
    ): ?Product {
        return $this->baseQuery(includeVendor: $includeVendor)
            ->with(['supplierMapping.supplierApi'])
            ->find($id);
    }

    public function findOrderProduct(int $id): ?Product
    {
        $query = Product::query()
            ->where('product_type', 'digital')
            ->whereIn('digital_product_type', $this->eligibleDigitalProductTypes())
            ->where('status', 1)
            ->where('request_status', 1)
            ->whereIn('added_by', ['admin', 'seller']);

        $this->applyVendorPartnerApprovalConstraint($query);

        return $query
            ->with(['supplierMapping.supplierApi'])
            ->find($id);
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

    public function hasActiveDirectTopupMapping(int $productId): bool
    {
        return SupplierProductMapping::query()
            ->where('product_id', $productId)
            ->where('is_active', true)
            ->where('is_direct_topup', true)
            ->whereHas('supplierApi', fn (Builder $query) => $query->where('is_active', true))
            ->exists();
    }
}
