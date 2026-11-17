<?php

use App\Http\Middleware\RequireActiveUser;
use App\Http\Middleware\RequireAdmin;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up'
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: '*');
        $middleware->alias([
            'active' => RequireActiveUser::class,
            'admin' => RequireAdmin::class,
        ]);
    })
    ->withSchedule(function (Schedule $schedule): void {
        // The frequent refresh is intentionally limited to PI materials,
        // compressed asteroid ores (including bonus grades), compressed ice,
        // four requested ice products, and the eight standard minerals.
        $schedule->command('buyback:refresh-jita-prices --fast')
            ->everyFifteenMinutes()
            ->withoutOverlapping(20);

        // Ship Buyback has its own slower refresh and never joins the 15-minute set.
        $schedule->command('buyback:refresh-jita-prices --ships')
            ->everyTwoHours()
            ->withoutOverlapping(110);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
    })
    ->create();
