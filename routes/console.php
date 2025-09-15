<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

// Schedule command untuk check product expiry setiap jam 8 pagi
Schedule::command('products:check-expiry')->dailyAt('03:00');

// Schedule command untuk check need purchase setiap jam 9 pagi (setelah expiry check)
Schedule::command('products:check-need-purchase')->dailyAt('10:10');
