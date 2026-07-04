<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Crypt;

/**
 * Class SupplierApi
 *
 * @property int $id
 * @property string $name
 * @property string $driver
 * @property string $base_url
 * @property string $credentials Encrypted JSON blob
 * @property string $auth_type api_key|bearer_token|oauth2|basic|hmac
 * @property array|null $settings Driver-specific config
 * @property int $rate_limit_per_minute
 * @property int $priority
 * @property bool $is_active
 * @property bool $is_sandbox
 * @property bool $supports_direct_top_up
 * @property string $health_status healthy|degraded|down|unknown
 * @property Carbon|null $health_checked_at
 * @property Carbon|null $last_sync_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Collection|SupplierProductMapping[] $productMappings
 * @property-read Collection|SupplierApiLog[] $logs
 * @property-read Collection|SupplierOrder[] $supplierOrders
 */
class SupplierApi extends Model
{
    protected $fillable = [
        'name',
        'driver',
        'base_url',
        'credentials',
        'auth_type',
        'settings',
        'rate_limit_per_minute',
        'priority',
        'is_active',
        'is_sandbox',
        'supports_direct_top_up',
        'health_status',
        'health_checked_at',
        'last_sync_at',
    ];

    protected function casts(): array
    {
        return [
            'settings' => 'array',
            'rate_limit_per_minute' => 'integer',
            'priority' => 'integer',
            'is_active' => 'boolean',
            'is_sandbox' => 'boolean',
            'supports_direct_top_up' => 'boolean',
            'health_checked_at' => 'datetime',
            'last_sync_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    // ─── Credential Accessors (AES-256-CBC) ──────────────────────────────

    /**
     * Decrypt and return the credentials as an associative array.
     *
     * @return array{api_key?: string, api_secret?: string, client_id?: string}
     */
    public function getDecryptedCredentials(): array
    {
        try {
            return json_decode(Crypt::decryptString($this->credentials), true) ?? [];
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Encrypt and store credentials from a plain associative array.
     */
    public function setEncryptedCredentials(array $credentials): void
    {
        $this->credentials = Crypt::encryptString(json_encode($credentials));
    }

    // ─── Relationships ───────────────────────────────────────────────────

    public function productMappings(): HasMany
    {
        return $this->hasMany(SupplierProductMapping::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(SupplierApiLog::class);
    }

    public function supplierOrders(): HasMany
    {
        return $this->hasMany(SupplierOrder::class);
    }

    // ─── Helpers ─────────────────────────────────────────────────────────

    public function isHealthy(): bool
    {
        return $this->health_status === 'healthy';
    }

    public function isDown(): bool
    {
        return $this->health_status === 'down';
    }

    // ─── Scopes ──────────────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeHealthy($query)
    {
        return $query->where('health_status', 'healthy');
    }

    public function scopeByPriority($query)
    {
        return $query->orderBy('priority', 'asc');
    }
}
