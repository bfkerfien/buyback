<?php

use Illuminate\Support\Facades\Artisan;

Artisan::command('buyback:about', function () {
    $this->info('Triglavian Buyback application bootstrap is running.');
})->purpose('Display Buyback application status.');
