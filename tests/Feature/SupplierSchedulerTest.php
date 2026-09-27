<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\TestCase;

class SupplierSchedulerTest extends TestCase
{
    /** @return string[] */
    private function forbiddenSupplierAutoRestockTokens(): array
    {
        return [
            'auto_restock',
            'AutoRestock',
            'enable_auto_restock',
            'min_stock_threshold',
            'max_restock_qty',
            'SupplierStockSyncJob',
        ];
    }

    public function test_supplier_auto_fetch_jobs_are_not_scheduled(): void
    {
        Artisan::call('schedule:list');
        $output = Artisan::output();

        foreach ($this->forbiddenSupplierAutoRestockTokens() as $token) {
            $this->assertStringNotContainsString($token, $output, 'schedule:list must not reference supplier auto-restock');
        }

        $this->assertStringContainsString('SupplierHealthCheckJob', $output);
        $this->assertStringContainsString('SyncSupplierMappingPricesJob', $output);
    }

    public function test_console_schedule_does_not_reference_auto_restock_or_stock_sync_job(): void
    {
        $console = file_get_contents(base_path('routes/console.php')) ?: '';

        foreach ($this->forbiddenSupplierAutoRestockTokens() as $token) {
            $this->assertStringNotContainsString($token, $console, 'routes/console.php must not reference supplier auto-restock');
        }

        $this->assertStringNotContainsString('everyFifteenMinutes', $console);
    }

    public function test_supplier_stock_sync_job_class_is_removed(): void
    {
        $this->assertFileDoesNotExist(app_path('Jobs/SupplierStockSyncJob.php'));
    }

    public function test_jobs_do_not_reference_supplier_auto_restock(): void
    {
        foreach (glob(app_path('Jobs/*.php')) ?: [] as $path) {
            $this->assertFileDoesNotContainSupplierAutoRestockTokens($path);
        }
    }

    public function test_console_commands_do_not_reference_supplier_auto_restock(): void
    {
        $commandsRoot = app_path('Console/Commands');

        if (! is_dir($commandsRoot)) {
            $this->fail('Console commands directory missing');
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($commandsRoot, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $this->assertFileDoesNotContainSupplierAutoRestockTokens($file->getPathname());
        }
    }

    public function test_scheduled_supplier_mapping_prices_job_does_not_purchase_inventory(): void
    {
        $source = file_get_contents(app_path('Jobs/SyncSupplierMappingPricesJob.php')) ?: '';

        $this->assertStringContainsString('syncStock', $source);
        $this->assertStringNotContainsString('fetchAndStockCodes', $source);
        $this->assertStringNotContainsString('placeSupplierOrder', $source);
        $this->assertStringNotContainsString('fulfillOrder', $source);
    }

    private function assertFileDoesNotContainSupplierAutoRestockTokens(string $path): void
    {
        $contents = file_get_contents($path) ?: '';

        foreach ($this->forbiddenSupplierAutoRestockTokens() as $token) {
            $this->assertStringNotContainsString(
                $token,
                $contents,
                "Forbidden supplier auto-restock token [{$token}] in {$path}"
            );
        }
    }
}
