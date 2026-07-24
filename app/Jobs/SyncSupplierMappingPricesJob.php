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
 * Daily price sync job — refreshes mapped supplier cost prices from live API data.
 * Uses SupplierManager::syncStock() so each read is recorded in supplier_api_logs.
 */
class SyncSupplierMappingPricesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 600;

    public function __construct()
    {
        $this->onQueue('slow');
    }

    public function handle(SupplierManager $manager): void
    {
        $mappings = SupplierProductMapping::query()
            ->storefrontOnly()
            ->active()
            ->whereHas('supplierApi', fn ($query) => $query->where('is_active', true))
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
                Log::error('SyncSupplierMappingPricesJob: mapping price sync failed', [
                    'mapping_id' => $mapping->id,
                    'product_id' => $mapping->product_id,
                    'supplier_id' => $mapping->supplier_api_id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info('SyncSupplierMappingPricesJob: completed', [
            'total' => $mappings->count(),
            'synced' => $synced,
            'failed' => $failed,
        ]);
    }
}
