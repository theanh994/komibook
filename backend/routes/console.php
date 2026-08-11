<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

Schedule::command('checkout-sessions:expire')->everyMinute()->withoutOverlapping();
Schedule::command('order-side-effects:dispatch --limit=100')->everyMinute()->withoutOverlapping();
Schedule::command('campaigns:dispatch-due --limit=25')->everyMinute()->withoutOverlapping();
Schedule::command('articles:publish-due --limit=50')->everyMinute()->withoutOverlapping();
