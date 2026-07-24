<?php

namespace App\Jobs;

use App\Services\Supplier\SupplierManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * @deprecated Use SyncSupplierMappingPricesJob. Kept for queued retries from older deployments.
 */
class SupplierStockSyncJob implements ShouldQueue
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
        (new SyncSupplierMappingPricesJob)->handle($manager);
    }
}
