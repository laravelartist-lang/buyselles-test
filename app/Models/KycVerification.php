<?php

namespace App\Models;

use App\Enums\KycStatus;
use App\Enums\KycUserType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $user_type
 * @property int $user_id
 * @property string $external_user_id
 * @property string|null $applicant_id
 * @property string $level_name
 * @property string $status
 * @property string|null $review_answer
 * @property string|null $reject_type
 * @property array|null $reject_labels
 * @property string|null $moderation_comment
 * @property \Illuminate\Support\Carbon|null $required_at
 * @property \Illuminate\Support\Carbon|null $verified_at
 * @property \Illuminate\Support\Carbon|null $last_synced_at
 * @property array|null $last_webhook_payload
 */
class KycVerification extends Model
{
    protected $fillable = [
        'user_type',
        'user_id',
        'external_user_id',
        'applicant_id',
        'level_name',
        'status',
        'review_answer',
        'reject_type',
        'reject_labels',
        'moderation_comment',
        'required_at',
        'verified_at',
        'last_synced_at',
        'last_webhook_payload',
    ];

    protected $casts = [
        'id' => 'integer',
        'user_type' => 'string',
        'user_id' => 'integer',
        'external_user_id' => 'string',
        'applicant_id' => 'string',
        'level_name' => 'string',
        'status' => 'string',
        'review_answer' => 'string',
        'reject_type' => 'string',
        'reject_labels' => 'array',
        'moderation_comment' => 'string',
        'required_at' => 'datetime',
        'verified_at' => 'datetime',
        'last_synced_at' => 'datetime',
        'last_webhook_payload' => 'array',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Seller::class, 'user_id');
    }

    public function scopeForCustomer(Builder $query): Builder
    {
        return $query->where('user_type', KycUserType::CUSTOMER);
    }

    public function scopeForVendor(Builder $query): Builder
    {
        return $query->where('user_type', KycUserType::VENDOR);
    }

    public function isApproved(): bool
    {
        return $this->status === KycStatus::APPROVED;
    }

    public function isInProgress(): bool
    {
        return in_array($this->status, KycStatus::inProgressStatuses(), true);
    }

    public function isRejected(): bool
    {
        return in_array($this->status, [KycStatus::REJECTED, KycStatus::RESET], true);
    }

    /**
     * A verification only blocks the account once it has been requested.
     */
    public function blocksAccount(): bool
    {
        return $this->required_at !== null && ! $this->isApproved();
    }
}
