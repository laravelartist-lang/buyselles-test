<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $customer_scope
 * @property string $checkout_method
 * @property string|null $idempotency_key
 * @property string $cart_fingerprint
 * @property string $status
 * @property array<int, int>|null $order_ids
 * @property array<string, mixed>|null $response_payload
 * @property int|null $http_status
 * @property Carbon $created_at
 */
class CustomerCheckoutIdempotency extends Model
{
    public const STATUS_PROCESSING = 'processing';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    public const UPDATED_AT = null;

    protected $table = 'customer_checkout_idempotency';

    protected $fillable = [
        'customer_scope',
        'checkout_method',
        'idempotency_key',
        'cart_fingerprint',
        'status',
        'order_ids',
        'response_payload',
        'http_status',
    ];

    protected function casts(): array
    {
        return [
            'order_ids' => 'array',
            'response_payload' => 'array',
            'http_status' => 'integer',
            'created_at' => 'datetime',
        ];
    }
}
