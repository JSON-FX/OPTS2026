<?php

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

if (! $app->environment('staging') || config('database.connections.mysql.database') !== 'edts_image_check') {
    throw new RuntimeException('Disposable image-check database required.');
}
$counts = [
    'roles' => Spatie\Permission\Models\Role::count(),
    'fund_types' => App\Models\FundType::count(),
    'actions' => App\Models\ActionTaken::count(),
    'users' => App\Models\User::count(),
    'suppliers' => App\Models\Supplier::count(),
    'particulars' => App\Models\Particular::count(),
];
if ($counts !== ['roles' => 3, 'fund_types' => 3, 'actions' => 19, 'users' => 0, 'suppliers' => 0, 'particulars' => 0]
    || ! Illuminate\Support\Facades\Schema::hasColumn('users', 'sso_role')
    || ! Illuminate\Support\Facades\Schema::hasTable('connect_pilot_revocations')) {
    throw new RuntimeException('Fresh MySQL bootstrap verification failed.');
}
echo "Fresh MySQL schema and reference-only bootstrap verified.\n";
