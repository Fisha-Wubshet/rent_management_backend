<?php

namespace App\Models;

use App\Support\PhoneNumber;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Tymon\JWTAuth\Contracts\JWTSubject;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements JWTSubject
{
    use HasRoles;

    protected $fillable = [
        'first_name', 'last_name',
        'email', 'phone_number', 'country_code',
        'password', 'recovery_code_hash', 'must_change_password',
        'banned', 'last_login',
        'shop_id', 'branch_id',
    ];
    protected $hidden = ['password', 'remember_token', 'recovery_code_hash'];
    protected $casts = ['banned' => 'boolean', 'last_login' => 'datetime', 'must_change_password' => 'boolean'];
    protected $appends = ['enabled'];
    protected string $guard_name = 'api';

    public function getEnabledAttribute(): bool { return !$this->banned; }

    // Normalize phone_number on write so equality lookups are format-independent.
    public function setPhoneNumberAttribute($value): void
    {
        $cc = $this->attributes['country_code'] ?? '+251';
        $this->attributes['phone_number'] = PhoneNumber::normalize($value, $cc);
    }

    public function getJWTIdentifier() { return $this->getKey(); }
    public function getJWTCustomClaims() { return []; }

    public function shop() { return $this->belongsTo(Shop::class); }
    public function branch() { return $this->belongsTo(Branch::class); }
}
