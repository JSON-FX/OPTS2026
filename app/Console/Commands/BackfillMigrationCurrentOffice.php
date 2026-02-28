<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\MigrationRecord;
use App\Models\Transaction;
use App\Services\WorkflowAssignmentService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackfillMigrationCurrentOffice extends Command
{
    protected $signature = 'app:backfill-migration-current-office
                            {--import-id= : Specific migration import ID to process}
                            {--dry-run : Show what would be updated without making changes}';

    protected $description = 'Backfill current_office_id and workflow assignment for legacy migrated transactions';

    public function handle(WorkflowAssignmentService $workflowService): int
    {
        $this->backfillOffices();
        $this->backfillWorkflows($workflowService);

        return self::SUCCESS;
    }

    private function backfillOffices(): void
    {
        $query = Transaction::where('is_legacy', true)
            ->whereNull('current_office_id');

        if ($importId = $this->option('import-id')) {
            $transactionIds = MigrationRecord::where('migration_import_id', $importId)
                ->where('target_table', 'transactions')
                ->where('status', 'created')
                ->pluck('target_id');
            $query->whereIn('id', $transactionIds);
        }

        $transactions = $query->get();

        if ($transactions->isEmpty()) {
            $this->info('No legacy transactions need office backfilling.');

            return;
        }

        $this->info("Found {$transactions->count()} legacy transaction(s) with NULL current_office_id.");

        $updated = 0;
        $skipped = 0;
        $isDryRun = $this->option('dry-run');

        foreach ($transactions as $transaction) {
            $lastEndorsement = DB::table('transaction_actions')
                ->where('transaction_id', $transaction->id)
                ->where('action_type', 'endorse')
                ->whereNotNull('to_office_id')
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->first();

            $officeId = null;
            $source = 'none';

            if ($lastEndorsement) {
                $officeId = $lastEndorsement->to_office_id;
                $source = 'last endorsement';
            } else {
                $officeId = $transaction->procurement?->end_user_id;
                $source = 'procurement end_user_id';
            }

            if ($officeId) {
                $officeName = DB::table('offices')->where('id', $officeId)->value('name') ?? 'Unknown';

                if ($isDryRun) {
                    $this->line("  [DRY RUN] #{$transaction->id} ({$transaction->reference_number}): would set office #{$officeId} ({$officeName}) via {$source}");
                } else {
                    $transaction->update(['current_office_id' => $officeId]);
                    $this->line("  #{$transaction->id} ({$transaction->reference_number}): set office #{$officeId} ({$officeName}) via {$source}");
                }
                $updated++;
            } else {
                $this->warn("  #{$transaction->id} ({$transaction->reference_number}): no office determined. Skipped.");
                $skipped++;
            }
        }

        $prefix = $this->option('dry-run') ? '[DRY RUN] ' : '';
        $this->info("{$prefix}Office backfill done. Updated: {$updated}, Skipped: {$skipped}.");
    }

    private function backfillWorkflows(WorkflowAssignmentService $workflowService): void
    {
        $query = Transaction::where('is_legacy', true)
            ->whereNull('workflow_id');

        if ($importId = $this->option('import-id')) {
            $transactionIds = MigrationRecord::where('migration_import_id', $importId)
                ->where('target_table', 'transactions')
                ->where('status', 'created')
                ->pluck('target_id');
            $query->whereIn('id', $transactionIds);
        }

        $transactions = $query->get();

        if ($transactions->isEmpty()) {
            $this->info('No legacy transactions need workflow backfilling.');

            return;
        }

        $this->info("Found {$transactions->count()} legacy transaction(s) with NULL workflow_id.");

        // Cache active workflows per category
        $workflows = [];
        foreach (['PR', 'PO', 'VCH'] as $category) {
            $workflows[$category] = $workflowService->getActiveWorkflow($category);
        }

        $assigned = 0;
        $noStep = 0;
        $noWorkflow = 0;
        $isDryRun = $this->option('dry-run');

        foreach ($transactions as $transaction) {
            $workflow = $workflows[$transaction->category] ?? null;
            if (!$workflow) {
                $this->warn("  #{$transaction->id} ({$transaction->reference_number}): no active workflow for {$transaction->category}. Skipped.");
                $noWorkflow++;

                continue;
            }

            // Find workflow step matching the transaction's current office
            $matchingStep = null;
            if ($transaction->current_office_id) {
                $matchingStep = $workflow->steps()
                    ->where('office_id', $transaction->current_office_id)
                    ->orderBy('step_order')
                    ->first();
            }

            $stepInfo = $matchingStep
                ? "step #{$matchingStep->step_order} ({$matchingStep->office?->abbreviation})"
                : 'no matching step';

            if ($isDryRun) {
                $this->line("  [DRY RUN] #{$transaction->id} ({$transaction->reference_number}): would assign workflow '{$workflow->name}', {$stepInfo}");
            } else {
                $transaction->update([
                    'workflow_id' => $workflow->id,
                    'current_step_id' => $matchingStep?->id,
                    'received_at' => $transaction->received_at ?? $transaction->created_at,
                ]);
                $this->line("  #{$transaction->id} ({$transaction->reference_number}): assigned workflow '{$workflow->name}', {$stepInfo}");
            }

            $assigned++;
            if (!$matchingStep) {
                $noStep++;
            }
        }

        $prefix = $isDryRun ? '[DRY RUN] ' : '';
        $this->info("{$prefix}Workflow backfill done. Assigned: {$assigned}, No matching step: {$noStep}, No workflow: {$noWorkflow}.");
    }
}
