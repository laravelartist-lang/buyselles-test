<?php

namespace App\Console\Commands;

use App\Models\SupplierProductMapping;
use App\Services\Supplier\SupplierManager;
use Illuminate\Console\Command;

class RepairSupplierMappingCurrenciesCommand extends Command
{
    protected $signature = 'supplier:repair-mapping-currencies
                            {--supplier= : Limit repair to a supplier API ID}';

    protected $description = 'Convert supplier product mappings stored in JOD (or other currencies) into USD';

    public function handle(SupplierManager $manager): int
    {
        $query = SupplierProductMapping::query()
            ->with('supplierApi')
            ->where('cost_currency', '!=', 'USD')
            ->where('cost_price', '>', 0);

        if ($supplierId = $this->option('supplier')) {
            $query->where('supplier_api_id', (int) $supplierId);
        }

        $mappings = $query->get();

        if ($mappings->isEmpty()) {
            $this->info('No non-USD supplier mappings found.');

            return self::SUCCESS;
        }

        $repaired = 0;

        foreach ($mappings as $mapping) {
            $supplier = $mapping->supplierApi;

            if (! $supplier) {
                $this->warn("Skipping mapping {$mapping->id}: supplier missing.");

                continue;
            }

            if ($manager->normalizeLegacyMappingCost($mapping, $supplier)) {
                $mapping->refresh();
                $repaired++;
                $this->line("Mapping {$mapping->id}: {$mapping->supplier_product_name} → USD {$mapping->cost_price}");
            }
        }

        $this->info("Repaired {$repaired} of {$mappings->count()} non-USD mapping(s).");

        return self::SUCCESS;
    }
}
