<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    protected $fillable = ['entity_type', 'entity_id', 'action', 'performed_by', 'performed_by_name', 'shop_id', 'branch_id', 'details', 'timestamp'];
    protected $casts = ['timestamp' => 'datetime'];
}
