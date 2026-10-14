<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $this->command?->info('Users and application grants are managed in SSO; no local accounts were created.');
    }
}
