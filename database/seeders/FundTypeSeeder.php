<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class FundTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $fundTypes = [
            [
                'name' => 'General Fund',
                'abbreviation' => 'GF',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Trust Fund',
                'abbreviation' => 'TF',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Special Education Fund',
                'abbreviation' => 'SEF',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        foreach ($fundTypes as $fundType) {
            DB::table('fund_types')->updateOrInsert(
                ['abbreviation' => $fundType['abbreviation']],
                $fundType
            );
        }
    }
}
