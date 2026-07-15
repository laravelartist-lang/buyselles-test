<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;

/**
 * Class SupplierOrder
 *
 * @property int $id
 * @property int $supplier_api_id
 * @property int $supplier_product_mapping_id
 * @property int|null $order_id
 * @property int|null $order_detail_id
 * @property string|null $supplier_order_id
 * @property int $quantity
 * @property float $cost_per_unit
 * @property float $total_cost
 * @property string $cost_currency
 * @property string $status pending|processing|fulfilled|partial|failed|refunded
 * @property string|null $codes_received Encrypted JSON
 * @property Carbon|null $fulfilled_at
 * @property string|null $failed_reason
 * @property int $attempt_count
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read SupplierApi $supplierApi
 * @property-read SupplierProductMapping $productMapping
 * @property-read Order|null $order
 * @property-read OrderDetail|null $orderDetail
 */
class SupplierOrder extends Model
{
    protected $fillable = [
        'supplier_api_id',
        'supplier_product_mapping_id',
        'order_id',
        'order_detail_id',
        'supplier_order_id',
        'quantity',
        'cost_per_unit',
        'total_cost',
        'cost_currency',
        'status',
        'codes_received',
        'fulfilled_at',
        'failed_reason',
        'attempt_count',
    ];

    protected function casts(): array
    {
        return [
            'supplier_api_id' => 'integer',
            'supplier_product_mapping_id' => 'integer',
            'order_id' => 'integer',
            'order_detail_id' => 'integer',
            'quantity' => 'integer',
            'cost_per_unit' => 'decimal:10',
            'total_cost' => 'decimal:10',
            'attempt_count' => 'integer',
            'fulfilled_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    // ─── Relationships ───────────────────────────────────────────────────

    public function supplierApi(): BelongsTo
    {
        return $this->belongsTo(SupplierApi::class);
    }

    public function productMapping(): BelongsTo
    {
        return $this->belongsTo(SupplierProductMapping::class, 'supplier_product_mapping_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function orderDetail(): BelongsTo
    {
        return $this->belongsTo(OrderDetail::class);
    }

    // ─── Encrypted Codes Accessor ────────────────────────────────────────

    /**
     * Decrypt and return the received codes as an array.
     *
     * @return string[]
     */
    public function getDecryptedCodes(): array
    {
        if (! $this->codes_received) {
            return [];
        }

        try {
            return json_decode(Crypt::decryptString($this->codes_received), true) ?? [];
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Encrypt and store an array of plain-text codes.
     *
     * @param  string[]  $codes
     */
    public function setEncryptedCodes(array $codes): void
    {
        $this->codes_received = Crypt::encryptString(json_encode($codes));
    }

    // ─── Scopes ──────────────────────────────────────────────────────────

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeFulfilled($query)
    {
        return $query->where('status', 'fulfilled');
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }
}
