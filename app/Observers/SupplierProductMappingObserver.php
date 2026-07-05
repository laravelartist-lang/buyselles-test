<?php

namespace App\Observers;

use App\Models\SupplierProductMapping;
use App\Services\Supplier\MappedProductCacheService;

class SupplierProductMappingObserver
{
    public function __construct(
        private readonly MappedProductCacheService $mappedProductCacheService,
    ) {}

    public function saved(SupplierProductMapping $mapping): void
    {
        $this->mappedProductCacheService->bustForMapping($mapping);
    }

    public function deleted(SupplierProductMapping $mapping): void
    {
        if (! $mapping->product_id) {
            return;
        }

        cacheRemoveByType(type: 'products');
        \Illuminate\Support\Facades\Cache::forget('supplier_stock:'.$mapping->id);
    }
}
