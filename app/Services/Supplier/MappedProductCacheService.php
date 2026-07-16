<?php

namespace App\Services\Supplier;

use App\Models\Product;
use App\Models\SupplierProductMapping;
use App\Services\DigitalProductCodeService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class MappedProductCacheService
{
    public function __construct(
        private readonly SupplierManager $supplierManager,
        private readonly DigitalProductCodeService $codeService,
    ) {}

    public function bustForMapping(SupplierProductMapping $mapping, bool $refreshSupplierStock = true): void
    {
        if (! $mapping->product_id) {
            return;
        }

        Cache::forget('supplier_stock:'.$mapping->id);
        Cache::forget('bamboo_face_value:'.$mapping->supplier_product_id);

        cacheRemoveByType(type: 'products');

        $this->syncProductDisplayPrice($mapping);

        $this->codeService->syncStock((int) $mapping->product_id);

        if ($refreshSupplierStock) {
            try {
                $freshMapping = SupplierProductMapping::query()
                    ->with('supplierApi')
                    ->find($mapping->id);

                if ($freshMapping && $freshMapping->supplierApi?->is_active) {
                    $this->supplierManager->getAvailableStockForMapping($freshMapping);
                }
            } catch (\Throwable $e) {
                Log::warning('MappedProductCacheService: supplier stock refresh failed', [
                    'mapping_id' => $mapping->id,
                    'product_id' => $mapping->product_id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info('MappedProductCacheService: product cache busted', [
            'mapping_id' => $mapping->id,
            'product_id' => $mapping->product_id,
        ]);
    }

    public function syncProductDisplayPrice(SupplierProductMapping $mapping): void
    {
        if (! $mapping->product_id) {
            return;
        }

        $product = Product::query()->find($mapping->product_id);

        if (! $product) {
            return;
        }

        $displayPrice = $mapping->getStartingDisplayPrice();
        $updates = [];

        if ($displayPrice > 0 && (float) $product->getRawOriginal('unit_price') !== $displayPrice) {
            $updates['unit_price'] = $displayPrice;
        }

        $costPrice = (float) $mapping->cost_price;
        if ($costPrice >= 0 && (float) $product->purchase_price !== $costPrice) {
            $updates['purchase_price'] = $costPrice;
        }

        if ((bool) $mapping->is_direct_topup) {
            $minQuantity = $this->resolveDirectTopUpMinQuantity($mapping);

            if ($minQuantity !== null && (int) $product->minimum_order_qty !== $minQuantity) {
                $updates['minimum_order_qty'] = $minQuantity;
            }
        }

        if ($updates === []) {
            return;
        }

        Product::where('id', $mapping->product_id)->update($updates);

        Log::info('MappedProductCacheService: product prices synced from mapping', [
            'product_id' => $mapping->product_id,
            'mapping_id' => $mapping->id,
            'updates' => $updates,
        ]);
    }

    private function resolveDirectTopUpMinQuantity(SupplierProductMapping $mapping): ?int
    {
        if ($mapping->direct_topup_bundle_quantity !== null) {
            $bundleQuantity = (float) $mapping->direct_topup_bundle_quantity;

            if ($bundleQuantity > 0) {
                return (int) ceil($bundleQuantity);
            }
        }

        return $this->resolveCatalogMinQuantity($mapping);
    }

    private function resolveCatalogMinQuantity(SupplierProductMapping $mapping): ?int
    {
        if (! $mapping->supplier_api_id || trim((string) $mapping->supplier_product_id) === '') {
            return null;
        }

        $catalog = Cache::get(SupplierCatalogSyncService::catalogCacheKey((int) $mapping->supplier_api_id), []);

        if (! is_array($catalog)) {
            return null;
        }

        foreach ($catalog as $item) {
            if (! is_array($item)) {
                continue;
            }

            if ((string) ($item['id'] ?? '') !== (string) $mapping->supplier_product_id) {
                continue;
            }

            $minQuantity = (float) ($item['min_quantity'] ?? 0);

            if ($minQuantity <= 0) {
                return null;
            }

            return (int) ceil($minQuantity);
        }

        return null;
    }
}
