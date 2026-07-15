<?php

namespace App\Console\Commands;

use App\Models\SupplierApi;
use App\Services\Supplier\Presets\SecretOrcaPreset;
use App\Services\Supplier\SupplierManager;
use Illuminate\Console\Command;

class PollSecretOrcaOrderCommand extends Command
{
    protected $signature = 'secretorca:poll-order
                            {order_number : Secret Orca order_number e.g. SBX-40CC6EA37F8E or ORD-...}
                            {--supplier= : Supplier API ID (defaults to active SecretOrca)}
                            {--repair-settings : Re-apply preset settings before polling}';

    protected $description = 'Poll a Secret Orca order status by order_number';

    public function handle(SupplierManager $supplierManager): int
    {
        $supplier = $this->resolveSupplier();

        if ($supplier === null) {
            $this->error('No active SecretOrca supplier found.');

            return self::FAILURE;
        }

        if ($this->option('repair-settings')) {
            $supplier->settings = array_merge($supplier->settings ?? [], SecretOrcaPreset::settings());
            $supplier->save();
            $this->info('Re-applied Secret Orca preset settings.');
        }

        $orderNumber = (string) $this->argument('order_number');

        try {
            $result = $supplierManager->driver($supplier)->getOrderStatus($orderNumber);

            $this->info('Order: '.$orderNumber);
            $this->line('Status: '.$result->status);
            $this->newLine();
            $this->line(json_encode($result->rawResponse, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            if (str_contains($e->getMessage(), '404')) {
                $this->newLine();
                $this->comment('If you see 404, run: php artisan secretorca:connect');
                $this->comment('Missing setting: order_status_endpoint = /api/v1/external/orders/{order_id}/');
            }

            return self::FAILURE;
        }
    }

    private function resolveSupplier(): ?SupplierApi
    {
        if ($this->option('supplier')) {
            return SupplierApi::query()
                ->where('id', (int) $this->option('supplier'))
                ->where('is_active', true)
                ->first();
        }

        return SupplierApi::query()
            ->where('name', SecretOrcaPreset::SUPPLIER_NAME)
            ->where('driver', 'generic_rest')
            ->where('is_active', true)
            ->first();
    }
}
