<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// withoutOverlapping: a hung run must not pile up new ones on top of it
Schedule::command('app:parse-lifts-command')->everyFifteenMinutes()->withoutOverlapping();

Schedule::command('app:fetch-weather')->hourly()->withoutOverlapping();

Schedule::command('telegram:send-lifts-to-channel')->dailyAt('08:30');

Schedule::command('app:parse-web-cams')->everyThreeHours()->withoutOverlapping();

Schedule::command('app:parse-web-cam')->everyFifteenMinutes()->withoutOverlapping();