<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class SupplierSchedulerTest extends TestCase
{
    public function test_supplier_auto_fetch_jobs_are_not_scheduled(): void
    {
        Artisan::call('schedule:list');
        $output = Artisan::output();

        $this->assertStringNotContainsString('SupplierStockSyncJob', $output);
        $this->assertStringContainsString('SupplierHealthCheckJob', $output);
        $this->assertStringContainsString('SyncSupplierMappingPricesJob', $output);
    }

    public function test_console_schedule_does_not_reference_auto_restock_or_stock_sync_job(): void
    {
        $console = file_get_contents(base_path('routes/console.php')) ?: '';

        $this->assertStringNotContainsString('SupplierStockSyncJob', $console);
        $this->assertStringNotContainsString('auto_restock', $console);
        $this->assertStringNotContainsString('everyFifteenMinutes', $console);
    }

    public function test_supplier_stock_sync_job_class_is_removed(): void
    {
        $this->assertFileDoesNotExist(app_path('Jobs/SupplierStockSyncJob.php'));
    }
}
