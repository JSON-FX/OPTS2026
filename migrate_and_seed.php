<?php

require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);

$kernel->call('db:seed', ['--class' => Database\Seeders\FundTypeSeeder::class]);

echo "seeded";
