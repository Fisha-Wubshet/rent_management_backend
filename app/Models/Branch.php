<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Branch extends Model
{
    protected $fillable = ['name', 'address', 'phone', 'shop_id'];

    public function shop() { return $this->belongsTo(Shop::class); }
    public function users() { return $this->hasMany(User::class); }
    public function items() { return $this->hasMany(Item::class); }
    public function bookings() { return $this->hasMany(Booking::class); }
}
