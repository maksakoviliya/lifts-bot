<?php

use App\Http\Controllers\EgegeshPushController;
use App\Http\Controllers\WebhookController;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;

Route::get('/', function () {
    return view('welcome');
});

Route::post('/webhook', WebhookController::class)->name('webhook');

// egegesh pages pushed by scripts/egegesh-push.sh while prod can't reach the site itself
// A machine-to-machine call: no session rows or CSRF for it
Route::post('/egegesh/push', EgegeshPushController::class)
    ->withoutMiddleware([StartSession::class, ShareErrorsFromSession::class, ValidateCsrfToken::class])
    ->name('egegesh.push');
