<?php

namespace App\Jobs;

use App\Models\SupplierProductDenomination;
use App\Models\SupplierProductMapping;
use App\Services\Supplier\SupplierManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Sync denominations for a supplier product mapping from the Bamboo catalog.
 *
 * For a given mapping, this job fetches the brand's products from the supplier API
 * and creates/updates SupplierProductDenomination records.
 *
 * - FIXED denomination: minFaceValue == maxFaceValue (each product = one denomination)
 * - VARIABLE range: minFaceValue != maxFaceValue (one product = one variable denomination)
 */
class SyncDenominationsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [30, 60, 120];

    public function __construct(
        public int $mappingId,
    ) {}

    public function handle(SupplierManager $manager): void
    {
        $mapping = SupplierProductMapping::with('supplierApi')->find($this->mappingId);

        if (! $mapping || ! $mapping->supplierApi || ! $mapping->supplierApi->is_active) {
            Log::warning('SyncDenominationsJob: mapping or supplier not found/active', [
                'mapping_id' => $this->mappingId,
            ]);

            return;
        }

        $supplier = $mapping->supplierApi;
        $syncService = app(\App\Services\Supplier\SupplierCatalogSyncService::class);

        $scopeFilters = [];
        if ($mapping->supplier_brand_id) {
            $scopeFilters['brand_id'] = $mapping->supplier_brand_id;
        } else {
            $scopeFilters['product_id'] = $mapping->supplier_product_id;
        }

        try {
            $dtos = $syncService->fetchScopedProducts($supplier, $scopeFilters);
        } catch (\Throwable $e) {
            Log::error('SyncDenominationsJob: failed to fetch products from supplier', [
                'mapping_id' => $this->mappingId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }

        if (empty($dtos)) {
            Log::info('SyncDenominationsJob: no products returned from supplier', [
                'mapping_id' => $this->mappingId,
            ]);

            return;
        }

        // Extract brand info from the first DTO's rawData
        $firstRaw = $dtos[0]->rawData ?? [];
        $brandId = (string) ($firstRaw['internalId'] ?? '');
        $brandName = (string) ($firstRaw['name'] ?? '');
        $brandCurrency = (string) ($firstRaw['currencyCode'] ?? 'USD');

        // Update mapping with brand info if not set, then re-fetch all brand products
        if ($brandId && ! $mapping->supplier_brand_id) {
            $mapping->update([
                'supplier_brand_id' => $brandId,
                'supplier_brand_name' => $brandName,
            ]);

            try {
                $brandDtos = $syncService->fetchScopedProducts($supplier, ['brand_id' => $brandId]);
                if (! empty($brandDtos)) {
                    $dtos = $brandDtos;
                    $firstRaw = $dtos[0]->rawData ?? $firstRaw;
                    $brandCurrency = (string) ($firstRaw['currencyCode'] ?? $brandCurrency);
                }
            } catch (\Throwable $e) {
                Log::warning('SyncDenominationsJob: brand re-fetch failed, using initial product data', [
                    'mapping_id' => $this->mappingId,
                    'brand_id' => $brandId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $hasVariable = false;
        $hasFixed = false;
        $syncedIds = [];

        foreach ($dtos as $dto) {
            $product = $dto->rawData['_product'] ?? [];
            $productId = (string) ($product['id'] ?? $dto->supplierProductId);
            $productName = (string) ($product['name'] ?? $dto->name);

            $minFace = (float) ($product['minFaceValue'] ?? 0);
            $maxFace = (float) ($product['maxFaceValue'] ?? 0);
            $isFixed = $minFace > 0 && $minFace === $maxFace;

            // Skip products that don't have denomination data (both face values are 0)
            if ($minFace <= 0 && $maxFace <= 0) {
                continue;
            }

            // Cost price from supplier (wholesale)
            $costMin = (float) ($product['price']['min'] ?? $minFace);
            $costCurrency = (string) ($product['price']['currencyCode'] ?? $brandCurrency);

            $stockCount = ($product['count'] ?? null) !== null ? (int) $product['count'] : null;

            $denomination = SupplierProductDenomination::updateOrCreate(
                [
                    'supplier_product_mapping_id' => $mapping->id,
                    'supplier_product_id' => $productId,
                ],
                [
                    'name' => $productName,
                    'type' => $isFixed ? 'fixed' : 'variable',
                    'face_value' => $isFixed ? $minFace : null,
                    'min_face_value' => $minFace,
                    'max_face_value' => $maxFace,
                    'face_value_currency' => $brandCurrency,
                    'cost_price' => $costMin,
                    'cost_currency' => $costCurrency,
                    'stock_available' => $stockCount,
                    'is_active' => ! ($product['isDeleted'] ?? false),
                    'sort_order' => $isFixed ? (int) $minFace : 0,
                ],
            );

            $syncedIds[] = $denomination->id;
            $isFixed ? $hasFixed = true : $hasVariable = true;
        }

        // Deactivate denominations that are no longer in the supplier catalog
        SupplierProductDenomination::where('supplier_product_mapping_id', $mapping->id)
            ->whereNotIn('id', $syncedIds)
            ->update(['is_active' => false]);

        // Update mapping's is_customizable based on whether it has variable denominations
        // and sync min/max amounts from the variable denomination
        if ($hasVariable) {
            $variableDenom = SupplierProductDenomination::where('supplier_product_mapping_id', $mapping->id)
                ->where('type', 'variable')
                ->where('is_active', true)
                ->first();

            if ($variableDenom && ($variableDenom->min_face_value > 0 || $variableDenom->max_face_value > 0)) {
                $mapping->update([
                    'is_customizable' => true,
                    'min_amount' => $variableDenom->min_face_value,
                    'max_amount' => $variableDenom->max_face_value,
                ]);
            }
        } elseif (! $hasFixed && ! $hasVariable) {
            // No real denominations exist — reset customizable state
            $mapping->update([
                'is_customizable' => false,
                'min_amount' => null,
                'max_amount' => null,
            ]);
        } elseif ($hasFixed && ! $hasVariable) {
            // Pure fixed denominations — no custom amount input needed
            $mapping->update([
                'is_customizable' => false,
                'min_amount' => null,
                'max_amount' => null,
            ]);
        }

        $mapping->update(['last_synced_at' => now()]);

        app(\App\Services\Supplier\MappedProductCacheService::class)
            ->bustForMapping($mapping->fresh(['supplierApi']));

        Log::info('SyncDenominationsJob: completed', [
            'mapping_id' => $mapping->id,
            'brand_id' => $brandId,
            'brand_name' => $brandName,
            'fixed_count' => SupplierProductDenomination::where('supplier_product_mapping_id', $mapping->id)->where('type', 'fixed')->where('is_active', true)->count(),
            'variable_count' => SupplierProductDenomination::where('supplier_product_mapping_id', $mapping->id)->where('type', 'variable')->where('is_active', true)->count(),
            'deactivated' => SupplierProductDenomination::where('supplier_product_mapping_id', $mapping->id)->where('is_active', false)->count(),
        ]);
    }
}
