<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;

/**
 * Class DigitalProductCode
 *
 * @property int $id
 * @property int $product_id
 * @property int|null $seller_id
 * @property string $code AES-256-CBC encrypted at rest
 * @property string|null $pin AES-256-CBC encrypted PIN (e.g. gift card PIN)
 * @property string|null $code_hash SHA-256 hash for duplicate detection
 * @property string|null $serial_number Optional serial/reference number (plain text)
 * @property Carbon|null $expiry_date Code expiry date
 * @property string $status available | reserved | sold | failed | expired
 * @property string $source manual | supplier_api
 * @property bool $is_active Whether the code is active (can be sold) or deactivated by vendor/admin
 * @property int|null $order_id
 * @property int|null $order_detail_id
 * @property Carbon|null $assigned_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class DigitalProductCode extends Model
{
    protected $fillable = [
        'product_id',
        'seller_id',
        'code',
        'pin',
        'code_hash',
        'serial_number',
        'expiry_date',
        'status',
        'source',
        'is_active',
        'order_id',
        'order_detail_id',
        'assigned_at',
    ];

    protected function casts(): array
    {
        return [
            'product_id' => 'integer',
            'seller_id' => 'integer',
            'order_id' => 'integer',
            'order_detail_id' => 'integer',
            'is_active' => 'boolean',
            'expiry_date' => 'date',
            'assigned_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    // ─── Relationships ───────────────────────────────────────────────────────

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id')
            ->withoutGlobalScope(Product::STOREFRONT_SCOPE);
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class, 'seller_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function orderDetail(): BelongsTo
    {
        return $this->belongsTo(OrderDetail::class, 'order_detail_id');
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    /**
     * Decrypt and return the plain-text code.
     * ONLY call this at the moment of delivery — never in list views.
     */
    public function decryptCode(): string
    {
        return Crypt::decryptString($this->code);
    }

    /**
     * Decrypt and return the plain-text PIN.
     * Returns null if no PIN is stored.
     */
    public function decryptPin(): ?string
    {
        if (empty($this->pin)) {
            return null;
        }

        return Crypt::decryptString($this->pin);
    }

    /**
     * Whether this code is currently past its expiry date.
     */
    public function isExpired(): bool
    {
        return $this->expiry_date !== null && $this->expiry_date->isPast();
    }

    // ─── Scopes ──────────────────────────────────────────────────────────────

    /**
     * Scope: only available (unassigned) codes that are not expired and are active.
     */
    public function scopeAvailable(Builder $query): Builder
    {
        return $query->where('status', 'available')
            ->where('is_active', true)
            ->where(function (Builder $q): void {
                $q->whereNull('expiry_date')
                    ->orWhereDate('expiry_date', '>=', now()->toDateString());
            });
    }

    /**
     * Scope: only active codes.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope: only inactive (deactivated) codes.
     */
    public function scopeInactive(Builder $query): Builder
    {
        return $query->where('is_active', false);
    }

    /**
     * Scope: only sold (delivered) codes.
     */
    public function scopeSold(Builder $query): Builder
    {
        return $query->where('status', 'sold');
    }

    /**
     * Scope: only expired codes.
     */
    public function scopeExpired(Builder $query): Builder
    {
        return $query->where('status', 'expired');
    }

    /**
     * Scope: codes with a past expiry date that are still marked available.
     * Used by the daily expiry job.
     */
    public function scopePastExpiry(Builder $query): Builder
    {
        return $query->where('status', 'available')
            ->whereNotNull('expiry_date')
            ->whereDate('expiry_date', '<', now()->toDateString());
    }
}
