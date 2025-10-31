<?php

namespace Database\Seeders;

use App\Models\ActionTaken;
use Illuminate\Database\Seeder;

class ActionTakenSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $actions = [
            ['description' => 'Approved', 'is_active' => true],
            ['description' => 'Endorsed', 'is_active' => true],
            ['description' => 'Reviewed', 'is_active' => true],
            ['description' => 'Verified', 'is_active' => true],
            ['description' => 'Certified', 'is_active' => true],
            ['description' => 'Forwarded', 'is_active' => true],
            ['description' => 'Received', 'is_active' => true],
            ['description' => 'Noted', 'is_active' => true],
            ['description' => 'For Clarification', 'is_active' => true],
            ['description' => 'Returned for Revision', 'is_active' => true],
            ['description' => 'Rejected', 'is_active' => true],
            ['description' => 'On Hold', 'is_active' => true],
            ['description' => 'Cancelled', 'is_active' => true],
            ['description' => 'Completed', 'is_active' => true],
            ['description' => 'For Signature', 'is_active' => true],
            ['description' => 'Signed', 'is_active' => true],
            ['description' => 'Processed', 'is_active' => true],
            ['description' => 'Released', 'is_active' => true],
            ['description' => 'Inactive Action Example', 'is_active' => false],
        ];

        foreach ($actions as $action) {
            ActionTaken::create($action);
        }
    }
}
