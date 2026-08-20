<?php

namespace App\Services\Apple;

use App\Models\Product;
use Illuminate\Console\OutputStyle;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use RuntimeException;

class AppleIapCatalogSyncService
{
    public function __construct(
        private readonly AppStoreConnectClient $client,
        private readonly AppleIapPriceCalculator $priceCalculator,
        private readonly AppleIapPricePointResolver $pricePointResolver,
    ) {}

    /**
     * @param  array<int, int|string>|null  $productIds
     * @return array{
     *     summary: array<string, int>,
     *     rows: array<int, array<string, mixed>>
     * }
     */
    public function sync(
        ?array $productIds,
        ?float $percentMarkup,
        ?float $fixedUsdMarkup,
        bool $dryRun,
        bool $updatePrices,
        ?int $limit,
        ?OutputStyle $output = null,
    ): array {
        $this->priceCalculator->assertMarkupOptions($percentMarkup, $fixedUsdMarkup);

        if (! $dryRun && ! $this->client->isConfigured()) {
            throw new RuntimeException(
                'App Store Connect credentials are missing. Set APP_STORE_CONNECT_KEY_ID, APP_STORE_CONNECT_ISSUER_ID, and APP_STORE_CONNECT_API_KEY.'
            );
        }

        $products = $this->digitalProductsQuery($productIds, $limit)->get();

        if ($products->isEmpty()) {
            return [
                'summary' => [
                    'processed' => 0,
                    'created' => 0,
                    'existing' => 0,
                    'priced' => 0,
                    'db_updated' => 0,
                    'skipped' => 0,
                    'failed' => 0,
                ],
                'rows' => [],
            ];
        }

        $appId = null;
        $existingByProductId = [];

        if (! $dryRun) {
            $bundleId = (string) config('apple_app_store.bundle_id');
            $appId = $this->client->getAppIdByBundleId($bundleId);

            if ($appId === null) {
                throw new RuntimeException('No App Store Connect app found for bundle ID '.$bundleId.'.');
            }

            $existingByProductId = $this->client->listInAppPurchaseIdsForApp($appId);
        }

        $summary = [
            'processed' => 0,
            'created' => 0,
            'existing' => 0,
            'priced' => 0,
            'db_updated' => 0,
            'skipped' => 0,
            'failed' => 0,
        ];
        $rows = [];

        foreach ($products as $product) {
            $summary['processed']++;

            try {
                $row = $this->syncProduct(
                    product: $product,
                    appId: $appId,
                    existingByProductId: $existingByProductId,
                    percentMarkup: $percentMarkup,
                    fixedUsdMarkup: $fixedUsdMarkup,
                    dryRun: $dryRun,
                    updatePrices: $updatePrices,
                );

                $rows[] = $row;

                match ($row['result']) {
                    'created' => $summary['created']++,
                    'existing' => $summary['existing']++,
                    'priced' => $summary['priced']++,
                    'skipped' => $summary['skipped']++,
                    default => null,
                };

                if (($row['db_updated'] ?? false) === true) {
                    $summary['db_updated']++;
                }

                if ($output !== null) {
                    $output->writeln(sprintf(
                        '[%s] #%d %s → %s | Buyselles $%0.2f → target $%0.2f → Apple $%0.2f (%s)',
                        strtoupper($row['result']),
                        $product->id,
                        Str::limit($this->rawProductName($product), 40),
                        $row['apple_product_id'],
                        $row['buyselles_price_usd'],
                        $row['target_usd'],
                        $row['apple_tier_usd'] ?? 0,
                        $row['message'],
                    ));
                }
            } catch (\Throwable $exception) {
                $summary['failed']++;
                $rows[] = [
                    'result' => 'failed',
                    'product_id' => $product->id,
                    'product_name' => $this->rawProductName($product),
                    'digital_product_type' => $product->digital_product_type,
                    'apple_product_id' => $this->resolveAppleProductId($product),
                    'message' => $exception->getMessage(),
                ];

                if ($output !== null) {
                    $output->error(sprintf(
                        '[FAILED] #%d %s: %s',
                        $product->id,
                        Str::limit($this->rawProductName($product), 40),
                        $exception->getMessage(),
                    ));
                }
            }
        }

        return compact('summary', 'rows');
    }

