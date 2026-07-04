<?php

namespace App\Observers;

use App\Models\SupplierProductMapping;
use App\Services\DigitalProductCodeService;

class SupplierProductMappingObserver
{
    public function __construct(
        private readonly DigitalProductCodeService $codeService,
    ) {}

    /**
     * When a mapping is created or updated, sync the product's price
     * from the mapping so the frontend always shows the correct value.
     */
    public function saved(SupplierProductMapping $mapping): void
    {
        if (! $mapping->product_id) {
            return;
        }

        $this->codeService->applyApiPriceIfManualDepleted($mapping->product_id);
    }
}
