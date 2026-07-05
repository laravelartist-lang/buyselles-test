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

    /**
     * Prepare a sync run. When resuming, keeps accumulated products and next page.
     */
    public function beginSync(int $supplierId, bool $freshStart = false): int
    {
        $statusKey = self::statusCacheKey($supplierId);
        $checkpointKey = self::checkpointCacheKey($supplierId);

        if ($freshStart) {
            Cache::forget(self::catalogCacheKey($supplierId));
            Cache::forget($checkpointKey);
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
                'products' => [],
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
            'total_products' => count($checkpoint['products'] ?? []),
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
            'products' => [],
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
        $mappedProducts = $this->mapProductsToCatalog($products);

        $existingProducts = $checkpoint['products'] ?? [];
        $mergedProducts = array_merge($existingProducts, $mappedProducts);

        $totalPages = $this->resolveTotalPages($pageMeta, $config, $pageIndex, $hasMore);

        $nextPage = $hasMore ? $pageIndex + 1 : $pageIndex + 1;

        Cache::put($checkpointKey, [
            'next_page' => $nextPage,
            'total_pages' => $totalPages,
            'total_items' => $pageMeta['total'],
            'page_size' => $config['default_size'],
            'products' => $mergedProducts,
            'started_at' => $checkpoint['started_at'] ?? now()->toIso8601String(),
            'last_successful_page' => $pageIndex,
        ], now()->addHours(6));

        Cache::put(self::statusCacheKey($supplierId), [
            'state' => 'running',
            'progress' => $this->calculateProgress($pageIndex + 1, $totalPages),
            'next_page' => $nextPage,
            'pages_fetched' => $pageIndex + 1,
            'total_pages' => $totalPages,
            'total_products' => count($mergedProducts),
            'total_items' => $pageMeta['total'],
            'started_at' => $checkpoint['started_at'] ?? now()->toIso8601String(),
        ], now()->addHours(6));

        Log::info('SupplierCatalogSyncService: page fetched', [
            'supplier_id' => $supplierId,
            'driver' => $supplier->driver,
            'page' => $pageIndex,
            'items_on_page' => count($mappedProducts),
            'total_products' => count($mergedProducts),
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

    public function markComplete(int $supplierId): void
    {
        $checkpoint = Cache::get(self::checkpointCacheKey($supplierId), []);
        $catalog = $checkpoint['products'] ?? [];

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

        Cache::forget(self::checkpointCacheKey($supplierId));

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
            'total_products' => count($checkpoint['products'] ?? []),
            'total_pages' => $checkpoint['total_pages'] ?? null,
            'failed_at' => now()->toIso8601String(),
        ], now()->addHours(6));

        if (! empty($checkpoint['products'])) {
            Cache::put(self::checkpointCacheKey($supplierId), array_merge($checkpoint, [
                'next_page' => $failedPageIndex,
            ]), now()->addHours(6));
        }

        Log::error('SupplierCatalogSyncService: page failed', [
            'supplier_id' => $supplierId,
            'failed_page' => $failedPageIndex,
            'error' => $message,
            'saved_products' => count($checkpoint['products'] ?? []),
        ]);
    }

    public function hasResumableCheckpoint(int $supplierId): bool
    {
        $checkpoint = Cache::get(self::checkpointCacheKey($supplierId));

        return is_array($checkpoint)
            && (int) ($checkpoint['next_page'] ?? 0) >= 0
            && (
                ! empty($checkpoint['products'])
                || (int) ($checkpoint['next_page'] ?? 0) > 0
            );
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
     * @param  SupplierProductDTO[]  $products
     * @return array<int, array<string, mixed>>
     */
    private function mapProductsToCatalog(array $products): array
    {
        return collect($products)->map(fn (SupplierProductDTO $product) => [
            'id' => $product->supplierProductId,
            'name' => $product->name,
            'price' => $product->price,
            'currency' => $product->currency,
            'stock' => $product->stockAvailable,
            'region' => $product->region,
            'image' => $product->imageUrl,
        ])->values()->all();
    }
}
