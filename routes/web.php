<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->view('welcome', [
        'appName' => config('app.name', 'Triglavian Buyback'),
    ]);
});

Route::get('/health', fn () => response()->json([
    'status' => 'ok',
    'service' => 'buyback',
    'timestamp' => now()->toIso8601String(),
]));
