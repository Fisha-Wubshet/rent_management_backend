<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Booking extends Model
{
    protected $fillable = [
        'invoice_number', 'booking_date', 'return_date', 'first_name', 'last_name',
        'phone_number', 'alt_phone_number', 'booking_type', 'status', 'customer_id',
        'total_agreed_price', 'total_advance_payment', 'security_deposit',
        'security_deposit_returned', 'deposit_deduction', 'deposit_deduction_reason',
        'excess_damage_charge', 'refund_amount', 'cancellation_reason', 'cancelled_at',
        'shop_id', 'branch_id'
    ];
    protected $casts = [
        'booking_date' => 'date', 'return_date' => 'date', 'cancelled_at' => 'datetime',
        'total_agreed_price' => 'decimal:2', 'total_advance_payment' => 'decimal:2',
        'security_deposit' => 'decimal:2', 'security_deposit_returned' => 'boolean',
        'deposit_deduction' => 'decimal:2', 'excess_damage_charge' => 'decimal:2',
        'refund_amount' => 'decimal:2',
    ];
    protected $appends = ['balance_due'];

    public function getBalanceDueAttribute(): float
    {
        if ($this->status === 'CANCELLED') return 0.0;
        return (float) $this->total_agreed_price - (float) $this->total_advance_payment;
    }

    public function shop() { return $this->belongsTo(Shop::class); }
    public function branch() { return $this->belongsTo(Branch::class); }
    public function customer() { return $this->belongsTo(Customer::class); }
    public function items() { return $this->hasMany(BookingItem::class); }
    public function changeLogs() { return $this->hasMany(BookingChangeLog::class); }
}
