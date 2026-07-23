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
}
