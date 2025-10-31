<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Role::create(['name' => 'Viewer', 'guard_name' => 'web']);
        Role::create(['name' => 'Endorser', 'guard_name' => 'web']);
        Role::create(['name' => 'Administrator', 'guard_name' => 'web']);
    }
}
