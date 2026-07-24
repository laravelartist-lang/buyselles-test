<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Class SupplierProductMapping
 *
 * @property int $id
 * @property int $product_id
 * @property int $supplier_api_id
 * @property string $supplier_product_id
 * @property string|null $supplier_brand_id
 * @property string|null $supplier_brand_name
 * @property string|null $supplier_product_name
 * @property float $cost_price
 * @property string $cost_currency
 * @property string $markup_type percent|flat
 * @property float $markup_value
 * @property int $priority
 * @property string $code_source_priority local_first|supplier_first
 * @property bool $is_active
 * @property bool $is_customizable
 * @property bool $is_direct_topup
 * @property string|null $direct_topup_account_label
 * @property string|null $direct_topup_region
 * @property float|null $direct_topup_bundle_quantity
 * @property float|null $min_amount
 * @property float|null $max_amount
 * @property Carbon|null $last_synced_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Product $product
 * @property-read SupplierApi $supplierApi
 * @property-read \Illuminate\Database\Eloquent\Collection|SupplierProductDenomination[] $denominations
 */
class SupplierProductMapping extends Model
{
    public const CODE_SOURCE_LOCAL_FIRST = 'local_first';

    public const CODE_SOURCE_SUPPLIER_FIRST = 'supplier_first';

    protected $fillable = [
        'product_id',
        'supplier_api_id',
        'supplier_product_id',
        'supplier_brand_id',
        'supplier_brand_name',
        'supplier_product_name',
        'cost_price',
        'cost_currency',
        'markup_type',
        'markup_value',
        'priority',
        'code_source_priority',
        'is_active',
        'is_customizable',
        'is_direct_topup',
        'direct_topup_account_label',
        'direct_topup_region',
        'direct_topup_bundle_quantity',
        'min_amount',
        'max_amount',
        'last_synced_at',
    ];

