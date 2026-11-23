<?php

use App\Http\Controllers\AppraisalController;
use App\Http\Controllers\AppraisalComparisonController;
use App\Http\Controllers\AppraisalVisibilityController;
use App\Http\Controllers\AdminAppraisalController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EveSsoController;
use App\Http\Controllers\OperationsController;
use App\Http\Controllers\OreValueController;
use App\Http\Controllers\PayoutRuleController;
use App\Http\Controllers\PiPlannerController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => auth()->check() ? redirect()->route('dashboard') : view('welcome', ['appName' => 'EveBuyback']))->name('home');
Route::get('/ore-values', [OreValueController::class, 'index'])->middleware(['auth', 'active', 'admin'])->name('ore-values.index');
Route::post('/ore-values/pi-taxes', [OreValueController::class, 'updateTaxes'])->middleware(['auth', 'active', 'admin'])->name('ore-values.pi-taxes');
Route::get('/health', fn () => response()->json(['status' => 'ok', 'service' => 'buyback', 'timestamp' => now()->toIso8601String()]))->name('health');
Route::get('/login/eve', [EveSsoController::class, 'redirect'])->name('login.eve');
Route::get('/auth/eve/callback', [EveSsoController::class, 'callback'])->name('auth.eve.callback');
Route::post('/logout', [EveSsoController::class, 'logout'])->middleware('auth')->name('logout');
Route::post('/appraisals', [AppraisalController::class, 'store'])->middleware('throttle:10,1')->name('appraisals.store');
Route::get('/ship-buyback', [AppraisalController::class, 'shipBuyback'])->name('ship-buyback.index');
Route::post('/ship-buyback', [AppraisalController::class, 'storeShipBuyback'])->middleware('throttle:10,1')->name('ship-buyback.store');
Route::get('/q/{token}', [AppraisalController::class, 'show'])->whereAlphaNumeric('token')->name('appraisals.show');

Route::middleware(['auth', 'active'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/pi-planner', [PiPlannerController::class, 'index'])->middleware('admin')->name('pi-planner.index');
    Route::post('/pi-planner/p1-buy-rule', [PiPlannerController::class, 'setP1BuyRule'])->middleware('admin')->name('pi-planner.p1-buy-rule');
    Route::get('/link-character/eve', [EveSsoController::class, 'linkCharacter'])->name('login.eve.link');
    Route::delete('/appraisals/{quote}', [AppraisalVisibilityController::class, 'destroy'])->whereNumber('quote')->name('appraisals.destroy');
    Route::delete('/appraisals/{quote}/hide', [AppraisalVisibilityController::class, 'hide'])->whereNumber('quote')->name('appraisals.hide');
    Route::post('/admin/appraisals/purge', [AdminAppraisalController::class, 'purge'])->middleware('admin')->name('admin.appraisals.purge');
    Route::post('/admin/appraisals/purge-archived', [AdminAppraisalController::class, 'purgeArchived'])->middleware('admin')->name('admin.appraisals.purge-archived');
    Route::post('/admin/appraisal-comparison', [AppraisalComparisonController::class, 'compare'])->middleware('admin')->name('admin.appraisal-comparison');
    Route::post('/admin/appraisals/{quote}/contract', [AppraisalVisibilityController::class, 'markContracted'])->middleware('admin')->whereNumber('quote')->name('admin.appraisals.contract');
    Route::post('/admin/appraisals/{quote}/archive', [AppraisalVisibilityController::class, 'archive'])->middleware('admin')->whereNumber('quote')->name('admin.appraisals.archive');
    Route::post('/admin/appraisals/{quote}/restore', [AppraisalVisibilityController::class, 'restore'])->middleware('admin')->whereNumber('quote')->name('admin.appraisals.restore');
    Route::post('/admin/mineral-pricing', [PayoutRuleController::class, 'storeMineralPricing'])->middleware('admin')->name('admin.mineral-pricing.store');
    Route::post('/admin/ice-pricing', [PayoutRuleController::class, 'storeIcePricing'])->middleware('admin')->name('admin.ice-pricing.store');
    Route::post('/admin/gas-pricing', [PayoutRuleController::class, 'storeGasPricing'])->middleware('admin')->name('admin.gas-pricing.store');
    Route::post('/admin/appraisal-ore-rules', [PayoutRuleController::class, 'storeOre'])->middleware('admin')->name('admin.appraisal-ore-rules.store');
    Route::delete('/admin/appraisal-ore-rules/{typeId}', [PayoutRuleController::class, 'destroyOre'])->middleware('admin')->whereNumber('typeId')->name('admin.appraisal-ore-rules.destroy');
    Route::post('/runner/jobs', [OperationsController::class, 'createJob'])->middleware('admin')->name('runner.jobs.create');
    Route::post('/runner/jobs/{job}/accept', [OperationsController::class, 'acceptJob'])->whereNumber('job')->name('runner.jobs.accept');
    Route::post('/runner/jobs/{job}/deliver', [OperationsController::class, 'deliverJob'])->whereNumber('job')->name('runner.jobs.deliver');
});
