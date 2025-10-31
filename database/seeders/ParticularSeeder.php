<?php

namespace Database\Seeders;

use App\Models\Particular;
use Illuminate\Database\Seeder;

class ParticularSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $particulars = [
            ['description' => 'Office Supplies (Bond Paper, Pens, Folders, etc.)', 'is_active' => true],
            ['description' => 'Computer Equipment (Desktop, Laptop, Monitor)', 'is_active' => true],
            ['description' => 'Computer Peripherals (Keyboard, Mouse, Printer)', 'is_active' => true],
            ['description' => 'Office Furniture (Desks, Chairs, Cabinets)', 'is_active' => true],
            ['description' => 'Air Conditioning Units and Installation', 'is_active' => true],
            ['description' => 'Laboratory Equipment and Apparatus', 'is_active' => true],
            ['description' => 'Books and Library Materials', 'is_active' => true],
            ['description' => 'Janitorial and Cleaning Supplies', 'is_active' => true],
            ['description' => 'Construction Materials (Cement, Steel, Paint)', 'is_active' => true],
            ['description' => 'Electrical Supplies and Fixtures', 'is_active' => true],
            ['description' => 'Plumbing Materials and Fixtures', 'is_active' => true],
            ['description' => 'Network Equipment (Router, Switch, Cables)', 'is_active' => true],
            ['description' => 'Software License and Subscription', 'is_active' => true],
            ['description' => 'Security Systems and CCTV Equipment', 'is_active' => true],
            ['description' => 'Audio-Visual Equipment (Projector, Sound System)', 'is_active' => true],
            ['description' => 'Classroom Furniture and Equipment', 'is_active' => true],
            ['description' => 'Sports and PE Equipment', 'is_active' => true],
            ['description' => 'Medical and First Aid Supplies', 'is_active' => true],
            ['description' => 'Vehicle Maintenance and Repair', 'is_active' => true],
            ['description' => 'Printing and Publication Services', 'is_active' => true],
            ['description' => 'Professional Services (Consultancy, Training)', 'is_active' => true],
            ['description' => 'Building Renovation and Repair Services', 'is_active' => true],
            ['description' => 'Generator and UPS Systems', 'is_active' => true],
            ['description' => 'Fire Safety Equipment and Systems', 'is_active' => true],
            ['description' => 'Landscaping and Gardening Supplies', 'is_active' => true],
            ['description' => 'Inactive Particular Example', 'is_active' => false],
        ];

        foreach ($particulars as $particular) {
            Particular::create($particular);
        }
    }
}
