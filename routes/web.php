<?php

use Illuminate\Support\Facades\Route;

// Serve Vue's index.html for all GET requests not handled by API routes.
// This allows Vue Router to handle /dashboard, /bookings, /login etc.
Route::get('/{any?}', function () {
    $index = public_path('index.html');
    if (file_exists($index)) {
        return response(file_get_contents($index), 200)->header('Content-Type', 'text/html');
    }
    return response('App not deployed.', 404);
})->where('any', '^(?!api(?:/|$)).*$');
