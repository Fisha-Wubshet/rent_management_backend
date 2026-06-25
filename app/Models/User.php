<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Tymon\JWTAuth\Contracts\JWTSubject;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements JWTSubject
{
    use HasRoles;

    protected $fillable = ['first_name', 'last_name', 'email', 'password', 'banned', 'last_login', 'shop_id', 'branch_id'];
    protected $hidden = ['password', 'remember_token'];
    protected $casts = ['banned' => 'boolean', 'last_login' => 'datetime'];
    protected $appends = ['enabled'];
    protected string $guard_name = 'api';

    public function getEnabledAttribute(): bool { return !$this->banned; }

    public function getJWTIdentifier() { return $this->getKey(); }
    public function getJWTCustomClaims() { return []; }

    public function shop() { return $this->belongsTo(Shop::class); }
    public function branch() { return $this->belongsTo(Branch::class); }
}
