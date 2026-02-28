<?php

declare(strict_types=1);

namespace App\Services\Migration;

use App\Models\MigrationImport;
use App\Models\MigrationRecord;
use Illuminate\Support\Facades\DB;

class MigrationReportService
{
    public function generateDryRunReport(array $groups, EttsMapper $mapper): array
    {
        $report = [
            'procurements_to_create' => 0,
            'transactions_to_create' => ['pr' => 0, 'po' => 0, 'vch' => 0],
            'records_to_skip' => 0,
            'orphaned_pos' => 0,
            'orphaned_vchs' => 0,
            'unparseable_dates' => 0,
            'financial_totals' => ['pr_amount' => 0, 'po_amount' => 0, 'vch_amount' => 0],
            'warnings' => [],
        ];

        $dateParser = new DateParser();

        foreach ($groups as $group) {
            $pr = $group['pr'] ?? null;
            $pos = $group['pos'] ?? [];
            $vchs = $group['vchs'] ?? [];

            if ($pr) {
                $report['procurements_to_create']++;
                $report['transactions_to_create']['pr']++;

                if (isset($pr->amount)) {
                    $report['financial_totals']['pr_amount'] += (float) $pr->amount;
                }

                $date = $dateParser->parse($pr->date_of_entry ?? null);
                if (!$date && !empty($pr->date_of_entry)) {
                    $report['unparseable_dates']++;
                }
            }

            if (!$pr && count($pos) > 0) {
                $report['orphaned_pos'] += count($pos);
                $report['warnings'][] = "PO(s) without PR: " . implode(', ', array_map(fn($po) => $po->reference_id, $pos));
            }

            foreach ($pos as $po) {
                $report['transactions_to_create']['po']++;
                if (isset($po->amount)) {
                    $report['financial_totals']['po_amount'] += (float) $po->amount;
                }
            }

            if (!$pr && count($pos) === 0 && count($vchs) > 0) {
                $report['orphaned_vchs'] += count($vchs);
                $report['warnings'][] = "VCH(s) without PR or PO: " . implode(', ', array_map(fn($vch) => $vch->reference_id, $vchs));
            }

            foreach ($vchs as $vch) {
                $report['transactions_to_create']['vch']++;
                if (isset($vch->amount)) {
                    $report['financial_totals']['vch_amount'] += (float) $vch->amount;
                }
            }
        }

        return $report;
    }

    public function generateValidationReport(MigrationImport $import): array
    {
        $records = $import->records();

        return [
            'counts' => [
                'source' => $import->total_source_records,
                'created' => $records->where('status', 'created')->count(),
                'skipped' => $records->where('status', 'skipped')->count(),
                'failed' => $records->where('status', 'failed')->count(),
            ],
            'financial_reconciliation' => $this->generateFinancialReconciliation($import),
            'orphans' => [
                'pos' => $import->dry_run_report['orphaned_pos'] ?? 0,
                'vchs' => $import->dry_run_report['orphaned_vchs'] ?? 0,
            ],
            'integrity_errors' => $this->checkIntegrity($import),
        ];
    }

    public function generateFinancialReconciliation(MigrationImport $import): array
    {
        $dryRun = $import->dry_run_report;
        $ettsTotal = ($dryRun['financial_totals']['pr_amount'] ?? 0)
            + ($dryRun['financial_totals']['po_amount'] ?? 0)
            + ($dryRun['financial_totals']['vch_amount'] ?? 0);

        // Sum OPTS2026 amounts from migrated procurements
        $optsTotal = DB::table('procurements')
            ->where('is_legacy', true)
            ->whereIn('id', function ($query) use ($import) {
                $query->select('target_id')
                    ->from('migration_records')
                    ->where('migration_import_id', $import->id)
                    ->where('target_table', 'procurements')
                    ->where('status', 'created');
            })
            ->sum('abc_amount');

        return [
            'etts_total' => round($ettsTotal, 2),
            'opts_total' => round((float) $optsTotal, 2),
            'difference' => round($ettsTotal - (float) $optsTotal, 2),
        ];
    }

    private function checkIntegrity(MigrationImport $import): array
    {
        $errors = [];

        // Check for orphaned migration records
        $orphanedRecords = MigrationRecord::where('migration_import_id', $import->id)
            ->where('target_table', 'procurements')
            ->where('status', 'created')
            ->whereNotIn('target_id', function ($query) {
                $query->select('id')->from('procurements');
            })
            ->count();

        if ($orphanedRecords > 0) {
            $errors[] = "Found {$orphanedRecords} migration records pointing to non-existent procurements.";
        }

        // Check for transactions missing current_office_id
        $missingOffice = DB::table('transactions')
            ->where('is_legacy', true)
            ->whereNull('current_office_id')
            ->whereIn('id', function ($query) use ($import) {
                $query->select('target_id')
                    ->from('migration_records')
                    ->where('migration_import_id', $import->id)
                    ->where('target_table', 'transactions')
                    ->where('status', 'created');
            })
            ->count();

        if ($missingOffice > 0) {
            $errors[] = "Found {$missingOffice} migrated transactions without a current office assignment.";
        }

        // Check for transactions missing workflow assignment
        $missingWorkflow = DB::table('transactions')
            ->where('is_legacy', true)
            ->whereNull('workflow_id')
            ->whereIn('id', function ($query) use ($import) {
                $query->select('target_id')
                    ->from('migration_records')
                    ->where('migration_import_id', $import->id)
                    ->where('target_table', 'transactions')
                    ->where('status', 'created');
            })
            ->count();

        if ($missingWorkflow > 0) {
            $errors[] = "Found {$missingWorkflow} migrated transactions without workflow assignment.";
        }

        // Check count consistency
        if ($import->migrated_count !== $import->records()->where('status', 'created')->count()) {
            $errors[] = 'Migrated count does not match actual created records.';
        }

        return $errors;
    }
}
