<?php

namespace App\DTOs\Supplier;

readonly class CatalogPageResult
{
    /**
     * @param  SupplierProductDTO[]  $products
     */
    public function __construct(
        public array $products,
        public int $pageIndex,
        public int $itemsOnPage,
        public int $totalItems,
        public bool $hasMorePages,
    ) {}
}
