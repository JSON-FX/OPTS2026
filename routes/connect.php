<?php

use App\Http\Controllers\ConnectPilotController;
use App\Http\Middleware\EnsureSsoEligibility;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\Route;

Route::post('/connect/pilot/launch', [ConnectPilotController::class, 'launch'])
    ->middleware(['auth', 'throttle:30,1'])->name('connect.pilot.launch');

// Signed server-to-server requests never use browser SSO or CSRF state.
Route::post('/connect/pilot/introspect', [ConnectPilotController::class, 'introspect'])
    ->withoutMiddleware([ValidateCsrfToken::class, EnsureSsoEligibility::class])
    ->middleware('throttle:'.config('connect_pilot.introspection_source_per_minute', 6000).',1')
    ->name('connect.pilot.introspect');
