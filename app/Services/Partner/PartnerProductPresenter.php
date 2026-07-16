<?php

namespace App\Services\Partner;

use App\Models\PartnerCatalogItem;
use App\Models\Product;
use App\Services\DirectTopUp\DirectTopUpService;

class PartnerProductPresenter
{
    public function __construct(
        private readonly PartnerProductStockResolver $stockResolver,
        private readonly DirectTopUpService $directTopUpService,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toListArray(PartnerCatalogItem $catalogItem): array
    {
        $catalogItem->loadMissing([
            'product.supplierMapping.supplierApi',
            'product.supplierMapping.activeDenominations',
            'activeDenominationPrices.denomination',
        ]);

        $product = $catalogItem->product;
        $fulfillmentType = $this->fulfillmentType($product);

        return [
            'id' => $product->id,
            'name' => $product->name,
            'slug' => $product->slug,
            'category_id' => $product->category_id,
            'pricing' => $this->pricing($catalogItem),
            'available_stock' => $fulfillmentType === 'direct_topup'
                ? null
                : $this->stockResolver->resolve($product),
            'thumbnail' => $product->thumbnail_full_url ?? null,
            'seller_type' => $this->sellerType($product),
            'fulfillment_type' => $fulfillmentType,
            'supplier' => $this->supplierDriver($product),
            'requires_account_id' => $fulfillmentType === 'direct_topup',
            'direct_topup' => $this->directTopUp($catalogItem),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toDetailArray(PartnerCatalogItem $catalogItem): array
    {
        $product = $catalogItem->product;

        return array_merge($this->toListArray($catalogItem), [
            'sub_category_id' => $product->sub_category_id,
            'brand_id' => $product->brand_id,
            'description' => $product->details,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function toOrderSnapshot(PartnerCatalogItem $catalogItem, array $quote): array
    {
        $product = $catalogItem->product;

        return [
            'id' => $product->id,
            'name' => $product->name,
            'slug' => $product->slug,
            'user_id' => $product->user_id,
            'added_by' => $product->added_by,
            'product_type' => $product->product_type,
            'digital_product_type' => $product->digital_product_type,
            'partner_catalog_item_id' => $catalogItem->id,
            'pricing' => $quote,
            'category_id' => $product->category_id,
        ];
    }

    public function sellerType(Product $product): string
    {
        return $product->added_by === 'admin' ? 'in_house' : 'vendor';
    }

    public function fulfillmentType(Product $product): string
    {
        if ($product->supplierMapping?->is_direct_topup) {
            return 'direct_topup';
        }

        return $this->stockResolver->resolveActiveCodeMapping($product) !== null
            ? 'supplier_codes'
            : 'local_codes';
    }

    public function supplierDriver(Product $product): ?string
    {
        $mapping = $product->supplierMapping?->is_direct_topup
            ? $product->supplierMapping
            : $this->stockResolver->resolveActiveCodeMapping($product);

        if ($mapping === null || $mapping->supplierApi === null) {
            return null;
        }

        return $mapping->supplierApi->driver;
    }

    /**
     * @return array<string, mixed>
     */
    private function pricing(PartnerCatalogItem $catalogItem): array
    {
        $mapping = $catalogItem->product->supplierMapping;
        $denominations = $mapping?->activeDenominations ?? collect();

        if ($mapping?->is_direct_topup) {
            return [
                'type' => 'direct_topup_bundle',
                'currency' => $catalogItem->currency,
                'price' => $catalogItem->partner_price !== null ? (float) $catalogItem->partner_price : null,
            ];
        }

        if ($denominations->isEmpty()) {
            return [
                'type' => 'fixed',
                'currency' => $catalogItem->currency,
                'unit_price' => $catalogItem->partner_price !== null ? (float) $catalogItem->partner_price : null,
            ];
        }

        return [
            'type' => 'denominations',
            'currency' => $catalogItem->currency,
            'denominations' => $denominations
                ->map(function ($denomination) use ($catalogItem): array {
                    $price = $catalogItem->activeDenominationPrices
                        ->firstWhere('supplier_product_denomination_id', $denomination->id);

                    if ($denomination->isFixed()) {
                        return [
                            'id' => $denomination->id,
                            'type' => 'fixed',
                            'name' => $denomination->name,
                            'face_value' => (float) $denomination->face_value,
                            'face_value_currency' => $denomination->face_value_currency,
                            'partner_price' => $price !== null ? (float) $price->partner_price : null,
                            'available' => $price !== null,
                        ];
                    }

                    return [
                        'id' => $denomination->id,
                        'type' => 'variable',
                        'name' => $denomination->name,
                        'min_face_value' => (float) $denomination->min_face_value,
                        'max_face_value' => (float) $denomination->max_face_value,
                        'face_value_currency' => $denomination->face_value_currency,
                        'price_formula' => $catalogItem->hasVariablePriceFormula()
                            ? [
                                'type' => $catalogItem->variable_price_type,
                                'value' => (float) $catalogItem->variable_price_value,
                            ]
                            : null,
                        'available' => $catalogItem->hasVariablePriceFormula(),
                    ];
                })
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function directTopUp(PartnerCatalogItem $catalogItem): ?array
    {
        $product = $catalogItem->product;
        $mapping = $product->supplierMapping;

        if (! $mapping?->is_direct_topup) {
            return null;
        }

        $accountLabel = trim((string) ($mapping->direct_topup_account_label ?? '')) !== ''
            ? (string) $mapping->direct_topup_account_label
            : (translate('account_id') ?: 'Account ID');

        return [
            'account_label' => $accountLabel,
            'region' => filled($mapping->direct_topup_region)
                ? strtoupper((string) $mapping->direct_topup_region)
                : null,
            'bundle_quantity' => $this->directTopUpService->resolveBundleQuantity($product),
            'bundle_price' => $catalogItem->partner_price !== null
                ? (float) $catalogItem->partner_price
                : null,
            'quantity_label' => translate('direct_topup_credits_quantity') ?: 'Credits',
        ];
    }
}
