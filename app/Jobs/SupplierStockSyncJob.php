<?php

namespace App\Jobs;

use App\Models\SupplierProductMapping;
use App\Services\Supplier\SupplierManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Periodic stock sync job — runs every 15 minutes via scheduler.
 *
 * For each active supplier-product mapping with auto_restock enabled:
 * 1. Checks remote stock availability via supplier API
 * 2. Updates cost price if it has changed
 * 3. Auto-restocks if local pool is below min_stock_threshold
 */
class SupplierStockSyncJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 600; // 10 minutes

    public function __construct()
    {
        $this->onQueue('slow');
    }

    public function handle(SupplierManager $manager): void
    {
        $mappings = SupplierProductMapping::query()
            ->active()
            ->with('supplierApi')
            ->get();

        $synced = 0;
        $failed = 0;

        foreach ($mappings as $mapping) {
            try {
                $manager->syncStock($mapping);
                $synced++;
            } catch (\Throwable $e) {
                $failed++;
                Log::error('SupplierStockSyncJob: mapping sync failed', [
                    'mapping_id' => $mapping->id,
                    'product_id' => $mapping->product_id,
                    'supplier_id' => $mapping->supplier_api_id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info('SupplierStockSyncJob: completed', [
            'total' => $mappings->count(),
            'synced' => $synced,
            'failed' => $failed,
        ]);
    }
}
