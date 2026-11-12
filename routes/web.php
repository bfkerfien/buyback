<?php

use App\Http\Controllers\AppraisalController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => view('welcome', [
    'appName' => config('app.name', 'Triglavian Buyback'),
]))->name('home');

Route::get('/health', fn () => response()->json([
    'status' => 'ok',
    'service' => 'buyback',
    'timestamp' => now()->toIso8601String(),
]))->name('health');

Route::post('/appraisals', [AppraisalController::class, 'store'])
    ->middleware('throttle:10,1')
    ->name('appraisals.store');

Route::get('/q/{token}', [AppraisalController::class, 'show'])
    ->whereAlphaNumeric('token')
    ->name('appraisals.show');
