<?php

namespace Database\Seeders;

use App\Models\FundType;
use Illuminate\Database\Seeder;
use RuntimeException;
use Spatie\Permission\Models\Role;

class StagingReferenceSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('staging')) {
            throw new RuntimeException('This bootstrap is restricted to staging.');
        }

        foreach (['Viewer', 'Endorser', 'Administrator'] as $name) {
            Role::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }
        foreach (['GF' => 'General Fund', 'TF' => 'Trust Fund', 'SEF' => 'Special Education Fund'] as $abbreviation => $name) {
            FundType::firstOrCreate(['abbreviation' => $abbreviation], ['name' => $name, 'is_active' => true]);
        }
        $this->call(ActionTakenSeeder::class);
    }
}
