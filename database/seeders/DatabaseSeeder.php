<?php

namespace Database\Seeders;

use App\Models\User;
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
            OfficeSeeder::class,
            SupplierSeeder::class,
            ParticularSeeder::class,
            ActionTakenSeeder::class,
        ]);

        // Seed users with different roles (depends on offices and roles)
        $this->call([
            UserSeeder::class,
            ProcurementSeeder::class,
            TransactionSeeder::class,
        ]);
    }
}
