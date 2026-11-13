<?php

use App\Http\Controllers\AppraisalController;
use App\Http\Controllers\AppraisalVisibilityController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EveSsoController;
use App\Http\Controllers\OperationsController;
use App\Http\Controllers\OreValueController;
use App\Http\Controllers\PayoutRuleController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => view('welcome', ['appName' => 'Lentivirus Buyback']))->name('home');
Route::get('/ore-values', [OreValueController::class, 'index'])->name('ore-values.index');
Route::get('/health', fn () => response()->json(['status' => 'ok', 'service' => 'buyback', 'timestamp' => now()->toIso8601String()]))->name('health');
Route::get('/login/eve', [EveSsoController::class, 'redirect'])->name('login.eve');
Route::get('/auth/eve/callback', [EveSsoController::class, 'callback'])->name('auth.eve.callback');
Route::post('/logout', [EveSsoController::class, 'logout'])->middleware('auth')->name('logout');
Route::post('/appraisals', [AppraisalController::class, 'store'])->middleware('throttle:10,1')->name('appraisals.store');
Route::get('/q/{token}', [AppraisalController::class, 'show'])->whereAlphaNumeric('token')->name('appraisals.show');

Route::middleware(['auth', 'active'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/link-character/eve', [EveSsoController::class, 'linkCharacter'])->name('login.eve.link');
    Route::delete('/appraisals/{quote}/hide', [AppraisalVisibilityController::class, 'hide'])->whereNumber('quote')->name('appraisals.hide');
    Route::post('/admin/appraisals/{quote}/archive', [AppraisalVisibilityController::class, 'archive'])->middleware('admin')->whereNumber('quote')->name('admin.appraisals.archive');
    Route::post('/admin/appraisals/{quote}/restore', [AppraisalVisibilityController::class, 'restore'])->middleware('admin')->whereNumber('quote')->name('admin.appraisals.restore');
    Route::post('/admin/appraisal-rates', [PayoutRuleController::class, 'store'])->middleware('admin')->name('admin.appraisal-rates.store');
    Route::delete('/admin/appraisal-rates/{categoryId}', [PayoutRuleController::class, 'destroy'])->middleware('admin')->whereNumber('categoryId')->name('admin.appraisal-rates.destroy');
    Route::post('/admin/appraisal-ore-rules', [PayoutRuleController::class, 'storeOre'])->middleware('admin')->name('admin.appraisal-ore-rules.store');
    Route::delete('/admin/appraisal-ore-rules/{typeId}', [PayoutRuleController::class, 'destroyOre'])->middleware('admin')->whereNumber('typeId')->name('admin.appraisal-ore-rules.destroy');
    Route::post('/runner/jobs', [OperationsController::class, 'createJob'])->middleware('admin')->name('runner.jobs.create');
    Route::post('/runner/jobs/{job}/accept', [OperationsController::class, 'acceptJob'])->whereNumber('job')->name('runner.jobs.accept');
    Route::post('/runner/jobs/{job}/deliver', [OperationsController::class, 'deliverJob'])->whereNumber('job')->name('runner.jobs.deliver');
});
