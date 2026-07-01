<?php

namespace App\Services\Supplier\Concerns;

use App\DTOs\Supplier\SupplierOrderResult;

trait ProvidesUnsupportedDirectTopUp
{
    public function placeTopUpOrder(
        string $supplierProductId,
        float $quantity,
        string $accountId,
        ?float $unitPrice = null,
    ): SupplierOrderResult {
        return new SupplierOrderResult(
            supplierOrderId: null,
            status: 'failed',
            codes: [],
            rawResponse: ['message' => 'Direct top-up is not supported by this supplier driver.'],
        );
    }
}
