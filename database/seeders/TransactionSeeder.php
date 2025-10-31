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
        $procurements = DB::table('procurements')
            ->orderBy('id')
            ->get();

        if ($procurements->isEmpty()) {
            return;
        }

        $faker = fake();
        $statusOptions = ['Created', 'In Progress', 'Completed', 'On Hold', 'Cancelled'];
        $modeOptions = ['Public Bidding', 'Shopping', 'Direct Contracting'];
        $categoryCounters = ['PR' => 0, 'PO' => 0, 'VCH' => 0];

        $officeIds = Office::query()->pluck('id')->all();
        $activeUserIds = User::query()->where('is_active', true)->pluck('id')->all();
        $supplierRecords = Supplier::query()->get(['id', 'address'])->keyBy('id');
        $supplierIds = $supplierRecords->keys()->all();
        $fundTypeIds = FundType::query()->pluck('id')->all();
        $particularDescriptions = DB::table('particulars')->pluck('description', 'id');

        if (empty($officeIds) || empty($activeUserIds) || empty($supplierIds) || empty($fundTypeIds)) {
            return;
        }

        $workflowRows = DB::table('workflows')
            ->whereIn('category', ['PR', 'PO', 'VCH'])
            ->get(['id', 'category']);

        if ($workflowRows->isEmpty()) {
            $now = Carbon::now();
            DB::table('workflows')->insert([
                ['category' => 'PR', 'name' => 'Default PR Workflow', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
                ['category' => 'PO', 'name' => 'Default PO Workflow', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
                ['category' => 'VCH', 'name' => 'Default VCH Workflow', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ]);
            $workflowRows = DB::table('workflows')
                ->whereIn('category', ['PR', 'PO', 'VCH'])
                ->get(['id', 'category']);
        }

        $workflowsByCategory = $workflowRows
            ->groupBy('category')
            ->map(fn ($rows) => $rows->pluck('id')->all())
            ->all();

        if (
            empty($workflowsByCategory['PR'] ?? null) ||
            empty($workflowsByCategory['PO'] ?? null) ||
            empty($workflowsByCategory['VCH'] ?? null)
        ) {
            return;
        }

        $purchaseRequestMap = [];
        $purchaseOrderMap = [];

        foreach ($procurements as $index => $procurement) {
            $prTransactionDate = Carbon::now()->subDays($faker->numberBetween(20, 80));
            $prTransactionId = DB::table('transactions')->insertGetId([
                'procurement_id' => $procurement->id,
                'category' => 'PR',
                'reference_number' => $this->buildReferenceNumber('PR', ++$categoryCounters['PR']),
                'status' => Arr::random($statusOptions),
                'workflow_id' => Arr::random($workflowsByCategory['PR']),
                'current_office_id' => $faker->boolean(70) ? Arr::random($officeIds) : null,
                'current_user_id' => $faker->boolean(45) ? Arr::random($activeUserIds) : null,
                'created_by_user_id' => Arr::random($activeUserIds),
                'created_at' => $prTransactionDate,
                'updated_at' => (clone $prTransactionDate)->addDays($faker->numberBetween(1, 10)),
                'deleted_at' => null,
            ]);

            $prSupplierId = Arr::random($supplierIds);
            $prCreatedAt = Carbon::parse($procurement->date_of_entry)
                ->addDays($faker->numberBetween(0, 5));
            $purchaseRequestId = DB::table('purchase_requests')->insertGetId([
                'transaction_id' => $prTransactionId,
                'supplier_id' => $prSupplierId,
                'purpose' => $procurement->purpose ?: $faker->sentence(10),
                'estimated_budget' => round($faker->randomFloat(2, 10000, 300000), 2),
                'date_of_pr' => $prCreatedAt->format('Y-m-d'),
                'created_at' => $prCreatedAt,
                'updated_at' => $prCreatedAt,
            ]);

            $purchaseRequestMap[$procurement->id] = [
                'transaction_id' => $prTransactionId,
                'purchase_request_id' => $purchaseRequestId,
                'supplier_id' => $prSupplierId,
                'transaction_date' => $prTransactionDate,
            ];

            if ($index < 6) {
                $poTransactionDate = (clone $prTransactionDate)->addDays($faker->numberBetween(5, 15));
                $poTransactionId = DB::table('transactions')->insertGetId([
                    'procurement_id' => $procurement->id,
                    'category' => 'PO',
                    'reference_number' => $this->buildReferenceNumber('PO', ++$categoryCounters['PO']),
                    'status' => Arr::random($statusOptions),
                    'workflow_id' => Arr::random($workflowsByCategory['PO']),
                    'current_office_id' => $faker->boolean(60) ? Arr::random($officeIds) : null,
                    'current_user_id' => $faker->boolean(40) ? Arr::random($activeUserIds) : null,
                    'created_by_user_id' => Arr::random($activeUserIds),
                    'created_at' => $poTransactionDate,
                    'updated_at' => (clone $poTransactionDate)->addDays($faker->numberBetween(1, 7)),
                    'deleted_at' => null,
                ]);

                $poSupplierId = $purchaseRequestMap[$procurement->id]['supplier_id'];
                $totalCost = round($faker->randomFloat(2, 120000, 500000), 2);
                $purchaseOrderId = DB::table('purchase_orders')->insertGetId([
                    'transaction_id' => $poTransactionId,
                    'supplier_id' => $poSupplierId,
                    'supplier_address' => $supplierRecords[$poSupplierId]->address ?? $faker->address(),
                    'purchase_request_id' => $purchaseRequestMap[$procurement->id]['purchase_request_id'],
                    'particulars' => $particularDescriptions[$procurement->particular_id] ?? $faker->sentence(10),
                    'fund_type_id' => Arr::random($fundTypeIds),
                    'total_cost' => $totalCost,
                    'date_of_po' => $poTransactionDate->format('Y-m-d'),
                    'delivery_date' => $faker->boolean(65)
                        ? (clone $poTransactionDate)->addDays($faker->numberBetween(7, 21))->format('Y-m-d')
                        : null,
                    'delivery_term' => $faker->boolean(65) ? $faker->numberBetween(7, 30) : null,
                    'payment_term' => $faker->boolean(65) ? $faker->numberBetween(15, 45) : null,
                    'amount_in_words' => ucfirst($faker->words(6, true)),
                    'mode_of_procurement' => Arr::random($modeOptions),
                    'created_at' => $poTransactionDate,
                    'updated_at' => (clone $poTransactionDate)->addDays($faker->numberBetween(1, 5)),
                ]);

                $purchaseOrderMap[$procurement->id] = [
                    'transaction_id' => $poTransactionId,
                    'purchase_order_id' => $purchaseOrderId,
                    'supplier_id' => $poSupplierId,
                    'transaction_date' => $poTransactionDate,
                    'total_cost' => $totalCost,
                ];
            }

            if ($index < 4 && isset($purchaseOrderMap[$procurement->id])) {
                $voucherTransactionDate = (clone $purchaseOrderMap[$procurement->id]['transaction_date'])
                    ->addDays($faker->numberBetween(3, 12));
                $voucherTransactionId = DB::table('transactions')->insertGetId([
                    'procurement_id' => $procurement->id,
                    'category' => 'VCH',
                    'reference_number' => $this->buildReferenceNumber('VCH', ++$categoryCounters['VCH']),
                    'status' => Arr::random($statusOptions),
                    'workflow_id' => Arr::random($workflowsByCategory['VCH']),
                    'current_office_id' => $faker->boolean(55) ? Arr::random($officeIds) : null,
                    'current_user_id' => $faker->boolean(35) ? Arr::random($activeUserIds) : null,
                    'created_by_user_id' => Arr::random($activeUserIds),
                    'created_at' => $voucherTransactionDate,
                    'updated_at' => (clone $voucherTransactionDate)->addDays($faker->numberBetween(1, 4)),
                    'deleted_at' => null,
                ]);

                DB::table('vouchers')->insert([
                    'transaction_id' => $voucherTransactionId,
                    'purchase_order_id' => $purchaseOrderMap[$procurement->id]['purchase_order_id'],
                    'supplier_id' => $purchaseOrderMap[$procurement->id]['supplier_id'],
                    'obr_number' => sprintf('OBR-%s-%03d', Carbon::now()->format('y'), $categoryCounters['VCH']),
                    'particulars' => $particularDescriptions[$procurement->particular_id] ?? $faker->sentence(8),
                    'gross_amount' => max(
                        $purchaseOrderMap[$procurement->id]['total_cost'],
                        round($faker->randomFloat(2, 150000, 550000), 2)
                    ),
                    'created_at' => $voucherTransactionDate,
                    'updated_at' => (clone $voucherTransactionDate)->addDays($faker->numberBetween(1, 3)),
                ]);
            }
        }
    }

    /**
     * Generate a predictable reference number combining category, year, and sequence.
     */
    private function buildReferenceNumber(string $category, int $counter): string
    {
        return sprintf('%s-%s-%04d', $category, Carbon::now()->format('Y'), $counter);
    }
}
