<?php

namespace Database\Seeders;

use App\Models\Office;
use Illuminate\Database\Seeder;

class OfficeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Offices sourced from the ETTS (legacy system) endorsing_offices table.
     * IDs correspond to ETTS endorsing_offices.id for migration mapping.
     */
    public function run(): void
    {
        $offices = [
            // Municipal Mayor's Office & Divisions
            ['name' => 'Municipal Mayor\'s Office', 'type' => 'Executive', 'abbreviation' => 'MMO', 'is_active' => true],
            ['name' => 'MMO - Personal Staff', 'type' => 'Executive', 'abbreviation' => 'MMO-PS', 'is_active' => true],
            ['name' => 'MMO - Management Information System Division', 'type' => 'Executive', 'abbreviation' => 'MMO-MIS', 'is_active' => true],
            ['name' => 'MMO - Public Affairs, Information and Assistance Division', 'type' => 'Executive', 'abbreviation' => 'MMO-PAIAD', 'is_active' => true],
            ['name' => 'MMO - Bids and Award Committee', 'type' => 'Procurement', 'abbreviation' => 'MMO-BAC', 'is_active' => true],
            ['name' => 'MMO - Procurement Office', 'type' => 'Procurement', 'abbreviation' => 'MMO-PO', 'is_active' => true],
            ['name' => 'MMO - Livelihood Division', 'type' => 'Executive', 'abbreviation' => 'MMO-LD', 'is_active' => true],
            ['name' => 'MMO - Permits and Licenses Division', 'type' => 'Regulatory', 'abbreviation' => 'MMO-BPLO', 'is_active' => true],
            ['name' => 'MMO - General Services Office', 'type' => 'Administrative', 'abbreviation' => 'MMO-GSO', 'is_active' => true],
            ['name' => 'MMO - Nutrition Division', 'type' => 'Social', 'abbreviation' => 'MMO-ND', 'is_active' => true],
            ['name' => 'MMO - Population Development Division', 'type' => 'Social', 'abbreviation' => 'MMO-PDD', 'is_active' => true],
            ['name' => 'MMO - Economic Enterprise Division', 'type' => 'Executive', 'abbreviation' => 'MEMO', 'is_active' => true],
            ['name' => 'MMO - Barangay Affairs Division', 'type' => 'Executive', 'abbreviation' => 'MMO-BAD', 'is_active' => true],
            ['name' => 'MMO - Human Resource Management Office', 'type' => 'Administrative', 'abbreviation' => 'MMO-HRMO', 'is_active' => true],
            ['name' => 'MMO - Civil Security Unit', 'type' => 'Public Safety', 'abbreviation' => 'MMO-CSU', 'is_active' => true],

            // Legislative
            ['name' => 'Office of the Sangguniang Bayan', 'type' => 'Legislative', 'abbreviation' => 'SBO', 'is_active' => true],

            // Planning & Development
            ['name' => 'Municipal Planning and Development Office', 'type' => 'Planning', 'abbreviation' => 'MPDO', 'is_active' => true],
            ['name' => 'Municipal Engineer Office', 'type' => 'Planning', 'abbreviation' => 'MEO', 'is_active' => true],
            ['name' => 'Municipal Agriculture Office', 'type' => 'Planning', 'abbreviation' => 'MAO', 'is_active' => true],
            ['name' => 'Municipal Environment and Natural Resources Office', 'type' => 'Planning', 'abbreviation' => 'MENRO', 'is_active' => true],

            // Financial Management
            ['name' => 'Municipal Budget Office', 'type' => 'Financial', 'abbreviation' => 'MBO', 'is_active' => true],
            ['name' => 'Municipal Accounting Office', 'type' => 'Financial', 'abbreviation' => 'MACCO', 'is_active' => true],
            ['name' => 'Municipal Treasurer Office', 'type' => 'Financial', 'abbreviation' => 'MTO', 'is_active' => true],
            ['name' => 'Municipal Assessor Office', 'type' => 'Financial', 'abbreviation' => 'MASSO', 'is_active' => true],

            // Social Services
            ['name' => 'Municipal Social Welfare and Development Office', 'type' => 'Social', 'abbreviation' => 'MSWDO', 'is_active' => true],
            ['name' => 'Municipal Health Office', 'type' => 'Social', 'abbreviation' => 'MHO', 'is_active' => true],
            ['name' => 'MMO - Municipal Disaster Risk Reduction and Management Office', 'type' => 'Social', 'abbreviation' => 'MDRRMO', 'is_active' => true],

            // Regulatory
            ['name' => 'Municipal Civil Registrar Office', 'type' => 'Regulatory', 'abbreviation' => 'MCRO', 'is_active' => true],

            // External / Oversight
            ['name' => 'Commission On Audit', 'type' => 'External', 'abbreviation' => 'COA', 'is_active' => true],
            ['name' => 'Commission On Elections', 'type' => 'External', 'abbreviation' => 'COMELEC', 'is_active' => true],
            ['name' => 'Department of Interior Local Government', 'type' => 'External', 'abbreviation' => 'DILG', 'is_active' => true],

            // Public Safety
            ['name' => 'Philippine National Police', 'type' => 'Public Safety', 'abbreviation' => 'PNP', 'is_active' => true],
            ['name' => 'Bureau of Fire Protection', 'type' => 'Public Safety', 'abbreviation' => 'BFP', 'is_active' => true],

            // Other
            ['name' => 'Local School Board', 'type' => 'Other', 'abbreviation' => 'LSB', 'is_active' => true],
            ['name' => 'Municipal Trial Court', 'type' => 'Other', 'abbreviation' => 'MTC', 'is_active' => true],
        ];

        foreach ($offices as $office) {
            Office::firstOrCreate(
                ['abbreviation' => $office['abbreviation']],
                $office
            );
        }
    }
}
