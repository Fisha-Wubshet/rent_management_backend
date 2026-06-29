<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BookingItem extends Model
{
    protected $fillable = ['booking_id', 'item_id', 'is_returned', 'cleaning_gap_released'];
    protected $casts = ['is_returned' => 'boolean', 'cleaning_gap_released' => 'boolean'];

    public function booking() { return $this->belongsTo(Booking::class); }
    public function item() { return $this->belongsTo(Item::class); }
}
