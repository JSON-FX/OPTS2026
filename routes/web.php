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

    Route::resource('procurements', \App\Http\Controllers\ProcurementController::class);

    // Purchase Request - view (all authenticated users)
    Route::get('/purchase-requests/{id}', [\App\Http\Controllers\PurchaseRequestController::class, 'show'])
        ->name('purchase-requests.show');

    // Purchase Order - view (all authenticated users)
    Route::get('/purchase-orders/{id}', [\App\Http\Controllers\PurchaseOrderController::class, 'show'])
        ->name('purchase-orders.show');
});

Route::middleware(['auth', 'role:Endorser|Administrator'])->group(function () {
    // Purchase Request - create/edit/delete (Endorser/Administrator only)
    Route::get('/procurements/{procurement}/purchase-requests/create', [\App\Http\Controllers\PurchaseRequestController::class, 'create'])
        ->name('procurements.purchase-requests.create');
    Route::post('/procurements/{procurement}/purchase-requests', [\App\Http\Controllers\PurchaseRequestController::class, 'store'])
        ->name('procurements.purchase-requests.store');
    Route::get('/purchase-requests/{id}/edit', [\App\Http\Controllers\PurchaseRequestController::class, 'edit'])
        ->name('purchase-requests.edit');
    Route::put('/purchase-requests/{id}', [\App\Http\Controllers\PurchaseRequestController::class, 'update'])
        ->name('purchase-requests.update');
    Route::delete('/purchase-requests/{id}', [\App\Http\Controllers\PurchaseRequestController::class, 'destroy'])
        ->name('purchase-requests.destroy');

    // Purchase Order - create/edit/delete (Endorser/Administrator only)
    Route::get('/procurements/{procurement}/purchase-orders/create', [\App\Http\Controllers\PurchaseOrderController::class, 'create'])
        ->name('procurements.purchase-orders.create');
    Route::post('/procurements/{procurement}/purchase-orders', [\App\Http\Controllers\PurchaseOrderController::class, 'store'])
        ->name('procurements.purchase-orders.store');
    Route::get('/purchase-orders/{id}/edit', [\App\Http\Controllers\PurchaseOrderController::class, 'edit'])
        ->name('purchase-orders.edit');
    Route::put('/purchase-orders/{id}', [\App\Http\Controllers\PurchaseOrderController::class, 'update'])
        ->name('purchase-orders.update');
    Route::delete('/purchase-orders/{id}', [\App\Http\Controllers\PurchaseOrderController::class, 'destroy'])
        ->name('purchase-orders.destroy');
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
