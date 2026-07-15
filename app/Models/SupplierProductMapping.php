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
 * @property bool $is_active
 * @property bool $is_customizable
 * @property bool $is_direct_topup
 * @property string|null $direct_topup_account_label
 * @property string|null $direct_topup_region
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
        'is_active',
        'is_customizable',
        'is_direct_topup',
        'direct_topup_account_label',
        'direct_topup_region',
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
            'is_active' => 'boolean',
            'is_customizable' => 'boolean',
            'is_direct_topup' => 'boolean',
            'direct_topup_account_label' => 'string',
            'direct_topup_region' => 'string',
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

    /**
     * Calculate the suggested sell price based on cost + markup.
     */
    public function calculateSellPrice(): float
    {
        $decimalPlaces = $this->resolveSellPriceDecimalPlaces();

        if ($this->markup_type === 'percent') {
            return round($this->cost_price * (1 + $this->markup_value / 100), $decimalPlaces);
        }

        return round($this->cost_price + $this->markup_value, $decimalPlaces);
    }

    public function resolvePriceDecimalPlaces(): int
    {
        if ($this->is_direct_topup && (float) $this->cost_price > 0 && (float) $this->cost_price < 0.01) {
            return 10;
        }

        return 2;
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
}
