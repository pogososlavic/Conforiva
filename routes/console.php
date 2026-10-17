<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Run schedule:work locally or schedule:run every minute on the verified host.
Schedule::command('homya:expire-bank-transfers --apply')
    ->everyMinute()->withoutOverlapping(5)->when(fn () => config('commerce.inventory_enabled') === true);

// Reuse the existing scheduler; no DNS/network lookup is made by public page requests.
Schedule::command('security:refresh-google-crawlers')
    ->dailyAt('04:20')->timezone('UTC')->withoutOverlapping(10)
    ->when(fn () => in_array(config('storefront_protection.mode'), ['observe', 'enforce'], true));

// Remove only inactive files in the protection's isolated cache, including after it is disabled.
Schedule::command('security:prune-storefront-cache')
    ->hourly()->withoutOverlapping(10);

// Reconciliation cannot interrupt receipt confirmation; unique order IDs prevent recounting.
Schedule::command('homya:measure-paid-purchases --scheduled --apply')
    ->everyMinute()->withoutOverlapping(5)
    ->when(fn () => config('purchase_measurement.enabled') === true);
