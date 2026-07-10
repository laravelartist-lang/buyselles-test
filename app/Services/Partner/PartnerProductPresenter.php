<?php

namespace App\Services\Partner;

use App\Models\Product;

class PartnerProductPresenter
{
    public function __construct(
        private readonly PartnerProductStockResolver $stockResolver,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toListArray(Product $product): array
    {
        return [
            'id' => $product->id,
            'name' => $product->name,
            'slug' => $product->slug,
            'category_id' => $product->category_id,
            'unit_price' => (float) $product->unit_price,
            'purchase_price' => (float) $product->purchase_price,
            'available_stock' => $this->stockResolver->resolve($product),
            'thumbnail' => $product->thumbnail_full_url ?? null,
            'seller_type' => $this->sellerType($product),
            'fulfillment_type' => $this->fulfillmentType($product),
            'supplier' => $this->supplierDriver($product),
            'requires_account_id' => false,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toDetailArray(Product $product): array
    {
        return array_merge($this->toListArray($product), [
            'sub_category_id' => $product->sub_category_id,
            'brand_id' => $product->brand_id,
            'description' => $product->details,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function toOrderSnapshot(Product $product): array
    {
        return [
            'id' => $product->id,
            'name' => $product->name,
            'slug' => $product->slug,
            'user_id' => $product->user_id,
            'added_by' => $product->added_by,
            'product_type' => $product->product_type,
            'digital_product_type' => $product->digital_product_type,
            'unit_price' => (float) $product->getLocalUnitPrice(),
            'category_id' => $product->category_id,
        ];
    }

    public function sellerType(Product $product): string
    {
        return $product->added_by === 'admin' ? 'in_house' : 'vendor';
    }

    public function fulfillmentType(Product $product): string
    {
        return $this->stockResolver->resolveActiveCodeMapping($product) !== null
            ? 'supplier_codes'
            : 'local_codes';
    }

    public function supplierDriver(Product $product): ?string
    {
        $mapping = $this->stockResolver->resolveActiveCodeMapping($product);

        if ($mapping === null || $mapping->supplierApi === null) {
            return null;
        }

        return $mapping->supplierApi->driver;
    }
}
