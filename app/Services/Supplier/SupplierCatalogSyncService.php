<?php

namespace App\Services\Supplier;

use App\DTOs\Supplier\CatalogPageResult;
use App\DTOs\Supplier\SupplierProductDTO;
use App\Models\SupplierApi;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class SupplierCatalogSyncService
{
    public function __construct(
        private readonly SupplierManager $manager,
    ) {}

    public static function statusCacheKey(int $supplierId): string
    {
        return "supplier_catalog_sync_status_{$supplierId}";
    }

    public static function catalogCacheKey(int $supplierId): string
    {
        return "supplier_catalog_{$supplierId}";
    }

    public static function checkpointCacheKey(int $supplierId): string
    {
        return "supplier_catalog_sync_checkpoint_{$supplierId}";
    }

    public static function checkpointPageCacheKey(int $supplierId, int $pageIndex): string
    {
        return "supplier_catalog_sync_checkpoint_{$supplierId}_page_{$pageIndex}";
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getStatus(int $supplierId): ?array
    {
        $status = Cache::get(self::statusCacheKey($supplierId));

        return is_array($status) ? $status : null;
    }

    public function shouldAbortPageJob(int $supplierId): bool
    {
        $state = $this->getStatus($supplierId)['state'] ?? null;

        return in_array($state, ['cancelled', 'paused'], true);
    }

    /**
     * @return 'cancelled'|'paused'|null
     */
    public function stopReasonAfterPage(int $supplierId): ?string
    {
        $state = $this->getStatus($supplierId)['state'] ?? null;

        if ($state === 'cancelled') {
            return 'cancelled';
        }

        if ($state === 'paused') {
            return 'paused';
        }

        return null;
    }

    public function pauseSync(int $supplierId): void
    {
        $status = $this->getStatus($supplierId) ?? [];
        $checkpoint = Cache::get(self::checkpointCacheKey($supplierId), []);

        Cache::put(self::statusCacheKey($supplierId), array_merge($status, [
            'state' => 'paused',
            'paused_at' => now()->toIso8601String(),
            'next_page' => $checkpoint['next_page'] ?? ($status['next_page'] ?? 0),
            'total_products' => $this->countCheckpointProducts($supplierId, $checkpoint),
            'can_resume' => true,
        ]), now()->addHours(6));

        Log::info('SupplierCatalogSyncService: sync paused', [
            'supplier_id' => $supplierId,
            'next_page' => $checkpoint['next_page'] ?? null,
        ]);
    }

    public function cancelSync(int $supplierId): void
    {
        $status = $this->getStatus($supplierId) ?? [];
        $checkpoint = Cache::get(self::checkpointCacheKey($supplierId), []);

        Cache::put(self::statusCacheKey($supplierId), array_merge($status, [
            'state' => 'cancelled',
            'cancelled_at' => now()->toIso8601String(),
            'next_page' => $checkpoint['next_page'] ?? ($status['next_page'] ?? 0),
            'total_products' => $this->countCheckpointProducts($supplierId, $checkpoint),
            'can_resume' => false,
        ]), now()->addHours(6));

        Log::info('SupplierCatalogSyncService: sync cancelled', [
            'supplier_id' => $supplierId,
        ]);
    }

    public function clearCheckpointData(int $supplierId): void
    {
        $checkpoint = Cache::get(self::checkpointCacheKey($supplierId));

        if (is_array($checkpoint)) {
            foreach ($checkpoint['pages_stored'] ?? [] as $pageIndex) {
                Cache::forget(self::checkpointPageCacheKey($supplierId, (int) $pageIndex));
            }
        }

        Cache::forget(self::checkpointCacheKey($supplierId));
    }

    /**
     * Prepare a sync run. When resuming, keeps accumulated products and next page.
     */
    public function beginSync(int $supplierId, bool $freshStart = false): int
    {
        $statusKey = self::statusCacheKey($supplierId);
        $checkpointKey = self::checkpointCacheKey($supplierId);

        if ($freshStart) {
            Cache::forget(self::catalogCacheKey($supplierId));
            $this->clearCheckpointData($supplierId);
        }

        $checkpoint = Cache::get($checkpointKey);
        $startPage = is_array($checkpoint) ? (int) ($checkpoint['next_page'] ?? 0) : 0;

        if ($freshStart || ! is_array($checkpoint)) {
            $supplier = SupplierApi::find($supplierId);
            $config = $supplier ? $this->paginationConfig($supplier) : ['default_size' => 50];

            $checkpoint = [
                'next_page' => 0,
                'total_pages' => null,
                'total_items' => null,
                'page_size' => $config['default_size'],
                'pages_stored' => [],
                'total_products' => 0,
                'started_at' => now()->toIso8601String(),
            ];
            Cache::put($checkpointKey, $checkpoint, now()->addHours(6));
            $startPage = 0;
        }

        Cache::put($statusKey, [
            'state' => 'running',
            'progress' => $this->calculateProgress($startPage, $checkpoint['total_pages'] ?? null),
            'next_page' => $startPage,
            'total_pages' => $checkpoint['total_pages'] ?? null,
            'total_products' => $this->countCheckpointProducts($supplierId, $checkpoint),
            'resumed' => ! $freshStart && $startPage > 0,
            'started_at' => $checkpoint['started_at'] ?? now()->toIso8601String(),
        ], now()->addHours(6));

        return $startPage;
    }

    public function syncPage(int $supplierId, int $pageIndex): CatalogPageResult
    {
        $supplier = SupplierApi::findOrFail($supplierId);
        $config = $this->paginationConfig($supplier);
        $checkpointKey = self::checkpointCacheKey($supplierId);
        $checkpoint = Cache::get($checkpointKey, [
            'next_page' => $pageIndex,
            'pages_stored' => [],
            'total_products' => 0,
            'started_at' => now()->toIso8601String(),
        ]);

        $pageMeta = [
            'current_page' => $pageIndex,
            'items_on_page' => 0,
            'total' => 0,
            'last_page' => null,
        ];

        $filters = $this->buildPageFilters($supplier, $config, $pageIndex, $pageMeta);

        $driver = $this->manager->driver($supplier);
        $products = $driver->fetchProducts($filters);

        $hasMore = $this->determineHasMorePages($supplier->driver, $pageMeta, $config);
        $mappedProducts = $this->mapProductsToCatalog($products, $supplier);

        Cache::put(self::checkpointPageCacheKey($supplierId, $pageIndex), $mappedProducts, now()->addHours(6));

        $pagesStored = array_values(array_unique(array_merge(
            $checkpoint['pages_stored'] ?? [],
            [$pageIndex]
        )));
        $totalProducts = $this->countCheckpointProducts($supplierId, [
            'pages_stored' => $pagesStored,
            'total_products' => $checkpoint['total_products'] ?? 0,
        ]);

        $totalPages = $this->resolveTotalPages($pageMeta, $config, $pageIndex, $hasMore);
        $nextPage = $pageIndex + 1;

        Cache::put($checkpointKey, [
            'next_page' => $nextPage,
            'total_pages' => $totalPages,
            'total_items' => $pageMeta['total'],
            'page_size' => $config['default_size'],
            'pages_stored' => $pagesStored,
            'total_products' => $totalProducts,
            'started_at' => $checkpoint['started_at'] ?? now()->toIso8601String(),
            'last_successful_page' => $pageIndex,
        ], now()->addHours(6));

        Cache::put(self::statusCacheKey($supplierId), [
            'state' => 'running',
            'progress' => $this->calculateProgress($pageIndex + 1, $totalPages),
            'next_page' => $nextPage,
            'pages_fetched' => $pageIndex + 1,
            'total_pages' => $totalPages,
            'total_products' => $totalProducts,
            'total_items' => $pageMeta['total'],
            'started_at' => $checkpoint['started_at'] ?? now()->toIso8601String(),
        ], now()->addHours(6));

        Log::info('SupplierCatalogSyncService: page fetched', [
            'supplier_id' => $supplierId,
            'driver' => $supplier->driver,
            'page' => $pageIndex,
            'items_on_page' => count($mappedProducts),
            'total_products' => $totalProducts,
            'has_more' => $hasMore,
        ]);

        return new CatalogPageResult(
            products: $products,
            pageIndex: $pageIndex,
            itemsOnPage: count($mappedProducts),
            totalItems: (int) $pageMeta['total'],
            hasMorePages: $hasMore,
        );
    }

    /**
     * Fetch supplier products for a scoped filter (brand/product) without walking the full catalog.
     *
     * @param  array<string, mixed>  $scopeFilters
     * @return SupplierProductDTO[]
     */
    public function fetchScopedProducts(SupplierApi $supplier, array $scopeFilters): array
    {
        $config = $this->paginationConfig($supplier);
        $driver = $this->manager->driver($supplier);
        $allProducts = [];
        $pageIndex = 0;

        do {
            $pageMeta = [
                'current_page' => $pageIndex,
                'items_on_page' => 0,
                'total' => 0,
                'last_page' => null,
            ];

            $filters = array_merge($scopeFilters, $this->buildPageFilters($supplier, $config, $pageIndex, $pageMeta));
            $pageProducts = $driver->fetchProducts($filters);
            $allProducts = array_merge($allProducts, $pageProducts);
            $hasMore = $this->determineHasMorePages($supplier->driver, $pageMeta, $config);
            $pageIndex++;
        } while ($hasMore);

        return $allProducts;
    }

    public function markComplete(int $supplierId): void
    {
        $checkpoint = Cache::get(self::checkpointCacheKey($supplierId), []);
        $supplier = SupplierApi::findOrFail($supplierId);
        $catalog = $this->mergeCheckpointProducts($supplierId, $checkpoint);
        $catalog = $this->enrichCatalogSourcePrices($catalog, $supplier);

        Cache::put(self::catalogCacheKey($supplierId), $catalog, now()->addHours(6));

        Cache::put(self::statusCacheKey($supplierId), [
            'state' => 'done',
            'progress' => 100,
            'has_catalog' => true,
            'total_products' => count($catalog),
            'total_pages' => $checkpoint['total_pages'] ?? null,
            'pages_fetched' => $checkpoint['last_successful_page'] ?? null,
            'finished_at' => now()->toIso8601String(),
        ], now()->addHours(6));

        $this->clearCheckpointData($supplierId);

        Log::info('SupplierCatalogSyncService: completed', [
            'supplier_id' => $supplierId,
            'products' => count($catalog),
        ]);
    }

    public function markFailed(int $supplierId, int $failedPageIndex, ?\Throwable $exception = null): void
    {
        $checkpoint = Cache::get(self::checkpointCacheKey($supplierId), []);
        $message = $exception?->getMessage() ?: 'Unknown error';

        Cache::put(self::statusCacheKey($supplierId), [
            'state' => 'failed',
            'error' => $message,
            'failed_page' => $failedPageIndex,
            'next_page' => $failedPageIndex,
            'can_resume' => true,
            'total_products' => $this->countCheckpointProducts($supplierId, $checkpoint),
            'total_pages' => $checkpoint['total_pages'] ?? null,
            'failed_at' => now()->toIso8601String(),
        ], now()->addHours(6));

        if (! empty($checkpoint)) {
            Cache::put(self::checkpointCacheKey($supplierId), array_merge($checkpoint, [
                'next_page' => $failedPageIndex,
            ]), now()->addHours(6));
        }

        Log::error('SupplierCatalogSyncService: page failed', [
            'supplier_id' => $supplierId,
            'failed_page' => $failedPageIndex,
            'error' => $message,
            'saved_products' => $this->countCheckpointProducts($supplierId, $checkpoint),
        ]);
    }

    public function markPausedAfterPage(int $supplierId, int $pageIndex, CatalogPageResult $result): void
    {
        $checkpoint = Cache::get(self::checkpointCacheKey($supplierId), []);
        $nextPage = $result->hasMorePages ? $pageIndex + 1 : $pageIndex + 1;

        if (is_array($checkpoint)) {
            Cache::put(self::checkpointCacheKey($supplierId), array_merge($checkpoint, [
                'next_page' => $nextPage,
            ]), now()->addHours(6));
        }

        $status = $this->getStatus($supplierId) ?? [];

        Cache::put(self::statusCacheKey($supplierId), array_merge($status, [
            'state' => 'paused',
            'next_page' => $nextPage,
            'pages_fetched' => $pageIndex + 1,
            'total_products' => $this->countCheckpointProducts($supplierId, $checkpoint),
            'can_resume' => true,
            'paused_at' => now()->toIso8601String(),
        ]), now()->addHours(6));
    }

    public function hasResumableCheckpoint(int $supplierId): bool
    {
        $checkpoint = Cache::get(self::checkpointCacheKey($supplierId));

        if (! is_array($checkpoint)) {
            return false;
        }

        return (int) ($checkpoint['next_page'] ?? 0) >= 0
            && (
                ! empty($checkpoint['pages_stored'])
                || (int) ($checkpoint['next_page'] ?? 0) > 0
            );
    }

    /**
     * @param  array<string, mixed>  $checkpoint
     */
    private function countCheckpointProducts(int $supplierId, array $checkpoint): int
    {
        if (! empty($checkpoint['products']) && is_array($checkpoint['products'])) {
            return count($checkpoint['products']);
        }

        $count = 0;
        foreach ($checkpoint['pages_stored'] ?? [] as $pageIndex) {
            $chunk = Cache::get(self::checkpointPageCacheKey($supplierId, (int) $pageIndex), []);
            $count += is_array($chunk) ? count($chunk) : 0;
        }

        return $count;
    }

    /**
     * @param  array<string, mixed>  $checkpoint
     * @return array<int, array<string, mixed>>
     */
    private function mergeCheckpointProducts(int $supplierId, array $checkpoint): array
    {
        if (! empty($checkpoint['products']) && is_array($checkpoint['products'])) {
            return $checkpoint['products'];
        }

        $merged = [];

        foreach ($checkpoint['pages_stored'] ?? [] as $pageIndex) {
            $chunk = Cache::get(self::checkpointPageCacheKey($supplierId, (int) $pageIndex), []);
            if (is_array($chunk)) {
                $merged = array_merge($merged, $chunk);
            }
        }

        return $merged;
    }

    /**
     * @return array<string, mixed>
     */
    private function paginationConfig(SupplierApi $supplier): array
    {
        $settings = $supplier->settings ?? [];

        return match ($supplier->driver) {
            'bamboo' => [
                'page_key' => 'page',
                'size_key' => 'size',
                'page_base' => 0,
                'default_size' => 100,
            ],
            'golf_api' => [
                'page_key' => 'page',
                'size_key' => 'limit',
                'page_base' => 1,
                'default_size' => 100,
            ],
            'kinguin' => [
                'page_key' => 'page',
                'size_key' => 'size',
                'page_base' => 0,
                'default_size' => 50,
            ],
            'reloadly' => [
                'page_key' => 'page',
                'size_key' => 'size',
                'page_base' => 1,
                'default_size' => 50,
            ],
            default => [
                'page_key' => (string) ($settings['pagination_page_param'] ?? 'page'),
                'size_key' => (string) ($settings['pagination_per_page_param'] ?? 'limit'),
                'page_base' => (int) ($settings['pagination_page_base'] ?? 1),
                'default_size' => min((int) ($settings['pagination_per_page_default'] ?? 50), 100),
            ],
        };
    }

    /**
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $pageMeta
     * @return array<string, mixed>
     */
    private function buildPageFilters(SupplierApi $supplier, array $config, int $pageIndex, array &$pageMeta): array
    {
        $pageKey = $config['page_key'];
        $sizeKey = $config['size_key'];
        $pageSize = $config['default_size'];

        return [
            'fetch_all' => false,
            $pageKey => $pageIndex,
            $sizeKey => $pageSize,
            'size' => $pageSize,
            'limit' => $pageSize,
            'on_page' => function (int $page, int $itemsOnPage, int $total) use (&$pageMeta): void {
                $pageMeta['current_page'] = $page;
                $pageMeta['items_on_page'] = $itemsOnPage;
                $pageMeta['total'] = $total;
            },
        ];
    }

    /**
     * @param  array<string, mixed>  $pageMeta
     * @param  array<string, mixed>  $config
     */
    private function determineHasMorePages(string $driver, array $pageMeta, array $config): bool
    {
        $page = $pageMeta['current_page'];
        $itemsOnPage = $pageMeta['items_on_page'];
        $total = $pageMeta['total'];
        $pageSize = $config['default_size'];

        if ($itemsOnPage === 0) {
            return false;
        }

        return match ($driver) {
            'bamboo' => $itemsOnPage >= $pageSize && ($page * $pageSize) < ($total + $pageSize),
            'golf_api' => $total > 0
                ? ($page < (int) ceil($total / $pageSize) && $itemsOnPage >= $pageSize)
                : ($itemsOnPage >= $pageSize),
            'generic_rest' => $total > 0
                ? ($page < (int) ceil($total / $pageSize) && $itemsOnPage >= $pageSize)
                : ($itemsOnPage >= $pageSize),
            default => $itemsOnPage >= $pageSize && (($page + 1) * $pageSize) < max($total, ($page + 1) * $pageSize),
        };
    }

    /**
     * @param  array<string, mixed>  $pageMeta
     * @param  array<string, mixed>  $config
     */
    private function resolveTotalPages(array $pageMeta, array $config, int $pageIndex, bool $hasMore): ?int
    {
        $total = (int) $pageMeta['total'];
        $pageSize = $config['default_size'];

        if ($total <= 0) {
            return $hasMore ? null : ($pageIndex + 1);
        }

        return (int) ceil($total / $pageSize);
    }

    private function calculateProgress(int $nextPage, ?int $totalPages): int
    {
        if ($totalPages === null || $totalPages <= 0) {
            return min(99, $nextPage * 5);
        }

        return min(99, (int) round(($nextPage / $totalPages) * 100));
    }

    /**
     * Ensure catalog items include the supplier's source currency price for admin display.
     *
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array<string, mixed>>
     */
    public function enrichCatalogSourcePrices(array $items, SupplierApi $supplier): array
    {
        $sourceCurrency = strtoupper(trim((string) ($supplier->settings['source_currency'] ?? '')));

        if ($sourceCurrency === '' || $sourceCurrency === 'USD') {
            return $items;
        }

        $converter = app(SupplierCurrencyConverter::class);

        return array_map(function (array $item) use ($sourceCurrency, $converter): array {
            if (isset($item['source_price']) && $item['source_price'] !== '') {
                $item['source_currency'] = $item['source_currency'] ?? $sourceCurrency;

                return $item;
            }

            $displayCurrency = strtoupper(trim((string) ($item['currency'] ?? 'USD')));
            $displayPrice = (float) ($item['price'] ?? 0);

            if ($displayPrice > 0 && $displayCurrency === 'USD') {
                $item['source_price'] = $converter->fromUsd($displayPrice, $sourceCurrency);
                $item['source_currency'] = $sourceCurrency;
            }

            return $item;
        }, $items);
    }

    /**
     * @param  SupplierProductDTO[]  $products
     * @return array<int, array<string, mixed>>
     */
    private function mapProductsToCatalog(array $products, ?SupplierApi $supplier = null): array
    {
        $sourceCurrency = strtoupper(trim((string) ($supplier?->settings['source_currency'] ?? '')));
        $sourcePriceField = (string) ($supplier?->settings['product_price_field'] ?? 'price');

        return collect($products)->map(function (SupplierProductDTO $product) use ($sourceCurrency, $sourcePriceField): array {
            $entry = [
                'id' => $product->supplierProductId,
                'name' => $product->name,
                'price' => $product->price,
                'currency' => $product->currency,
                'stock' => $product->stockAvailable,
                'region' => $product->region,
                'image' => $product->imageUrl,
            ];

            if ($sourceCurrency !== '' && $sourceCurrency !== 'USD') {
                $rawPrice = data_get($product->rawData, $sourcePriceField);

                if ($rawPrice !== null && $rawPrice !== '') {
                    $entry['source_price'] = (float) $rawPrice;
                    $entry['source_currency'] = $sourceCurrency;
                }
            }

            return $entry;
        })->values()->all();
    }
}
