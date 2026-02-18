<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Transaction;
use App\Models\TransactionAction;
use App\Models\Workflow;
use Illuminate\Support\Facades\DB;

class WorkflowService
{
    /**
     * Create a workflow with its steps atomically.
     *
     * @param  array<string, mixed>  $workflowData
     * @param  array<int, array{office_id: int, expected_days: int}>  $stepsData
     */
    public function createWithSteps(array $workflowData, array $stepsData): Workflow
    {
        return DB::transaction(function () use ($workflowData, $stepsData) {
            $workflow = Workflow::create($workflowData);

            $stepCount = count($stepsData);
            foreach ($stepsData as $index => $stepData) {
                $workflow->steps()->create([
                    'office_id' => $stepData['office_id'],
                    'expected_days' => $stepData['expected_days'],
                    'step_order' => $index + 1,
                    'is_final_step' => $index === $stepCount - 1,
                    'action_taken_id' => $stepData['action_taken_id'] ?? null,
                ]);
            }

            return $workflow->load('steps.office');
        });
    }

    /**
     * Update a workflow with its steps atomically using positional sync.
     *
     * Matches incoming steps to existing steps by step_order (position)
     * to preserve step IDs where possible, keeping FK references
     * (transactions.current_step_id, transaction_actions.workflow_step_id) intact.
     *
     * @param  array<string, mixed>  $workflowData
     * @param  array<int, array{office_id: int, expected_days: int, action_taken_id?: int|null}>  $stepsData
     */
    public function updateWithSteps(Workflow $workflow, array $workflowData, array $stepsData): Workflow
    {
        return DB::transaction(function () use ($workflow, $workflowData, $stepsData) {
            $workflow->update($workflowData);

            $stepCount = count($stepsData);
            $usedExistingIds = [];

            // Temporarily offset all step_order values to avoid
            // UNIQUE(workflow_id, step_order) violations during reordering.
            $workflow->steps()->update(['step_order' => DB::raw('step_order + 10000')]);

            // Load existing steps AFTER offset, keyed by original position.
            // Subtract 10000 to get the original step_order for matching.
            $existingSteps = $workflow->steps()->orderBy('step_order')->get()
                ->keyBy(fn ($step) => $step->step_order - 10000);

            // Update existing steps (by position) and create new ones
            foreach ($stepsData as $index => $stepData) {
                $position = $index + 1;
                $existing = $existingSteps->get($position);

                if ($existing) {
                    // Update in place — preserves the step ID and all FK references
                    $existing->update([
                        'office_id' => $stepData['office_id'],
                        'step_order' => $position,
                        'expected_days' => $stepData['expected_days'],
                        'is_final_step' => $index === $stepCount - 1,
                        'action_taken_id' => $stepData['action_taken_id'] ?? null,
                    ]);
                    $usedExistingIds[] = $existing->id;
                } else {
                    // New step (workflow grew)
                    $workflow->steps()->create([
                        'office_id' => $stepData['office_id'],
                        'expected_days' => $stepData['expected_days'],
                        'step_order' => $position,
                        'is_final_step' => $index === $stepCount - 1,
                        'action_taken_id' => $stepData['action_taken_id'] ?? null,
                    ]);
                }
            }

            // Remove excess steps (workflow shrank)
            $stepsToRemove = $existingSteps->filter(
                fn ($step) => ! in_array($step->id, $usedExistingIds)
            );

            foreach ($stepsToRemove as $step) {
                $this->removeOrphanStep($step);
            }

            return $workflow->load('steps.office');
        });
    }

    /**
     * Remove a workflow step that is no longer in the workflow.
     *
     * Nullifies historical transaction_actions references, nullifies
     * current_step_id for completed/cancelled transactions, and deletes
     * the step. Active transactions should have been caught by validation.
     */
    private function removeOrphanStep(\App\Models\WorkflowStep $step): void
    {
        // Nullify historical action references (audit trail keeps the action but loses step link)
        TransactionAction::where('workflow_step_id', $step->id)
            ->update(['workflow_step_id' => null]);

        // Nullify current_step_id for completed/cancelled transactions
        Transaction::where('current_step_id', $step->id)
            ->whereIn('status', ['Completed', 'Cancelled'])
            ->update(['current_step_id' => null]);

        $step->delete();
    }

    /**
     * Check if a workflow can be deactivated.
     * Returns false if this is the only active workflow for its category.
     */
    public function canDeactivate(Workflow $workflow): bool
    {
        if (! $workflow->is_active) {
            return true; // Already inactive
        }

        $activeCount = Workflow::where('category', $workflow->category)
            ->where('is_active', true)
            ->where('id', '!=', $workflow->id)
            ->count();

        return $activeCount > 0;
    }

    /**
     * Check if a workflow can be deleted (hard delete).
     * Returns false if any transactions reference this workflow.
     */
    public function canDelete(Workflow $workflow): bool
    {
        return ! Transaction::where('workflow_id', $workflow->id)->exists();
    }

    /**
     * Check if a workflow has active (non-completed) transactions.
     */
    public function hasActiveTransactions(Workflow $workflow): bool
    {
        return Transaction::where('workflow_id', $workflow->id)
            ->whereNotIn('status', ['Completed', 'Cancelled'])
            ->exists();
    }
}
