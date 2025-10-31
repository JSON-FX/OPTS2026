<?php

namespace Database\Seeders;

use App\Models\Office;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get first office for assignment (or create if none exist)
        $office = Office::first();
        if (!$office) {
            $office = Office::create([
                'name' => 'Main Office',
                'abbreviation' => 'MAIN',
                'is_active' => true,
            ]);
        }

        // Create Administrator user
        $admin = User::create([
            'name' => 'Administrator User',
            'email' => 'admin@example.com',
            'password' => Hash::make('password'),
            'office_id' => $office->id,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        $admin->assignRole('Administrator');

        // Create Endorser user
        $endorser = User::create([
            'name' => 'Endorser User',
            'email' => 'endorser@example.com',
            'password' => Hash::make('password'),
            'office_id' => $office->id,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        $endorser->assignRole('Endorser');

        // Create Viewer user
        $viewer = User::create([
            'name' => 'Viewer User',
            'email' => 'viewer@example.com',
            'password' => Hash::make('password'),
            'office_id' => $office->id,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        $viewer->assignRole('Viewer');

        // Create additional Viewer without office
        $viewerNoOffice = User::create([
            'name' => 'Viewer No Office',
            'email' => 'viewer2@example.com',
            'password' => Hash::make('password'),
            'office_id' => null,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        $viewerNoOffice->assignRole('Viewer');

        // Create inactive user
        $inactiveUser = User::create([
            'name' => 'Inactive User',
            'email' => 'inactive@example.com',
            'password' => Hash::make('password'),
            'office_id' => $office->id,
            'is_active' => false,
            'email_verified_at' => now(),
        ]);
        $inactiveUser->assignRole('Viewer');
    }
}
