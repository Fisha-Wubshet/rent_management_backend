<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Subscription extends Model
{
    protected $fillable = ['shop_id', 'plan_name', 'amount_paid', 'start_date', 'end_date', 'max_branches', 'is_trial', 'is_suspended', 'notes', 'renewed_at'];
    protected $casts = ['start_date' => 'date', 'end_date' => 'date', 'is_trial' => 'boolean', 'is_suspended' => 'boolean', 'renewed_at' => 'datetime'];

    public function shop() { return $this->belongsTo(Shop::class); }

    public function getStatusAttribute(): string
    {
        if ($this->is_suspended) return 'SUSPENDED';
        $now = Carbon::now()->toDateString();
        $end = $this->end_date->toDateString();
        $gracePeriodEnd = $this->end_date->copy()->addDays(7)->toDateString();
        if ($now > $gracePeriodEnd) return 'EXPIRED';
        if ($now > $end) return 'GRACE_PERIOD';
        if ($now >= $this->end_date->copy()->subDays(7)->toDateString()) return 'EXPIRING_SOON';
        return 'ACTIVE';
    }

    public function isAccessAllowed(): bool
    {
        return in_array($this->status, ['ACTIVE', 'EXPIRING_SOON', 'GRACE_PERIOD']);
    }
}
