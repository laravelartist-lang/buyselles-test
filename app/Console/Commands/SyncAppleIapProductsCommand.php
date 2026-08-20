<?php

namespace App\Console\Commands;

use App\Services\Apple\AppleIapCatalogSyncService;
use App\Services\Apple\AppleIapPriceCalculator;
use Illuminate\Console\Command;
use InvalidArgumentException;

class SyncAppleIapProductsCommand extends Command
{
    protected $signature = 'apple:iap-sync
                            {--percent= : Add this percentage markup on the Buyselles price before choosing the Apple tier (example: 15)}
                            {--fixed-usd= : Add this fixed USD amount instead of percentage (example: 1.99)}
                            {--product-id=* : Sync only specific Buyselles product IDs}
                            {--limit= : Maximum number of digital products to process}
                            {--dry-run : Preview pricing and actions without calling Apple}
                            {--update-prices : Update App Store price tiers for products that already exist}';

    protected $description = 'Create or update App Store consumable IAP products for all active digital Buyselles products';

    public function __construct(
        private readonly AppleIapCatalogSyncService $syncService,
        private readonly AppleIapPriceCalculator $priceCalculator,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        try {
            $percentMarkup = $this->nullableFloatOption('percent');
            $fixedUsdMarkup = $this->nullableFloatOption('fixed-usd');
            $this->priceCalculator->assertMarkupOptions($percentMarkup, $fixedUsdMarkup);
        } catch (InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $productIds = collect($this->option('product-id'))
            ->filter(fn ($value): bool => $value !== null && $value !== '')
            ->map(fn ($value): int => (int) $value)
            ->values()
            ->all();

        $limit = $this->option('limit') !== null ? max(1, (int) $this->option('limit')) : null;
        $dryRun = (bool) $this->option('dry-run');
        $updatePrices = (bool) $this->option('update-prices');

        $this->info('Syncing digital products to App Store Connect...');
        $this->line('Pricing source: Buyselles product unit_price converted to USD via BackEndHelper::currency_to_usd()');

        if ($percentMarkup !== null) {
            $this->line('Markup mode: +'.$percentMarkup.'% on Buyselles price');
        } elseif ($fixedUsdMarkup !== null) {
            $this->line('Markup mode: +$'.number_format($fixedUsdMarkup, 2).' USD fixed');
        } else {
            $this->line('Markup mode: none (use Buyselles USD price as-is, rounded up to nearest Apple tier)');
        }

        if ($dryRun) {
            $this->warn('Dry run enabled — no Apple API writes will be performed.');
        }

        if (! $this->priceCalculator->canConvertCurrency()) {
            $this->warn(
                'Currency tables are unavailable on this database — using products.unit_price as USD. '.
                'Run migrations and php artisan cache:clear for accurate multi-currency conversion.'
            );
        }

        $this->priceCalculator->resetCurrencyFallbackFlag();

        try {
            $result = $this->syncService->sync(
                productIds: $productIds === [] ? null : $productIds,
                percentMarkup: $percentMarkup,
                fixedUsdMarkup: $fixedUsdMarkup,
                dryRun: $dryRun,
                updatePrices: $updatePrices,
                limit: $limit,
                output: $this->output,
            );
        } catch (\Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $summary = $result['summary'];

        $this->newLine();
        $this->table(
            ['Metric', 'Count'],
            collect($summary)->map(fn ($count, $key) => [$key, $count])->values()->all(),
        );

        if (($summary['failed'] ?? 0) > 0) {
            return self::FAILURE;
        }

        if ($this->priceCalculator->currencyFallbackWasUsed()) {
            $this->warn('Some prices used unit_price directly because currency conversion was unavailable.');
        }

        $this->info('Apple IAP sync finished.');

        return self::SUCCESS;
    }

    private function nullableFloatOption(string $name): ?float
    {
        $value = $this->option($name);

        if ($value === null || $value === '') {
            return null;
        }

        if (! is_numeric($value)) {
            throw new InvalidArgumentException('The --'.$name.' option must be numeric.');
        }

        return (float) $value;
    }
}
