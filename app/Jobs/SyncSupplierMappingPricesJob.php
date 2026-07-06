<?php

namespace App\Jobs;

use App\Models\SupplierProductMapping;
use App\Services\DigitalProductCodeService;
use App\Services\Supplier\SupplierCurrencyConverter;
use App\Services\Supplier\SupplierManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Daily price sync job — refreshes mapped supplier cost prices from live API data.
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

    public function handle(
        SupplierManager $manager,
        DigitalProductCodeService $codeService,
        SupplierCurrencyConverter $converter,
    ): void {
        $mappings = SupplierProductMapping::query()
            ->active()
            ->whereHas('supplierApi', fn ($query) => $query->where('is_active', true))
            ->with('supplierApi')
            ->get();

        $updated = 0;
        $failed = 0;

        foreach ($mappings as $mapping) {
            try {
                $supplier = $mapping->supplierApi;

                if ($supplier->isDown()) {
                    continue;
                }

                $driver = $manager->driver($supplier);
                $stockResult = $driver->fetchStock($mapping->supplier_product_id);
                $sourceCurrency = strtoupper(trim((string) ($supplier->settings['source_currency'] ?? $stockResult->currency)));
                $rawPrice = (float) $stockResult->price;

                if ($sourceCurrency !== '' && $sourceCurrency !== 'USD') {
                    $costPrice = $converter->toUsd($rawPrice, $sourceCurrency);
                    $costCurrency = 'USD';
                } else {
                    $costPrice = $rawPrice;
                    $costCurrency = strtoupper(trim($stockResult->currency)) ?: 'USD';
                }

                if ($costPrice <= 0 || ($costPrice == $mapping->cost_price && $costCurrency === $mapping->cost_currency)) {
                    continue;
                }

                $mapping->update([
                    'cost_price' => $costPrice,
                    'cost_currency' => $costCurrency,
                ]);

                $codeService->applyApiPriceIfManualDepleted($mapping->product_id);
                $updated++;
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
            'updated' => $updated,
            'failed' => $failed,
        ]);
    }
}
