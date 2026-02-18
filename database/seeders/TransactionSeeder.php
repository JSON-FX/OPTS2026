<?php

namespace Database\Seeders;

use App\Models\FundType;
use App\Models\Office;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class TransactionSeeder extends Seeder
{
    /**
     * Seed transactions and their type-specific tables (PR/PO/VCH) with coherent data.
     */
    public function run(): void
    {
        // No example transaction data seeded.
    }

    /**
     * Generate a predictable reference number combining category, year, and sequence.
     */
    private function buildReferenceNumber(string $category, int $counter): string
    {
        return sprintf('%s-%s-%04d', $category, Carbon::now()->format('Y'), $counter);
    }
}