    /**
     * @param  array<string, string>  $existingByProductId
     * @return array<string, mixed>
     */
    private function syncProduct(
        Product $product,
        ?string $appId,
        array $existingByProductId,
        ?float $percentMarkup,
        ?float $fixedUsdMarkup,
        bool $dryRun,
        bool $updatePrices,
    ): array {
        $appleProductId = $this->resolveAppleProductId($product);
        $pricing = $this->priceCalculator->calculateForProduct($product, $percentMarkup, $fixedUsdMarkup);
        $referenceName = $this->referenceName($product);
        $displayName = $this->displayName($product);
        $description = $this->description($product);
        $locale = (string) config('apple_app_store.locale');
        $territory = (string) config('apple_app_store.territory');
        $reviewNote = (string) config('apple_app_store.review_note');

        $iapId = $existingByProductId[$appleProductId] ?? null;
        $created = false;

        if ($iapId === null) {
            if ($dryRun) {
                return [
                    'result' => 'created',
                    'product_id' => $product->id,
                    'product_name' => $this->rawProductName($product),
                    'digital_product_type' => $product->digital_product_type,
                    'apple_product_id' => $appleProductId,
                    'buyselles_price_usd' => $pricing['buyselles_price_usd'],
                    'target_usd' => $pricing['target_usd'],
                    'apple_tier_usd' => null,
                    'message' => 'dry-run create + price',
                ];
            }

            $createdIap = $this->client->createConsumableInAppPurchase(
                appId: (string) $appId,
                productId: $appleProductId,
                referenceName: $referenceName,
                reviewNote: $reviewNote,
            );
            $iapId = $createdIap['id'];
            $created = true;

            $version = $this->client->createInAppPurchaseVersion($iapId);
            $this->client->createInAppPurchaseLocalization(
                versionId: $version['id'],
                locale: $locale,
                name: $displayName,
                description: $description,
            );
        } elseif ($dryRun && ! $updatePrices) {
            return [
                'result' => 'existing',
                'product_id' => $product->id,
                'product_name' => $this->rawProductName($product),
                'digital_product_type' => $product->digital_product_type,
                'apple_product_id' => $appleProductId,
                'buyselles_price_usd' => $pricing['buyselles_price_usd'],
                'target_usd' => $pricing['target_usd'],
                'apple_tier_usd' => null,
                'message' => 'dry-run existing only',
            ];
        }

        $appleTierUsd = null;
        $dbUpdated = false;

        if (! $dryRun && ($created || $updatePrices)) {
            $pricePoints = $this->client->listPricePointsForInAppPurchase($iapId, $territory);
            $selectedPoint = $this->pricePointResolver->resolveNearestTierAtOrAbove(
                $pricePoints,
                $pricing['target_usd'],
            );

            $this->client->setInAppPurchasePrice($iapId, $selectedPoint['id'], $territory);
            $appleTierUsd = $selectedPoint['customer_price'];
        }

        if (! $dryRun && trim((string) ($product->apple_product_id ?? '')) !== $appleProductId) {
            $product->forceFill(['apple_product_id' => $appleProductId])->save();
            $dbUpdated = true;
        }

        return [
            'result' => $created ? 'created' : ($updatePrices && ! $dryRun ? 'priced' : 'existing'),
            'product_id' => $product->id,
            'product_name' => $this->rawProductName($product),
            'digital_product_type' => $product->digital_product_type,
            'apple_product_id' => $appleProductId,
            'buyselles_price_usd' => $pricing['buyselles_price_usd'],
            'target_usd' => $pricing['target_usd'],
            'apple_tier_usd' => $appleTierUsd,
            'db_updated' => $dbUpdated,
            'message' => $created
                ? 'created on App Store Connect'
                : ($updatePrices ? 'price updated' : 'already exists'),
        ];
    }

    /**
     * @param  array<int, int|string>|null  $productIds
     * @return Builder<Product>
     */
    public function digitalProductsQuery(?array $productIds, ?int $limit): Builder
    {
        return Product::query()
            ->withoutGlobalScopes()
            ->select([
                'id',
                'name',
                'details',
                'unit_price',
                'product_type',
                'digital_product_type',
                'apple_product_id',
                'status',
                'request_status',
            ])
            ->where('product_type', 'digital')
            ->where('status', 1)
            ->where('request_status', 1)
            ->when($productIds !== null && $productIds !== [], fn (Builder $query) => $query->whereIn('id', $productIds))
            ->orderBy('id')
            ->when($limit !== null, fn (Builder $query) => $query->limit(max(1, $limit)));
    }

    public function resolveAppleProductId(Product $product): string
    {
        $custom = trim((string) ($product->apple_product_id ?? ''));

        if ($custom !== '') {
            return $custom;
        }

        return (string) config('apple_app_store.product_id_prefix').$product->id;
    }

    private function referenceName(Product $product): string
    {
        return Str::limit('Buyselles #'.$product->id.' '.$this->rawProductName($product), 64, '');
    }

    private function displayName(Product $product): string
    {
        return Str::limit($this->rawProductName($product), 30, '');
    }

    private function description(Product $product): string
    {
        $details = trim(strip_tags($this->rawProductDetails($product)));

        if ($details === '') {
            $details = 'Digital product for Buyselles.';
        }

        return Str::limit($details, 45, '');
    }

    private function rawProductName(Product $product): string
    {
        return (string) ($product->getAttributes()['name'] ?? '');
    }

    private function rawProductDetails(Product $product): string
    {
        return (string) ($product->getAttributes()['details'] ?? '');
    }
}
