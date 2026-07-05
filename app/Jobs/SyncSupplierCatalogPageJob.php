<?php

namespace App\Jobs;

use App\Services\Supplier\SupplierCatalogSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Fetches a single catalog page and chains the next page job.
 *
 * Failures retry only the current page; earlier pages remain in the checkpoint cache.
 */
class SyncSupplierCatalogPageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    /** @var int[] */
    public array $backoff = [10, 30, 60, 120, 300];

    public int $timeout = 300;

    public function __construct(
        public readonly int $supplierId,
        public readonly int $pageIndex,
    ) {}

    public function handle(SupplierCatalogSyncService $syncService): void
    {
        $result = $syncService->syncPage($this->supplierId, $this->pageIndex);

        if ($result->hasMorePages) {
            self::dispatch($this->supplierId, $this->pageIndex + 1)
                ->delay(now()->addSeconds(2));

            return;
        }

        $syncService->markComplete($this->supplierId);
    }

    public function failed(?\Throwable $exception): void
    {
        app(SupplierCatalogSyncService::class)->markFailed(
            $this->supplierId,
            $this->pageIndex,
            $exception
        );
    }
}
