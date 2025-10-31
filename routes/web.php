<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

Route::get('/dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::middleware(['auth', 'role:Administrator'])->group(function () {
    Route::resource('admin/users', App\Http\Controllers\Admin\UserController::class)->names([
        'index' => 'admin.users.index',
        'create' => 'admin.users.create',
        'store' => 'admin.users.store',
        'edit' => 'admin.users.edit',
        'update' => 'admin.users.update',
        'destroy' => 'admin.users.destroy',
    ]);

    Route::resource('admin/repositories/offices', App\Http\Controllers\Admin\OfficeController::class)->names([
        'index' => 'admin.repositories.offices.index',
        'create' => 'admin.repositories.offices.create',
        'store' => 'admin.repositories.offices.store',
        'edit' => 'admin.repositories.offices.edit',
        'update' => 'admin.repositories.offices.update',
        'destroy' => 'admin.repositories.offices.destroy',
    ]);

    Route::resource('admin/repositories/suppliers', App\Http\Controllers\Admin\SupplierController::class)->names([
        'index' => 'admin.repositories.suppliers.index',
        'create' => 'admin.repositories.suppliers.create',
        'store' => 'admin.repositories.suppliers.store',
        'edit' => 'admin.repositories.suppliers.edit',
        'update' => 'admin.repositories.suppliers.update',
        'destroy' => 'admin.repositories.suppliers.destroy',
    ]);

    Route::resource('admin/repositories/particulars', App\Http\Controllers\Admin\ParticularController::class)->names([
        'index' => 'admin.repositories.particulars.index',
        'create' => 'admin.repositories.particulars.create',
        'store' => 'admin.repositories.particulars.store',
        'edit' => 'admin.repositories.particulars.edit',
        'update' => 'admin.repositories.particulars.update',
        'destroy' => 'admin.repositories.particulars.destroy',
    ]);

    Route::resource('admin/repositories/fund-types', App\Http\Controllers\Admin\FundTypeController::class)->names([
        'index' => 'admin.repositories.fund-types.index',
        'create' => 'admin.repositories.fund-types.create',
        'store' => 'admin.repositories.fund-types.store',
        'edit' => 'admin.repositories.fund-types.edit',
        'update' => 'admin.repositories.fund-types.update',
        'destroy' => 'admin.repositories.fund-types.destroy',
    ]);

    Route::resource('admin/repositories/action-taken', App\Http\Controllers\Admin\ActionTakenController::class)->names([
        'index' => 'admin.repositories.action-taken.index',
        'create' => 'admin.repositories.action-taken.create',
        'store' => 'admin.repositories.action-taken.store',
        'edit' => 'admin.repositories.action-taken.edit',
        'update' => 'admin.repositories.action-taken.update',
        'destroy' => 'admin.repositories.action-taken.destroy',
    ]);
});

require __DIR__.'/auth.php';
