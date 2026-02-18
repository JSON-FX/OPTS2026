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
        // Get first office for default assignment
        $office = Office::first();
        if (! $office) {
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

        // Create Endorser users at each workflow office
        $workflowOffices = [
            'MBO' => ['name' => 'MBO Endorser', 'email' => 'mbo@example.com'],
            'MMO' => ['name' => 'MMO Endorser', 'email' => 'mmo@example.com'],
            'BAC' => ['name' => 'BAC Endorser', 'email' => 'bac@example.com'],
            'MMO-PO' => ['name' => 'MMO-PO Endorser', 'email' => 'mmo-po@example.com'],
            'MMO-PSMD' => ['name' => 'MMO-PSMD Endorser', 'email' => 'mmo-psmd@example.com'],
            'MACCO' => ['name' => 'MACCO Endorser', 'email' => 'macco@example.com'],
            'MTO' => ['name' => 'MTO Endorser', 'email' => 'mto@example.com'],
        ];

        foreach ($workflowOffices as $abbreviation => $userData) {
            $officeRecord = Office::where('abbreviation', $abbreviation)->first();
            if (! $officeRecord) {
                $this->command->warn("Office '{$abbreviation}' not found. Skipping user creation.");

                continue;
            }

            $user = User::create([
                'name' => $userData['name'],
                'email' => $userData['email'],
                'password' => Hash::make('password'),
                'office_id' => $officeRecord->id,
                'is_active' => true,
                'email_verified_at' => now(),
            ]);
            $user->assignRole('Endorser');
        }
    }
}
