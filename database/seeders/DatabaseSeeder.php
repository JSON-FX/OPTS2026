<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Seed reference data first
        $this->call([
            FundTypeSeeder::class,
            RoleSeeder::class,
            SupplierSeeder::class,
            ParticularSeeder::class,
            ActionTakenSeeder::class,
        ]);

        // Offices, users, and assignments are supplied by SSO.
        // Configure workflows against synchronized offices in the application.
    }
}
