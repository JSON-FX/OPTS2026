<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Transaction;
use App\Models\User;
use App\Services\WorkflowAssignmentService;
use Illuminate\Console\Command;

class BackfillTransactionWorkflows extends Command
{
    protected $signature = 'app:backfill-transaction-workflows';

    protected $description = 'Backfill workflow assignment fields for existing transactions missing them';

    public function handle(WorkflowAssignmentService $service): int
    {
        $transactions = Transaction::where(function ($query) {
            $query->whereNotNull('workflow_id')
                ->whereNull('current_step_id');
        })->orWhere(function ($query) {
            $query->whereNull('workflow_id');
        })->get();

        if ($transactions->isEmpty()) {
            $this->info('No transactions need backfilling.');

            return self::SUCCESS;
        }

        $this->info("Found {$transactions->count()} transaction(s) to backfill.");

        $updated = 0;
        $failed = 0;

        foreach ($transactions as $transaction) {
            $creator = User::find($transaction->created_by_user_id);

            if (! $creator) {
                $this->warn("Transaction #{$transaction->id}: Creator user #{$transaction->created_by_user_id} not found. Skipping.");
                $failed++;

                continue;
            }

            $workflow = $transaction->workflow_id
                ? \App\Models\Workflow::with('steps.office')->find($transaction->workflow_id)
                : $service->getActiveWorkflow($transaction->category);

            if (! $workflow) {
                $this->warn("Transaction #{$transaction->id}: No workflow found for category '{$transaction->category}'. Skipping.");
                $failed++;

                continue;
            }

            $firstStep = $workflow->getFirstStep();

            if (! $firstStep) {
                $this->warn("Transaction #{$transaction->id}: Workflow '{$workflow->name}' has no first step. Skipping.");
                $failed++;

                continue;
            }

            $transaction->update([
                'workflow_id' => $workflow->id,
                'current_step_id' => $firstStep->id,
                'current_office_id' => $creator->office_id,
                'current_user_id' => $creator->id,
                'received_at' => $transaction->created_at,
            ]);

            $this->line("  Transaction #{$transaction->id} ({$transaction->reference_number}): assigned workflow '{$workflow->name}', step #{$firstStep->step_order}");
            $updated++;
        }

        $this->info("Done. Updated: {$updated}, Skipped: {$failed}.");

        return self::SUCCESS;
    }
}
