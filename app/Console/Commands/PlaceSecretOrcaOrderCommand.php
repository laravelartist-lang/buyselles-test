<?php

namespace App\Console\Commands;

use App\Models\SupplierApi;
use App\Models\SupplierProductMapping;
use App\Services\DirectTopUp\DirectTopUpService;
use App\Services\Supplier\Drivers\GenericRestDriver;
use App\Services\Supplier\Presets\SecretOrcaPreset;
use App\Services\Supplier\SupplierManager;
use Illuminate\Console\Command;

class PlaceSecretOrcaOrderCommand extends Command
{
    protected $signature = 'secretorca:place-order
                            {--supplier= : Supplier API ID (defaults to active SecretOrca)}
                            {--mapping= : Supplier product mapping ID (resolves product_id + region)}
                            {--product-id= : Secret Orca product UUID (overrides mapping)}
                            {--quantity= : Amount to send (defaults to mapping bundle quantity or 900)}
                            {--account= : target_account / Player ID (required)}
                            {--region= : Region code e.g. EG, SA, AE, PK (defaults to mapping direct_topup_region)}
                            {--client-order-id= : Optional client_order_id for your tracking}
                            {--poll : Poll order status until completed or failed}
                            {--repair-settings : Re-apply Secret Orca preset settings before placing}';

    protected $description = 'Place a direct top-up order against the Secret Orca API (sandbox or live)';

    public function handle(SupplierManager $supplierManager): int
    {
        $account = trim((string) $this->option('account'));

        if ($account === '') {
            $this->error('Pass --account= with the recipient target_account (Player ID).');

            return self::FAILURE;
        }

        $supplier = $this->resolveSupplier();

        if ($supplier === null) {
            $this->error('No active SecretOrca supplier found. Run: php artisan secretorca:connect');

            return self::FAILURE;
        }

        if ($this->option('repair-settings')) {
            $this->repairSettings($supplier);
        }

        $mapping = $this->resolveMapping($supplier);

        $productId = trim((string) ($this->option('product-id') ?: $mapping?->supplier_product_id ?: ''));

        if ($productId === '') {
            $this->error('Pass --product-id= or --mapping= with a mapped Secret Orca product.');

            return self::FAILURE;
        }

        $quantityInput = $this->option('quantity');
        $quantity = $quantityInput !== null && $quantityInput !== ''
            ? (float) $quantityInput
            : $this->resolveDefaultQuantity($mapping);
        $region = strtoupper(trim((string) ($this->option('region')
            ?: $mapping?->direct_topup_region
            ?: '')));

        $this->info('Secret Orca — Place Order');
        $this->line(str_repeat('─', 60));
        $this->line('Supplier     : '.$supplier->name.' (#'.$supplier->id.')');
        $this->line('Product ID   : '.$productId);
        $this->line('Quantity     : '.$quantity);
        $this->line('Account      : '.$account);
        $this->line('Region       : '.($region !== '' ? $region : '(not sent)'));
        $this->newLine();

        if ($region === '' && $mapping !== null) {
            $this->warn('No region set. Set direct_topup_region on mapping #'.$mapping->id.' or pass --region=EG');
        }

        try {
            $driver = $supplierManager->driver($supplier);

            if ($driver instanceof GenericRestDriver) {
                $driver->setTopUpPayloadExtras(array_filter([
                    'region' => $region !== '' ? $region : null,
                    'idempotency_key' => 'cli-'.uniqid('', true),
                    'client_order_id' => $this->option('client-order-id') ?: 'cli-'.time(),
                ]));
            }

            $result = $driver->placeTopUpOrder(
                supplierProductId: $productId,
                quantity: $quantity,
                accountId: $account,
            );

            if ($driver instanceof GenericRestDriver) {
                $driver->clearTopUpPayloadExtras();
            }

            $orderNumber = $result->supplierOrderId;

            $this->info('Order placed successfully.');
            $this->line('  order_number : '.$orderNumber);
            $this->line('  status       : '.$result->status);
            $this->line('  total_cost   : '.(string) data_get($result->rawResponse, 'total_cost', 'n/a'));
            $this->newLine();

            if ($this->option('poll') && $orderNumber !== '') {
                return $this->pollUntilDone($supplierManager, $supplier, $orderNumber);
            }

            $this->comment('Poll status: php artisan secretorca:place-order --poll ... (re-run with same order)');
            $this->comment('Or: php artisan secretorca:poll-order '.$orderNumber);

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Place order failed: '.$e->getMessage());

            return self::FAILURE;
        }
    }

    private function pollUntilDone(SupplierManager $supplierManager, SupplierApi $supplier, string $orderNumber): int
    {
        $this->info('Polling order status…');

        $driver = $supplierManager->driver($supplier);
        $attempts = 0;
        $maxAttempts = 15;

        while ($attempts < $maxAttempts) {
            $attempts++;
            sleep(min(2 * $attempts, 10));

            try {
                $status = $driver->getOrderStatus($orderNumber);
            } catch (\Throwable $e) {
                $this->warn("Attempt {$attempts}: ".$e->getMessage());

                if (str_contains($e->getMessage(), '404') && ! $this->hasOrderStatusEndpoint($supplier)) {
                    $this->error('order_status_endpoint missing on supplier. Run: php artisan secretorca:connect --repair-settings');
                    $this->comment('Or: php artisan secretorca:place-order --repair-settings ...');

                    return self::FAILURE;
                }

                continue;
            }

            $this->line("Attempt {$attempts}: status={$status->status}");

            if ($status->status === 'fulfilled') {
                $this->info('Order completed.');
                $this->line(json_encode($status->rawResponse, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

                return self::SUCCESS;
            }

            if ($status->status === 'failed') {
                $this->error('Order failed on supplier side.');
                $this->line(json_encode($status->rawResponse, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

                return self::FAILURE;
            }
        }

        $this->warn('Still pending after '.$maxAttempts.' polls. Check again later.');

        return self::SUCCESS;
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

    private function resolveMapping(SupplierApi $supplier): ?SupplierProductMapping
    {
        if (! $this->option('mapping')) {
            return null;
        }

        return SupplierProductMapping::query()
            ->where('id', (int) $this->option('mapping'))
            ->where('supplier_api_id', $supplier->id)
            ->where('is_active', true)
            ->with('product')
            ->first();
    }

    private function resolveDefaultQuantity(?SupplierProductMapping $mapping): float
    {
        if ($mapping !== null) {
            $mapping->loadMissing('product');

            if ($mapping->product !== null) {
                return app(DirectTopUpService::class)->resolveBundleQuantity($mapping->product);
            }

            if ($mapping->direct_topup_bundle_quantity !== null && (float) $mapping->direct_topup_bundle_quantity > 0) {
                return (float) $mapping->direct_topup_bundle_quantity;
            }
        }

        return 900;
    }

    private function repairSettings(SupplierApi $supplier): void
    {
        $supplier->settings = array_merge($supplier->settings ?? [], SecretOrcaPreset::settings());
        $supplier->supports_direct_top_up = true;
        $supplier->save();

        $this->info('Re-applied Secret Orca preset settings (including order_status_endpoint).');
    }

    private function hasOrderStatusEndpoint(SupplierApi $supplier): bool
    {
        $endpoint = $supplier->settings['order_status_endpoint'] ?? null;

        return is_string($endpoint) && $endpoint !== '';
    }
}
