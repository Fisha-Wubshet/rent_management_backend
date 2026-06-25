<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ItemBlock extends Model
{
    protected $fillable = ['item_id', 'branch_id', 'shop_id', 'start_date', 'end_date', 'quantity', 'reason', 'created_by'];

    public function item(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Item::class);
    }
}
