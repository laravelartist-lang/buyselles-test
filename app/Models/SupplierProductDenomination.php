<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Class SupplierProductDenomination
 *
 * Represents a single denomination (fixed face value) or variable range
 * within a supplier product mapping.
 *
 * For FIXED denominations (e.g. Riot Access $10, $25, $50):
 *   - Each row is a separate Bamboo product with its own supplier_product_id
 *   - face_value = min_face_value = max_face_value
 *
 * For VARIABLE range (e.g. Apple UAE 50–5000 AED):
 *   - One row per mapping, min_face_value != max_face_value
 *   - Customer enters any amount within the range
 *
 * @property int $id
 * @property int $supplier_product_mapping_id
 * @property string $supplier_product_id
 * @property string|null $name
 * @property string $type fixed|variable
 * @property float|null $face_value
 * @property float|null $min_face_value
 * @property float|null $max_face_value
 * @property string $face_value_currency
 * @property float|null $cost_price
 * @property string|null $cost_currency
 * @property int|null $stock_available
 * @property bool $is_active
 * @property int $sort_order
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read SupplierProductMapping $mapping
 */
class SupplierProductDenomination extends Model
{
    protected $fillable = [
        'supplier_product_mapping_id',
        'supplier_product_id',
        'name',
        'type',
        'face_value',
        'min_face_value',
        'max_face_value',
        'face_value_currency',
        'cost_price',
        'cost_currency',
        'stock_available',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'supplier_product_mapping_id' => 'integer',
            'face_value' => 'decimal:2',
            'min_face_value' => 'decimal:2',
            'max_face_value' => 'decimal:2',
            'cost_price' => 'decimal:2',
            'stock_available' => 'integer',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    // ─── Relationships ───────────────────────────────────────────────────

    public function mapping(): BelongsTo
    {
        return $this->belongsTo(SupplierProductMapping::class, 'supplier_product_mapping_id');
    }

    // ─── Helpers ─────────────────────────────────────────────────────────

    public function isFixed(): bool
    {
        return $this->type === 'fixed';
    }

    public function isVariable(): bool
    {
        return $this->type === 'variable';
    }

    /**
     * Minimum selectable amount, matching storefront logic.
     */
    public function resolveMinimumAmount(?SupplierProductMapping $mapping = null): float
    {
        if ((float) ($this->min_face_value ?? 0) > 0) {
            return (float) $this->min_face_value;
        }

        $mapping ??= $this->relationLoaded('mapping') ? $this->mapping : $this->mapping()->first();

        return (float) ($mapping?->min_amount ?? 0);
    }

    /**
     * Maximum selectable amount, matching storefront logic.
     */
    public function resolveMaximumAmount(?SupplierProductMapping $mapping = null): float
    {
        if ((float) ($this->max_face_value ?? 0) > 0) {
            return (float) $this->max_face_value;
        }

        $mapping ??= $this->relationLoaded('mapping') ? $this->mapping : $this->mapping()->first();

        return (float) ($mapping?->max_amount ?? 0);
    }

    /**
     * Calculate the sell price for this denomination using the parent mapping's markup.
     * For fixed denominations, uses the face_value.
     * For variable denominations, requires passing the customer-chosen amount.
     */
    public function calculateSellPrice(?float $customAmount = null): float
    {
        $baseAmount = $this->isFixed() ? (float) $this->face_value : ($customAmount ?? 0.0);
        $mapping = $this->mapping;

        if ($mapping->markup_type === 'percent') {
            return round($baseAmount * (1 + $mapping->markup_value / 100), 2);
        }

        return round($baseAmount + (float) $mapping->markup_value, 2);
    }

    // ─── Scopes ──────────────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeFixed($query)
    {
        return $query->where('type', 'fixed');
    }

    public function scopeVariable($query)
    {
        return $query->where('type', 'variable');
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('face_value');
    }
}
