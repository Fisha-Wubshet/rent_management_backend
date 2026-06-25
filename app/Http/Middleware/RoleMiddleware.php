<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class RoleMiddleware
{
    public function handle(Request $request, Closure $next, string ...$roles): mixed
    {
        $user = auth('api')->user();
        if (!$user) return response()->json(['message' => 'Unauthenticated.'], 401);
        foreach ($roles as $role) {
            if ($user->hasRole($role)) return $next($request);
        }
        return response()->json(['message' => 'Forbidden.'], 403);
    }
}
