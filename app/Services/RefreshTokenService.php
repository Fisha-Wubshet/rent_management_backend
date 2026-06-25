<?php

namespace App\Services;

use App\Models\RefreshToken;
use App\Models\User;
use Illuminate\Support\Str;

class RefreshTokenService
{
    public function create(User $user): RefreshToken
    {
        RefreshToken::where('user_id', $user->id)->delete();
        return RefreshToken::create([
            'user_id' => $user->id,
            'token' => Str::random(64),
            'expires_at' => now()->addDays(7),
        ]);
    }

    public function findValid(string $token): RefreshToken
    {
        $rt = RefreshToken::where('token', $token)->with('user')->first();
        if (!$rt) abort(400, 'Invalid refresh token.');
        if ($rt->isExpired()) {
            $rt->delete();
            abort(400, 'Refresh token has expired. Please log in again.');
        }
        return $rt;
    }

    public function deleteForUser(User $user): void
    {
        RefreshToken::where('user_id', $user->id)->delete();
    }
}
