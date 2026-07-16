<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PartnerCatalogDenominationPrice extends Model
{
    protected $fillable = [
        'partner_catalog_item_id',
        'supplier_product_denomination_id',
        'partner_price',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'partner_catalog_item_id' => 'integer',
            'supplier_product_denomination_id' => 'integer',
            'partner_price' => 'decimal:10',
            'is_active' => 'boolean',
        ];
    }

    public function catalogItem(): BelongsTo
    {
        return $this->belongsTo(PartnerCatalogItem::class, 'partner_catalog_item_id');
    }

    public function denomination(): BelongsTo
    {
        return $this->belongsTo(SupplierProductDenomination::class, 'supplier_product_denomination_id');
    }
}
