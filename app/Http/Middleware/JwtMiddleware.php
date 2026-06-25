<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;

class JwtMiddleware
{
    public function handle(Request $request, Closure $next): mixed
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
            if (!$user) return response()->json(['message' => 'User not found.'], 404);
            if ($user->banned) return response()->json(['message' => 'Your account has been banned.'], 403);
        } catch (TokenExpiredException $e) {
            return response()->json(['message' => 'Token expired.'], 401);
        } catch (TokenInvalidException $e) {
            return response()->json(['message' => 'Token invalid.'], 401);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Token not found.'], 401);
        }
        return $next($request);
    }
}
