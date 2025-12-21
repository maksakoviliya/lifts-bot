<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::call(function () {
	Artisan::call('app:parse-lifts-command');
})->everyFifteenMinutes();

Schedule::call(function () {
	Artisan::call('app:fetch-weather');
})->hourly();

Schedule::call(function () {
	Artisan::call('telegram:send-lifts-to-channel');
})->dailyAt('06:00');