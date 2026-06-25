<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Shop extends Model
{
    protected $fillable = ['name', 'address', 'item_label', 'banned'];
    protected $casts = ['banned' => 'boolean'];

    public function branches() { return $this->hasMany(Branch::class); }
    public function users() { return $this->hasMany(User::class); }
    public function subscription() { return $this->hasOne(Subscription::class); }
    public function customers() { return $this->hasMany(Customer::class); }
    public function bookings() { return $this->hasMany(Booking::class); }
    public function categories() { return $this->hasMany(Category::class); }
}
