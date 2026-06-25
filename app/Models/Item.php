<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Item extends Model
{
    protected $fillable = ['name', 'unique_code', 'min_price', 'has_cleaning_gap', 'quantity', 'description', 'image_url', 'category_id', 'branch_id'];
    protected $casts = ['has_cleaning_gap' => 'boolean', 'min_price' => 'decimal:2'];

    public function branch() { return $this->belongsTo(Branch::class); }
    public function category() { return $this->belongsTo(Category::class); }
    public function bookingItems() { return $this->hasMany(BookingItem::class); }
    public function itemBlocks()   { return $this->hasMany(ItemBlock::class); }
}
