<?php

namespace Database\Seeders;

use App\Services\SSOOfficeService;
use Illuminate\Database\Seeder;

class OfficeSeeder extends Seeder
{
    public function run(): void
    {
        app(SSOOfficeService::class)->sync();
    }
}
