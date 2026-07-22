<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PendingRegistration extends Model
{
    // Status constants — kept as strings so migrations/UIs read cleanly.
    public const STATUS_UNCONFIRMED = 'UNCONFIRMED';
    public const STATUS_PENDING     = 'PENDING';
    public const STATUS_APPROVED    = 'APPROVED';
    public const STATUS_REJECTED    = 'REJECTED';

    // Rejection reason codes — used both for filtering and to render friendly labels client-side.
    public const REJECT_REASONS = [
        'SPAM',
        'INSUFFICIENT_INFO',
        'DUPLICATE',
        'OUT_OF_REGION',
        'OTHER',
    ];

    protected $fillable = [
        'shop_name', 'item_label',
        'first_name', 'last_name',
        'phone_number', 'country_code',
        'email', 'telegram_username', 'city',
        'status',
        'confirm_token', 'confirmed_at',
        'reviewed_by_user_id', 'reviewed_at',
        'rejection_reason', 'rejection_note',
        'created_shop_id',
        'email_language',
        'source_ip',
    ];

    protected $casts = [
        'confirmed_at' => 'datetime',
        'reviewed_at'  => 'datetime',
    ];

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }

    public function createdShop(): BelongsTo
    {
        return $this->belongsTo(Shop::class, 'created_shop_id');
    }

    public function isPending(): bool     { return $this->status === self::STATUS_PENDING; }
    public function isUnconfirmed(): bool { return $this->status === self::STATUS_UNCONFIRMED; }
    public function isApproved(): bool    { return $this->status === self::STATUS_APPROVED; }
    public function isRejected(): bool    { return $this->status === self::STATUS_REJECTED; }
}
