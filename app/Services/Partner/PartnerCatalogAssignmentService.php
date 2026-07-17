<?php

namespace App\Services\Partner;

use App\Jobs\SyncDenominationsJob;
use App\Models\PartnerCatalog;
use App\Models\PartnerCatalogItem;
use App\Models\Product;
use App\Models\SupplierApi;
use App\Models\SupplierProductMapping;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class PartnerCatalogAssignmentService
{
    public function __construct(
        private readonly GlobalPartnerCatalogQuery $globalCatalogQuery,
    ) {}

    /**
     * Assign an existing storefront product from the global catalog to a partner.
     *
     * @param  array{
     *     currency?: string,
     *     is_active?: bool,
     *     variable_price_type?: string|null,
     *     variable_price_value?: float|null,
     *     denomination_prices?: array<int, array{denomination_id: int, partner_price: float}>
     * }  $options
     */
    public function assignExistingProduct(
        PartnerCatalog $catalog,
        int $productId,
        ?float $partnerPrice,
        array $options = [],
    ): PartnerCatalogItem {
        $product = $this->globalCatalogQuery->findEligibleProduct($productId);

        if ($product === null) {
            throw new InvalidArgumentException('Product is not eligible for the global catalog.');
        }

        if ($partnerPrice !== null && $partnerPrice <= 0) {
            throw new InvalidArgumentException('Partner price must be greater than zero.');
        }

        return DB::transaction(function () use ($catalog, $product, $partnerPrice, $options): PartnerCatalogItem {
            $catalogItem = PartnerCatalogItem::query()
                ->where('partner_catalog_id', $catalog->id)
                ->where('product_id', $product->id)
                ->lockForUpdate()
                ->first();

            $attributes = [
                'partner_catalog_id' => $catalog->id,
                'product_id' => $product->id,
                'partner_price' => $partnerPrice,
                'variable_price_type' => $options['variable_price_type'] ?? null,
                'variable_price_value' => $options['variable_price_value'] ?? null,
                'currency' => strtoupper((string) ($options['currency'] ?? 'USD')),
                'is_active' => (bool) ($options['is_active'] ?? true),
            ];

            if ($catalogItem === null) {
                $catalogItem = PartnerCatalogItem::query()->create($attributes);
            } else {
                $catalogItem->update($attributes);
            }

            if (! empty($options['denomination_prices'])) {
                $catalogItem->denominationPrices()->delete();
                $catalogItem->denominationPrices()->createMany(
                    collect($options['denomination_prices'])
                        ->map(fn (array $price): array => [
                            'supplier_product_denomination_id' => (int) $price['denomination_id'],
                            'partner_price' => (float) $price['partner_price'],
                            'is_active' => true,
                        ])
                        ->all()
                );
            }

            return $catalogItem->fresh([
                'product.supplierMapping.supplierApi',
                'activeDenominationPrices',
            ]);
        });
    }

    public function toggleVisibility(PartnerCatalog $catalog, int $productId, bool $isActive): PartnerCatalogItem
    {
        $catalogItem = PartnerCatalogItem::query()
            ->where('partner_catalog_id', $catalog->id)
            ->where('product_id', $productId)
            ->first();

        if ($catalogItem === null) {
            throw new InvalidArgumentException('Product is not assigned to this partner catalog.');
        }

        $catalogItem->update(['is_active' => $isActive]);

        return $catalogItem->fresh([
            'product.supplierMapping.supplierApi',
            'activeDenominationPrices',
        ]);
    }

    /**
     * Assign a supplier catalog SKU to a partner catalog with an exact partner price.
     *
     * Always uses Partner-API-only products/mappings. Never reuses storefront
     * supplier mappings, so allowed partner items do not appear on the website.
     *
     * @param  array{
     *     supplier_api_id: int,
     *     supplier_product_id: string,
     *     supplier_product_name?: string|null,
     *     cost_price?: float|null,
     *     cost_currency?: string|null,
     *     partner_price: float,
     *     currency?: string,
     *     is_direct_topup?: bool
     * }  $payload
     */
    public function assignFromSupplierCatalog(PartnerCatalog $catalog, array $payload): PartnerCatalogItem
    {
        $supplier = SupplierApi::query()->find((int) $payload['supplier_api_id']);

        if ($supplier === null || ! $supplier->is_active) {
            throw new InvalidArgumentException('Supplier is not available.');
        }

        $supplierProductId = trim((string) $payload['supplier_product_id']);

        if ($supplierProductId === '') {
            throw new InvalidArgumentException('Supplier product ID is required.');
        }

        $partnerPrice = (float) $payload['partner_price'];

        if ($partnerPrice <= 0) {
            throw new InvalidArgumentException('Partner price must be greater than zero.');
        }

        return DB::transaction(function () use ($catalog, $supplier, $supplierProductId, $partnerPrice, $payload): PartnerCatalogItem {
            $mapping = $this->findPartnerOnlyMapping($supplier->id, $supplierProductId);
            $createdMapping = false;

            if ($mapping === null) {
                $mapping = $this->createPartnerOnlyProductAndMapping(
                    supplier: $supplier,
                    supplierProductId: $supplierProductId,
                    supplierProductName: trim((string) ($payload['supplier_product_name'] ?? '')),
                    costPrice: (float) ($payload['cost_price'] ?? 0),
                    costCurrency: strtoupper((string) ($payload['cost_currency'] ?? 'USD')),
                    partnerPrice: $partnerPrice,
                    isDirectTopup: (bool) ($payload['is_direct_topup'] ?? false) && $supplier->supports_direct_top_up,
                );
                $createdMapping = true;
            } elseif (! $mapping->is_active) {
                $mapping->update(['is_active' => true]);
            }

            $catalogItem = PartnerCatalogItem::query()
                ->where('partner_catalog_id', $catalog->id)
                ->where('product_id', $mapping->product_id)
                ->lockForUpdate()
                ->first();

            $attributes = [
                'partner_catalog_id' => $catalog->id,
                'product_id' => $mapping->product_id,
                'partner_price' => $partnerPrice,
                'variable_price_type' => null,
                'variable_price_value' => null,
                'currency' => strtoupper((string) ($payload['currency'] ?? 'USD')),
                'is_active' => true,
            ];

            if ($catalogItem === null) {
                $catalogItem = PartnerCatalogItem::query()->create($attributes);
            } else {
                $catalogItem->update($attributes);
            }

            if ($createdMapping) {
                SyncDenominationsJob::dispatch($mapping->id)->afterCommit();
            }

            return $catalogItem->fresh([
                'product.supplierMapping.supplierApi',
                'activeDenominationPrices',
            ]);
        });
    }

    private function findPartnerOnlyMapping(int $supplierApiId, string $supplierProductId): ?SupplierProductMapping
    {
        return SupplierProductMapping::query()
            ->where('supplier_api_id', $supplierApiId)
            ->where('supplier_product_id', $supplierProductId)
            ->whereHas('product', function ($query): void {
                $query->withoutGlobalScope(Product::STOREFRONT_SCOPE)
                    ->where('partner_api_only', true);
            })
            ->orderByDesc('is_active')
            ->orderBy('priority')
            ->lockForUpdate()
            ->first();
    }

    private function createPartnerOnlyProductAndMapping(
        SupplierApi $supplier,
        string $supplierProductId,
        string $supplierProductName,
        float $costPrice,
        string $costCurrency,
        float $partnerPrice,
        bool $isDirectTopup,
    ): SupplierProductMapping {
        $name = $supplierProductName !== ''
            ? $supplierProductName
            : ($supplier->name.' '.$supplierProductId);

        $product = Product::withoutGlobalScope(Product::STOREFRONT_SCOPE)->create([
            'added_by' => 'admin',
            'user_id' => auth('admin')->id() ?? 1,
            'name' => $name,
            'slug' => Str::slug(Str::limit($name, 60, '')).'-'.Str::lower(Str::random(6)),
            'code' => $this->generateUniqueProductCode(),
            'product_type' => 'digital',
            'digital_product_type' => 'ready_after_sell',
            'unit_price' => $partnerPrice,
            'purchase_price' => max(0, $costPrice),
            'current_stock' => 0,
            'minimum_order_qty' => 1,
            'status' => 1,
            'request_status' => 1,
            'featured_status' => 0,
            'featured' => 0,
            'partner_api_only' => true,
            'tax' => 0,
            'discount' => 0,
            'shipping_cost' => 0,
            'multiply_qty' => 0,
            'details' => 'Partner API catalog item from '.$supplier->name,
        ]);

        return SupplierProductMapping::query()->create([
            'product_id' => $product->id,
            'supplier_api_id' => $supplier->id,
            'supplier_product_id' => $supplierProductId,
            'supplier_product_name' => $supplierProductName !== '' ? $supplierProductName : null,
            'cost_price' => max(0, $costPrice),
            'cost_currency' => $costCurrency !== '' ? $costCurrency : 'USD',
            'markup_type' => 'flat',
            'markup_value' => 0,
            'priority' => 0,
            'is_active' => true,
            'is_customizable' => false,
            'is_direct_topup' => $isDirectTopup,
            'direct_topup_account_label' => $isDirectTopup ? (translate('player_id') ?: 'Player ID') : null,
        ]);
    }

    private function generateUniqueProductCode(): string
    {
        do {
            $code = 'PA'.strtoupper(Str::random(8));
        } while (
            Product::withoutGlobalScope(Product::STOREFRONT_SCOPE)
                ->where('code', $code)
                ->exists()
        );

        return $code;
    }
}
