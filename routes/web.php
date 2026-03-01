<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProfileController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::get('/dashboard/workload-detail', [DashboardController::class, 'workloadDetail'])
    ->middleware(['auth', 'verified'])
    ->name('dashboard.workload-detail');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');

    // Notifications (Story 3.8)
    Route::get('/notifications', [\App\Http\Controllers\NotificationController::class, 'index'])
        ->name('notifications.index');
    Route::post('/notifications/{id}/read', [\App\Http\Controllers\NotificationController::class, 'markAsRead'])
        ->name('notifications.markAsRead');
    Route::post('/notifications/read-all', [\App\Http\Controllers\NotificationController::class, 'markAllAsRead'])
        ->name('notifications.markAllAsRead');
    Route::delete('/notifications/{id}', [\App\Http\Controllers\NotificationController::class, 'destroy'])
        ->name('notifications.destroy');

    Route::resource('procurements', \App\Http\Controllers\ProcurementController::class);

    // Transactions - view all (all authenticated users)
    Route::get('/transactions', [\App\Http\Controllers\TransactionController::class, 'index'])
        ->name('transactions.index');

    // Purchase Request - view (all authenticated users)
    Route::get('/purchase-requests/{id}', [\App\Http\Controllers\PurchaseRequestController::class, 'show'])
        ->name('purchase-requests.show');

    // Purchase Order - view (all authenticated users)
    Route::get('/purchase-orders/{id}', [\App\Http\Controllers\PurchaseOrderController::class, 'show'])
        ->name('purchase-orders.show');

    // Voucher - view (all authenticated users)
    Route::get('/vouchers/{id}', [\App\Http\Controllers\VoucherController::class, 'show'])
        ->name('vouchers.show');
});

Route::middleware(['auth', 'role:Endorser|Administrator'])->group(function () {
    // Transaction receive routes (Story 3.5)
    Route::get('/transactions/pending', [\App\Http\Controllers\TransactionReceiveController::class, 'pending'])
        ->name('transactions.pending');
    Route::post('/transactions/{transaction}/receive', [\App\Http\Controllers\TransactionReceiveController::class, 'store'])
        ->name('transactions.receive.store');
    Route::post('/transactions/receive-bulk', [\App\Http\Controllers\TransactionReceiveController::class, 'storeBulk'])
        ->name('transactions.receive.bulk');

    // Transaction endorsement routes
    Route::get('/transactions/{transaction}/endorse', [\App\Http\Controllers\TransactionEndorseController::class, 'create'])
        ->name('transactions.endorse.create');
    Route::post('/transactions/{transaction}/endorse', [\App\Http\Controllers\TransactionEndorseController::class, 'store'])
        ->name('transactions.endorse.store');

    // Transaction complete route (Story 3.6)
    Route::post('/transactions/{transaction}/complete', [\App\Http\Controllers\TransactionCompleteController::class, 'store'])
        ->name('transactions.complete.store');

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

    // Voucher - create/edit/delete (Endorser/Administrator only)
    Route::get('/procurements/{procurement}/vouchers/create', [\App\Http\Controllers\VoucherController::class, 'create'])
        ->name('procurements.vouchers.create');
    Route::post('/procurements/{procurement}/vouchers', [\App\Http\Controllers\VoucherController::class, 'store'])
        ->name('procurements.vouchers.store');
    Route::get('/vouchers/{id}/edit', [\App\Http\Controllers\VoucherController::class, 'edit'])
        ->name('vouchers.edit');
    Route::put('/vouchers/{id}', [\App\Http\Controllers\VoucherController::class, 'update'])
        ->name('vouchers.update');
    Route::delete('/vouchers/{id}', [\App\Http\Controllers\VoucherController::class, 'destroy'])
        ->name('vouchers.destroy');
});

Route::middleware(['auth', 'role:Administrator'])->group(function () {
    // Transaction admin actions (Story 3.7)
    Route::post('/transactions/{transaction}/hold', [\App\Http\Controllers\TransactionHoldController::class, 'store'])
        ->name('transactions.hold.store');
    Route::post('/transactions/{transaction}/cancel', [\App\Http\Controllers\TransactionCancelController::class, 'store'])
        ->name('transactions.cancel.store');
    Route::post('/transactions/{transaction}/resume', [\App\Http\Controllers\TransactionResumeController::class, 'store'])
        ->name('transactions.resume.store');

    Route::resource('admin/workflows', App\Http\Controllers\Admin\WorkflowController::class)->names([
        'index' => 'admin.workflows.index',
        'create' => 'admin.workflows.create',
        'store' => 'admin.workflows.store',
        'show' => 'admin.workflows.show',
        'edit' => 'admin.workflows.edit',
        'update' => 'admin.workflows.update',
        'destroy' => 'admin.workflows.destroy',
    ]);

    Route::get('admin/users', [App\Http\Controllers\Admin\UserController::class, 'index'])->name('admin.users.index');
    Route::get('admin/users/{user}/edit', [App\Http\Controllers\Admin\UserController::class, 'edit'])->name('admin.users.edit');
    Route::put('admin/users/{user}', [App\Http\Controllers\Admin\UserController::class, 'update'])->name('admin.users.update');
    Route::delete('admin/users/{user}', [App\Http\Controllers\Admin\UserController::class, 'destroy'])->name('admin.users.destroy');

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

    // ETTS Data Migration
    Route::get('admin/migration', [App\Http\Controllers\Admin\MigrationController::class, 'index'])
        ->name('admin.migration.index');
    Route::post('admin/migration/upload', [App\Http\Controllers\Admin\MigrationController::class, 'upload'])
        ->name('admin.migration.upload');
    Route::get('admin/migration/{import}/mappings', [App\Http\Controllers\Admin\MigrationController::class, 'mappings'])
        ->name('admin.migration.mappings');
    Route::post('admin/migration/{import}/mappings', [App\Http\Controllers\Admin\MigrationController::class, 'saveMappings'])
        ->name('admin.migration.save-mappings');
    Route::post('admin/migration/{import}/dry-run', [App\Http\Controllers\Admin\MigrationController::class, 'dryRun'])
        ->name('admin.migration.dry-run');
    Route::get('admin/migration/{import}/dry-run', [App\Http\Controllers\Admin\MigrationController::class, 'dryRunResults'])
        ->name('admin.migration.dry-run-results');
    Route::post('admin/migration/{import}/execute', [App\Http\Controllers\Admin\MigrationController::class, 'execute'])
        ->name('admin.migration.execute');
    Route::get('admin/migration/{import}/progress', [App\Http\Controllers\Admin\MigrationController::class, 'progress'])
        ->name('admin.migration.progress');
    Route::get('admin/migration/{import}/results', [App\Http\Controllers\Admin\MigrationController::class, 'results'])
        ->name('admin.migration.results');
    Route::post('admin/migration/{import}/rollback', [App\Http\Controllers\Admin\MigrationController::class, 'rollback'])
        ->name('admin.migration.rollback');
    Route::post('admin/migration/clear-all', [App\Http\Controllers\Admin\MigrationController::class, 'clearAllProcurements'])
        ->name('admin.migration.clear-all');
});

require __DIR__.'/auth.php';
