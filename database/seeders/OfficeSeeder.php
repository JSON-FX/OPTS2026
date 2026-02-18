<?php

namespace Database\Seeders;

use App\Models\Office;
use Illuminate\Database\Seeder;

class OfficeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $offices = [
            // Executive Office
            ['name' => 'Municipal Mayor\'s Office', 'type' => 'Executive', 'abbreviation' => 'MMO', 'is_active' => true],
            ['name' => 'Office of the Vice Mayor', 'type' => 'Executive', 'abbreviation' => 'VM', 'is_active' => true],
            ['name' => 'Sangguniang Bayan', 'type' => 'Executive', 'abbreviation' => 'SB', 'is_active' => true],

            // Administrative Offices
            ['name' => 'Municipal Administrator Office', 'type' => 'Administrative', 'abbreviation' => 'ADMIN', 'is_active' => true],
            ['name' => 'Human Resource Management Office', 'type' => 'Administrative', 'abbreviation' => 'HRMO', 'is_active' => true],
            ['name' => 'General Services Office', 'type' => 'Administrative', 'abbreviation' => 'GSO', 'is_active' => true],

            // Financial Management
            ['name' => 'Municipal Treasurer Office', 'type' => 'Financial', 'abbreviation' => 'MTO', 'is_active' => true],
            ['name' => 'Municipal Accounting Office', 'type' => 'Financial', 'abbreviation' => 'MACCO', 'is_active' => true],
            ['name' => 'Municipal Budget Office', 'type' => 'Financial', 'abbreviation' => 'MBO', 'is_active' => true],
            ['name' => 'Municipal Assessor Office', 'type' => 'Financial', 'abbreviation' => 'ASSESS', 'is_active' => true],

            // Planning and Development
            ['name' => 'Municipal Planning and Development Office', 'type' => 'Planning', 'abbreviation' => 'MPDO', 'is_active' => true],
            ['name' => 'Municipal Engineering Office', 'type' => 'Planning', 'abbreviation' => 'MEO', 'is_active' => true],
            ['name' => 'Municipal Agriculture Office', 'type' => 'Planning', 'abbreviation' => 'MAO', 'is_active' => true],
            ['name' => 'Municipal Environment and Natural Resources Office', 'type' => 'Planning', 'abbreviation' => 'MENRO', 'is_active' => true],

            // Social Services
            ['name' => 'Municipal Social Welfare and Development Office', 'type' => 'Social', 'abbreviation' => 'MSWDO', 'is_active' => true],
            ['name' => 'Municipal Health Office', 'type' => 'Social', 'abbreviation' => 'MHO', 'is_active' => true],
            ['name' => 'Municipal Disaster Risk Reduction and Management Office', 'type' => 'Social', 'abbreviation' => 'MDRRMO', 'is_active' => true],

            // Regulatory and Legal
            ['name' => 'Municipal Legal Office', 'type' => 'Regulatory', 'abbreviation' => 'LEGAL', 'is_active' => true],
            ['name' => 'Business Permits and Licensing Office', 'type' => 'Regulatory', 'abbreviation' => 'BPLO', 'is_active' => true],
            ['name' => 'Municipal Civil Registrar Office', 'type' => 'Regulatory', 'abbreviation' => 'MCRO', 'is_active' => true],

            // Public Safety
            ['name' => 'Philippine National Police', 'type' => 'Public Safety', 'abbreviation' => 'PNP', 'is_active' => true],
            ['name' => 'Bureau of Fire Protection', 'type' => 'Public Safety', 'abbreviation' => 'BFP', 'is_active' => true],

            // Procurement & Supply
            ['name' => 'Bids and Awards Committee', 'type' => 'Procurement', 'abbreviation' => 'BAC', 'is_active' => true],
            ['name' => 'Procurement Office', 'type' => 'Procurement', 'abbreviation' => 'MMO-PO', 'is_active' => true],
            ['name' => 'Property and Supply Management Division', 'type' => 'Procurement', 'abbreviation' => 'MMO-PSMD', 'is_active' => true],

            // Other Offices
            ['name' => 'Public Information Office', 'type' => 'Other', 'abbreviation' => 'PIO', 'is_active' => true],
            ['name' => 'Municipal Tourism Office', 'type' => 'Other', 'abbreviation' => 'MTO-TOUR', 'is_active' => true],
        ];

        foreach ($offices as $office) {
            Office::create($office);
        }
    }
}
