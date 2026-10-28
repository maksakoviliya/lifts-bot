<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Bot updates via long polling: Telegram can't open connections to the hosting, so the bot asks for them itself.
// First in the list and in the background: a run lasts ~55s and must not wait for or hold up the tasks below.
// withoutOverlapping(2): if a run dies, its lock expires in 2 minutes instead of the default 24 hours.
Schedule::command('telegram:poll')->everyMinute()->runInBackground()->withoutOverlapping(2);

// withoutOverlapping: a hung run must not pile up new ones on top of it
Schedule::command('app:parse-lifts-command')->everyFifteenMinutes()->withoutOverlapping();

Schedule::command('app:fetch-weather')->hourly()->withoutOverlapping();

Schedule::command('telegram:send-lifts-to-channel')->dailyAt('08:30');

Schedule::command('app:parse-web-cams')->everyThreeHours()->withoutOverlapping();

Schedule::command('app:parse-web-cam')->everyFifteenMinutes()->withoutOverlapping();