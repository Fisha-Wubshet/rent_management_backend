<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BookingChangeLog extends Model
{
    protected $fillable = ['booking_id', 'changed_by_id', 'changed_by_name', 'removed_items_summary', 'added_items_summary', 'old_total_price', 'new_total_price', 'new_advance_payment', 'additional_payment', 'date_change_summary', 'notes', 'changed_at'];
    protected $casts = ['changed_at' => 'datetime'];

    public function booking() { return $this->belongsTo(Booking::class); }
    public function changedBy() { return $this->belongsTo(User::class, 'changed_by_id'); }
}
