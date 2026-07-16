<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PartnerCatalogItem extends Model
{
    public const VARIABLE_PRICE_PERCENT = 'percent';

    public const VARIABLE_PRICE_FLAT = 'flat';

    protected $fillable = [
        'partner_catalog_id',
        'product_id',
        'partner_price',
        'variable_price_type',
        'variable_price_value',
        'currency',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'partner_catalog_id' => 'integer',
            'product_id' => 'integer',
            'partner_price' => 'decimal:10',
            'variable_price_value' => 'decimal:10',
            'is_active' => 'boolean',
        ];
    }

    public function catalog(): BelongsTo
    {
        return $this->belongsTo(PartnerCatalog::class, 'partner_catalog_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)
            ->withoutGlobalScope(Product::STOREFRONT_SCOPE);
    }

    public function denominationPrices(): HasMany
    {
        return $this->hasMany(PartnerCatalogDenominationPrice::class);
    }

    public function activeDenominationPrices(): HasMany
    {
        return $this->denominationPrices()->where('is_active', true);
    }

    public function hasVariablePriceFormula(): bool
    {
        return in_array($this->variable_price_type, [
            self::VARIABLE_PRICE_PERCENT,
            self::VARIABLE_PRICE_FLAT,
        ], true) && $this->variable_price_value !== null;
    }
}
