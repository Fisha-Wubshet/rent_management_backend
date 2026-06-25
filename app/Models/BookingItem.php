<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BookingItem extends Model
{
    protected $fillable = ['booking_id', 'item_id', 'is_returned'];
    protected $casts = ['is_returned' => 'boolean'];

    public function booking() { return $this->belongsTo(Booking::class); }
    public function item() { return $this->belongsTo(Item::class); }
}
