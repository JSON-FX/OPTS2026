<?php

namespace Database\Seeders;

use App\Models\Office;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class UserSeeder extends Seeder
{
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
            'sso_uuid' => Str::uuid()->toString(),
            'name' => 'Administrator User',
            'email' => 'admin@example.com',
            'password' => null,
            'office_id' => $office->id,
            'is_active' => true,
            'sso_position' => 'System Administrator',
            'last_sso_login_at' => now(),
            'email_verified_at' => now(),
        ]);
        $admin->assignRole('Administrator');

        // Create Viewer user
        $viewer = User::create([
            'sso_uuid' => Str::uuid()->toString(),
            'name' => 'Viewer User',
            'email' => 'viewer@example.com',
            'password' => null,
            'office_id' => $office->id,
            'is_active' => true,
            'sso_position' => 'Staff',
            'last_sso_login_at' => now(),
            'email_verified_at' => now(),
        ]);
        $viewer->assignRole('Viewer');

        // Create additional Viewer without office
        $viewerNoOffice = User::create([
            'sso_uuid' => Str::uuid()->toString(),
            'name' => 'Viewer No Office',
            'email' => 'viewer2@example.com',
            'password' => null,
            'office_id' => null,
            'is_active' => true,
            'sso_position' => 'Staff',
            'last_sso_login_at' => now(),
            'email_verified_at' => now(),
        ]);
        $viewerNoOffice->assignRole('Viewer');

        // Create inactive user
        $inactiveUser = User::create([
            'sso_uuid' => Str::uuid()->toString(),
            'name' => 'Inactive User',
            'email' => 'inactive@example.com',
            'password' => null,
            'office_id' => $office->id,
            'is_active' => false,
            'sso_position' => 'Staff',
            'last_sso_login_at' => now(),
            'email_verified_at' => now(),
        ]);
        $inactiveUser->assignRole('Viewer');

        // Create Endorser users at each workflow office
        $workflowOffices = [
            'MBO' => ['name' => 'MBO Endorser', 'email' => 'mbo@example.com', 'position' => 'Budget Officer'],
            'MMO' => ['name' => 'MMO Endorser', 'email' => 'mmo@example.com', 'position' => 'Management Officer'],
            'BAC' => ['name' => 'BAC Endorser', 'email' => 'bac@example.com', 'position' => 'BAC Member'],
            'MMO-PO' => ['name' => 'MMO-PO Endorser', 'email' => 'mmo-po@example.com', 'position' => 'Purchase Officer'],
            'MMO-PSMD' => ['name' => 'MMO-PSMD Endorser', 'email' => 'mmo-psmd@example.com', 'position' => 'Supply Officer'],
            'MACCO' => ['name' => 'MACCO Endorser', 'email' => 'macco@example.com', 'position' => 'Accountant'],
            'MTO' => ['name' => 'MTO Endorser', 'email' => 'mto@example.com', 'position' => 'Treasurer'],
        ];

        foreach ($workflowOffices as $abbreviation => $userData) {
            $officeRecord = Office::where('abbreviation', $abbreviation)->first();
            if (! $officeRecord) {
                $this->command->warn("Office '{$abbreviation}' not found. Skipping user creation.");

                continue;
            }

            $user = User::create([
                'sso_uuid' => Str::uuid()->toString(),
                'name' => $userData['name'],
                'email' => $userData['email'],
                'password' => null,
                'office_id' => $officeRecord->id,
                'is_active' => true,
                'sso_position' => $userData['position'],
                'last_sso_login_at' => now(),
                'email_verified_at' => now(),
            ]);
            $user->assignRole('Endorser');
        }
    }
}