    protected function casts(): array
    {
        return [
            'product_id' => 'integer',
            'supplier_api_id' => 'integer',
            'cost_price' => 'decimal:10',
            'markup_value' => 'decimal:2',
            'priority' => 'integer',
            'code_source_priority' => 'string',
            'is_active' => 'boolean',
            'is_customizable' => 'boolean',
            'is_direct_topup' => 'boolean',
            'direct_topup_account_label' => 'string',
            'direct_topup_region' => 'string',
            'direct_topup_bundle_quantity' => 'decimal:4',
            'min_amount' => 'decimal:2',
            'max_amount' => 'decimal:2',
            'last_synced_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    // ─── Relationships ───────────────────────────────────────────────────

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function supplierApi(): BelongsTo
    {
        return $this->belongsTo(SupplierApi::class);
    }

    public function denominations(): HasMany
    {
        return $this->hasMany(SupplierProductDenomination::class);
    }

    public function activeDenominations(): HasMany
    {
        return $this->denominations()->where('is_active', true)->orderBy('sort_order')->orderBy('face_value');
    }

    /**
     * Check if this mapping has any denominations (fixed or variable).
     */
    public function hasDenominations(): bool
    {
        return $this->denominations()->where('is_active', true)->exists();
    }

    /**
     * Get fixed denominations for this mapping.
     *
     * @return \Illuminate\Database\Eloquent\Collection<SupplierProductDenomination>
     */
    public function fixedDenominations(): HasMany
    {
        return $this->denominations()->where('type', 'fixed')->where('is_active', true)->orderBy('sort_order')->orderBy('face_value');
    }

    /**
     * Get the variable denomination for this mapping (typically at most one).
     */
    public function variableDenomination(): HasMany
    {
        return $this->denominations()->where('type', 'variable')->where('is_active', true);
    }

    // ─── Helpers ─────────────────────────────────────────────────────────

    public function isSupplierFirst(): bool
    {
        return $this->code_source_priority === self::CODE_SOURCE_SUPPLIER_FIRST;
    }

    public function isLocalFirst(): bool
    {
        return ! $this->isSupplierFirst();
    }

    /**
     * Whether the partner must choose a supplier denomination (and possibly custom amount)
     * before ordering. Matches storefront behavior: only required when variable amount
     * is enabled on the mapping or a variable denomination exists.
     */
    public function requiresDenominationSelection(): bool
    {
        if ($this->is_customizable) {
            return true;
        }

        if ($this->relationLoaded('activeDenominations')) {
            return $this->activeDenominations->contains(fn (SupplierProductDenomination $denomination): bool => $denomination->isVariable());
        }

        return $this->variableDenomination()->exists();
    }

    /**
     * @return array<int, string>
     */
    public function codeSourcePriorityOrder(): array
    {
        return $this->isSupplierFirst()
            ? ['supplier_api', 'manual']
            : ['manual', 'supplier_api'];
    }

    /**
     * Calculate the suggested sell price based on cost + markup.
     * For direct top-up products, flat markup is applied once per bundle (see calculateDirectTopUpBundlePrice).
     */
    public function calculateSellPrice(): float
    {
        $decimalPlaces = $this->resolveSellPriceDecimalPlaces();

        if ($this->markup_type === 'percent') {
            return round($this->cost_price * (1 + $this->markup_value / 100), $decimalPlaces);
        }

        if ($this->is_direct_topup) {
            return round((float) $this->cost_price, $decimalPlaces);
        }

        return round($this->cost_price + $this->markup_value, $decimalPlaces);
    }

    /**
     * Calculate the wholesale bundle cost (per-coin cost × quantity).
     */
    public function calculateDirectTopUpBundleCost(float $quantity): float
    {
        $quantity = max(0, $quantity);

        return round((float) $this->cost_price * $quantity, $this->resolveDisplayDecimalPlaces());
    }

    /**
     * Resolve the configured bundle quantity for direct top-up mappings.
     */
    public function resolveDirectTopUpBundleQuantity(): float
    {
        if ($this->direct_topup_bundle_quantity !== null) {
            $bundleQuantity = (float) $this->direct_topup_bundle_quantity;

            if ($bundleQuantity > 0) {
                return $bundleQuantity;
            }
        }

        return 0.0;
    }

    /**
     * Bundle cost shown in admin for direct top-up mappings.
     */
    public function getDirectTopUpAdminDisplayCost(): float
    {
        $quantity = $this->resolveDirectTopUpBundleQuantity();

        if ($quantity <= 0) {
            return round((float) $this->cost_price, $this->resolveDisplayDecimalPlaces());
        }

        return $this->calculateDirectTopUpBundleCost($quantity);
    }

    /**
     * Bundle sell price shown in admin for direct top-up mappings.
     */
    public function getDirectTopUpAdminDisplaySellPrice(): float
    {
        $quantity = $this->resolveDirectTopUpBundleQuantity();

        if ($quantity <= 0) {
            return $this->calculateSellPrice();
        }

        return $this->calculateDirectTopUpBundlePrice($quantity);
    }

    /**
     * Per-coin supplier cost for direct top-up breakdowns.
     */
    public function getDirectTopUpCostPerCoin(): float
    {
        return round((float) $this->cost_price, $this->resolveSellPriceDecimalPlaces());
    }

    /**
     * Calculate the customer-facing bundle price for direct top-up products.
     * Percent markup: (cost × (1 + markup%)) × quantity.
     * Flat markup: (cost × quantity) + flat (applied once per bundle, not per coin).
     */
    public function calculateDirectTopUpBundlePrice(float $quantity): float
    {
        $quantity = max(0, $quantity);
        $cost = (float) $this->cost_price;
        $baseTotal = $cost * $quantity;
        $decimalPlaces = $this->resolveDisplayDecimalPlaces();

        if ($this->markup_type === 'percent') {
            return round($baseTotal * (1 + $this->markup_value / 100), $decimalPlaces);
        }

        return round($baseTotal + (float) $this->markup_value, $decimalPlaces);
    }

    public function resolvePriceDecimalPlaces(): int
    {
        if ($this->is_direct_topup && (float) $this->cost_price > 0 && (float) $this->cost_price < 1) {
            return 10;
        }

        return 2;
    }

    /**
     * Customer-facing prices always use the store's configured decimal setting (typically 2).
     */
    public function resolveDisplayDecimalPlaces(): int
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('business_settings')) {
            return 2;
        }

        return (int) (getWebConfig('decimal_point_settings') ?? 2);
    }

    private function resolveSellPriceDecimalPlaces(): int
    {
        return $this->resolvePriceDecimalPlaces();
    }

