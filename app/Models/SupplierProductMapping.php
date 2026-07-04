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
 * @property bool $auto_restock
 * @property int $min_stock_threshold
 * @property int $max_restock_qty
 * @property bool $is_active
 * @property bool $is_customizable
 * @property bool $is_direct_topup
 * @property string|null $direct_topup_account_label
 * @property float|null $direct_topup_min_quantity
 * @property float|null $direct_topup_max_quantity
 * @property float|null $direct_topup_price_per_unit
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
        'auto_restock',
        'min_stock_threshold',
        'max_restock_qty',
        'is_active',
        'is_customizable',
        'is_direct_topup',
        'direct_topup_account_label',
        'direct_topup_min_quantity',
        'direct_topup_max_quantity',
        'direct_topup_price_per_unit',
        'min_amount',
        'max_amount',
        'last_synced_at',
    ];

    protected function casts(): array
    {
        return [
            'product_id' => 'integer',
            'supplier_api_id' => 'integer',
            'cost_price' => 'decimal:2',
            'markup_value' => 'decimal:2',
            'priority' => 'integer',
            'auto_restock' => 'boolean',
            'min_stock_threshold' => 'integer',
            'max_restock_qty' => 'integer',
            'is_active' => 'boolean',
            'is_customizable' => 'boolean',
            'is_direct_topup' => 'boolean',
            'direct_topup_account_label' => 'string',
            'direct_topup_min_quantity' => 'decimal:4',
            'direct_topup_max_quantity' => 'decimal:4',
            'direct_topup_price_per_unit' => 'decimal:8',
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
        if ($this->markup_type === 'percent') {
            return round($this->cost_price * (1 + $this->markup_value / 100), 2);
        }

        return round($this->cost_price + $this->markup_value, 2);
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

    public function scopeAutoRestock($query)
    {
        return $query->where('auto_restock', true);
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
