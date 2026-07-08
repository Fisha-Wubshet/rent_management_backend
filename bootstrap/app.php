<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            // API routes registered first so they take priority over the Vue catch-all
            Route::middleware('api')
                ->group(base_path('routes/api.php'));
            // Vue catch-all must come last
            Route::middleware('web')
                ->group(base_path('routes/web.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'jwt.auth' => \App\Http\Middleware\JwtMiddleware::class,
            'role' => \App\Http\Middleware\RoleMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (\Illuminate\Validation\ValidationException $e, $request) {
            return response()->json([
                'status' => 422,
                'error' => 'Validation Error',
                'message' => collect($e->errors())->flatten()->first(),
                'timestamp' => now()->toIso8601String(),
            ], 422);
        });
        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\HttpException $e, $request) {
            return response()->json([
                'status' => $e->getStatusCode(),
                'error' => 'Business Rule Violation',
                'message' => $e->getMessage(),
                'timestamp' => now()->toIso8601String(),
            ], $e->getStatusCode());
        });
        $exceptions->render(function (\Illuminate\Database\Eloquent\ModelNotFoundException $e, $request) {
            return response()->json([
                'status' => 404,
                'error' => 'Not Found',
                'message' => 'Resource not found.',
                'timestamp' => now()->toIso8601String(),
            ], 404);
        });
    })->create();
