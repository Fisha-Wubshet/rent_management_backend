<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    protected $fillable = ['name', 'shop_id', 'branch_id', 'deleted'];
    protected $casts = ['deleted' => 'boolean'];

    public function shop() { return $this->belongsTo(Shop::class); }
    public function items() { return $this->hasMany(Item::class); }
}
