<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    protected $fillable = ['first_name', 'last_name', 'phone_number', 'alt_phone_number', 'notes', 'blacklisted', 'blacklist_reason', 'blacklisted_at', 'deleted', 'shop_id'];
    protected $casts = ['blacklisted' => 'boolean', 'deleted' => 'boolean', 'blacklisted_at' => 'datetime'];

    public function shop() { return $this->belongsTo(Shop::class); }
    public function bookings() { return $this->hasMany(Booking::class); }
}
