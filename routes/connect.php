<?php

use App\Http\Controllers\ConnectPilotController;
use Illuminate\Support\Facades\Route;

Route::post('/connect/pilot/launch', [ConnectPilotController::class, 'launch'])
    ->middleware(['auth', 'throttle:30,1'])->name('connect.pilot.launch');

// Signed server-to-server requests verify persisted host sessions directly.
// Exclude the web group to avoid anonymous session writes and browser cookies.
Route::post('/connect/pilot/introspect', [ConnectPilotController::class, 'introspect'])
    ->withoutMiddleware('web')
    ->middleware('throttle:'.config('connect_pilot.introspection_source_per_minute', 6000).',1')
    ->name('connect.pilot.introspect');
