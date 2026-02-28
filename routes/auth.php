<?php

use App\Http\Controllers\Auth\SSOController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('login', [SSOController::class, 'showLogin'])
        ->name('login');

    Route::get('auth/sso/redirect', [SSOController::class, 'redirect'])
        ->name('sso.redirect');

    Route::get('auth/sso/callback', [SSOController::class, 'callback'])
        ->name('sso.callback');
});

Route::middleware('auth')->group(function () {
    Route::post('logout', [SSOController::class, 'logout'])
        ->name('logout');
});
