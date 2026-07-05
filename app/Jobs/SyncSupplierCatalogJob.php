<?php

namespace App\Jobs;

use App\Services\Supplier\SupplierCatalogSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Starts (or resumes) a supplier catalog sync by dispatching the first page job.
 */
class SyncSupplierCatalogJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 60;

    public function __construct(
        public readonly int $supplierId,
        public readonly bool $freshStart = false,
    ) {}

    public function handle(SupplierCatalogSyncService $syncService): void
    {
        $startPage = $syncService->beginSync($this->supplierId, $this->freshStart);

        SyncSupplierCatalogPageJob::dispatch($this->supplierId, $startPage);
    }

    /** @deprecated Use SupplierCatalogSyncService::statusCacheKey() */
    public static function statusCacheKey(int $supplierId): string
    {
        return SupplierCatalogSyncService::statusCacheKey($supplierId);
    }

    /** @deprecated Use SupplierCatalogSyncService::catalogCacheKey() */
    public static function catalogCacheKey(int $supplierId): string
    {
        return SupplierCatalogSyncService::catalogCacheKey($supplierId);
    }
}