    /**
     * Price shown on listings and PDP before the customer picks an amount.
     * For customizable products this uses the minimum selectable amount (or first fixed denomination).
     */
    public function getStartingDisplayPrice(): float
    {
        if ($this->is_customizable) {
            if ($this->relationLoaded('activeDenominations')) {
                $firstFixed = $this->activeDenominations->firstWhere('type', 'fixed');

                if ($firstFixed) {
                    return $firstFixed->calculateSellPrice();
                }

                $variable = $this->activeDenominations->firstWhere('type', 'variable');

                if ($variable) {
                    return $this->resolveVariableStartingPrice($variable);
                }
            } else {
                $firstFixed = $this->fixedDenominations()->first();

                if ($firstFixed) {
                    return $firstFixed->calculateSellPrice();
                }

                $variable = $this->variableDenomination()->first();

                if ($variable) {
                    return $this->resolveVariableStartingPrice($variable);
                }
            }

            if ($this->min_amount !== null && (float) $this->min_amount > 0) {
                return (float) $this->min_amount;
            }
        }

        if ($this->is_direct_topup) {
            $bundleQuantity = $this->resolveDirectTopUpBundleQuantity();

            if ($bundleQuantity > 0) {
                return $this->calculateDirectTopUpBundlePrice($bundleQuantity);
            }

            $costPerCoin = $this->getDirectTopUpCostPerCoin();

            return $costPerCoin > 0 ? $costPerCoin : (float) ($this->min_amount ?? 0);
        }

        $sellPrice = $this->calculateSellPrice();

        return $sellPrice > 0 ? $sellPrice : (float) ($this->min_amount ?? 0);
    }

    private function resolveVariableStartingPrice(SupplierProductDenomination $variable): float
    {
        $min = (float) ($variable->min_face_value ?: $this->min_amount ?: 0);

        if ($min <= 0) {
            return 0.0;
        }

        // Variable/custom amounts are stored and charged as the customer-facing sell price.
        return $min;
    }

    /**
     * Resolve the card face value sent to the supplier when placing an order.
     *
     * Wholesale cost_price is never used here — suppliers such as Bamboo require the
     * denomination (e.g. a $10 card), not what we pay the supplier.
     */
    public function resolveSupplierFaceValue(?float $customAmount = null, ?SupplierProductDenomination $denomination = null): ?float
    {
        if ($denomination?->isFixed()) {
            $faceValue = (float) $denomination->face_value;

            return $faceValue > 0 ? $faceValue : null;
        }

        if ($denomination?->isVariable() && $customAmount !== null && $customAmount > 0) {
            return $customAmount;
        }

        if ($customAmount !== null && $customAmount > 0) {
            return $customAmount;
        }

        if ($this->min_amount !== null && (float) $this->min_amount > 0) {
            return (float) $this->min_amount;
        }

        return null;
    }

    /**
     * Calculate the sell price for a given custom amount using the mapping markup.
     * For customizable products, the customer's chosen amount replaces the fixed cost.
     */
    public function calculateCustomSellPrice(float $amount): float
    {
        if ($this->markup_type === 'percent') {
            return round($amount * (1 + $this->markup_value / 100), 2);
        }

        return round($amount + $this->markup_value, 2);
    }

    // ─── Scopes ──────────────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByPriority($query)
    {
        return $query->orderBy('priority', 'asc');
    }

    /**
     * Limit mappings to storefront (customer-facing) products only, excluding
     * products that are exclusively exposed through the partner API.
     */
    public function scopeStorefrontOnly($query)
    {
        return $query->whereHas('product', function ($productQuery): void {
            $productQuery->where(function ($inner): void {
                $inner->where('partner_api_only', false)
                    ->orWhereNull('partner_api_only');
            });
        });
    }

    // ─── Static helpers ──────────────────────────────────────────────────

    /**
     * Return true when at least one active mapping (via an active supplier)
     * exists for the given product.  Used to decide whether a zero local-code
     * count should be treated as "out of stock" for digital ready-products.
     */
    public static function hasActiveMapping(int $productId): bool
    {
        return static::query()
            ->where('product_id', $productId)
            ->where('is_active', true)
            ->whereHas('supplierApi', fn ($q) => $q->where('is_active', true))
            ->exists();
    }

    /**
     * True when the product has supplier mapping rows but none can fulfill
     * (inactive supplier API and/or supplier marked down).
     */
    public static function hasOnlyUnavailableSupplierMappings(int $productId): bool
    {
        $hasAnyActiveMappingRow = static::query()
            ->where('product_id', $productId)
            ->where('is_active', true)
            ->exists();

        if (! $hasAnyActiveMappingRow) {
            return false;
        }

        return ! static::hasActiveMapping($productId);
    }

    public static function primaryActiveMapping(int $productId): ?self
    {
        return static::query()
            ->where('product_id', $productId)
            ->where('is_active', true)
            ->whereHas('supplierApi', fn ($q) => $q->where('is_active', true))
            ->with('supplierApi')
            ->byPriority()
            ->first();
    }
}
