<?php

namespace Database\Seeders;

use App\Models\Supplier;
use Illuminate\Database\Seeder;

class SupplierSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $suppliers = [
            [
                'name' => 'ABC Office Supplies Inc.',
                'address' => '123 Business St., Manila, Philippines',
                'contact_person' => 'John Doe',
                'contact_number' => '+63 2 1234 5678',
                'is_active' => true,
            ],
            [
                'name' => 'Tech Solutions Corp.',
                'address' => '456 Innovation Ave., Makati City, Philippines',
                'contact_person' => 'Jane Smith',
                'contact_number' => '+63 2 2345 6789',
                'is_active' => true,
            ],
            [
                'name' => 'Educational Equipment Providers',
                'address' => '789 Learning Rd., Quezon City, Philippines',
                'contact_person' => 'Robert Johnson',
                'contact_number' => '+63 2 3456 7890',
                'is_active' => true,
            ],
            [
                'name' => 'Furniture Depot Philippines',
                'address' => '321 Comfort Lane, Pasig City, Philippines',
                'contact_person' => 'Maria Garcia',
                'contact_number' => '+63 2 4567 8901',
                'is_active' => true,
            ],
            [
                'name' => 'Computer World Trading',
                'address' => '654 Tech Hub, Mandaluyong City, Philippines',
                'contact_person' => 'Michael Chen',
                'contact_number' => '+63 2 5678 9012',
                'is_active' => true,
            ],
            [
                'name' => 'Construction Materials Supply Co.',
                'address' => '987 Builder St., Caloocan City, Philippines',
                'contact_person' => 'David Santos',
                'contact_number' => '+63 2 6789 0123',
                'is_active' => true,
            ],
            [
                'name' => 'Laboratory Equipment Specialists',
                'address' => '147 Science Park, Taguig City, Philippines',
                'contact_person' => 'Sarah Lee',
                'contact_number' => '+63 2 7890 1234',
                'is_active' => true,
            ],
            [
                'name' => 'Printing & Publishing House',
                'address' => '258 Media Ave., Manila, Philippines',
                'contact_person' => 'Carlos Reyes',
                'contact_number' => '+63 2 8901 2345',
                'is_active' => true,
            ],
            [
                'name' => 'Cleaning & Janitorial Services Inc.',
                'address' => '369 Clean St., Pasay City, Philippines',
                'contact_person' => 'Linda Cruz',
                'contact_number' => '+63 2 9012 3456',
                'is_active' => true,
            ],
            [
                'name' => 'Security Systems & Equipment',
                'address' => '741 Safety Blvd., Paranaque City, Philippines',
                'contact_person' => 'Thomas Lim',
                'contact_number' => '+63 2 0123 4567',
                'is_active' => true,
            ],
            [
                'name' => 'Inactive Supplier Example',
                'address' => '999 Old Business Rd., Manila, Philippines',
                'contact_person' => 'N/A',
                'contact_number' => null,
                'is_active' => false,
            ],
        ];

        foreach ($suppliers as $supplier) {
            Supplier::create($supplier);
        }
    }
}
