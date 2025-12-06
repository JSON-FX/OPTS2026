<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Transaction;
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
                ]);
            }

            return $workflow->load('steps.office');
        });
    }

    /**
     * Update a workflow with its steps atomically.
     *
     * @param  array<string, mixed>  $workflowData
     * @param  array<int, array{office_id: int, expected_days: int}>  $stepsData
     */
    public function updateWithSteps(Workflow $workflow, array $workflowData, array $stepsData): Workflow
    {
        return DB::transaction(function () use ($workflow, $workflowData, $stepsData) {
            $workflow->update($workflowData);

            // Delete existing steps and recreate
            $workflow->steps()->delete();

            $stepCount = count($stepsData);
            foreach ($stepsData as $index => $stepData) {
                $workflow->steps()->create([
                    'office_id' => $stepData['office_id'],
                    'expected_days' => $stepData['expected_days'],
                    'step_order' => $index + 1,
                    'is_final_step' => $index === $stepCount - 1,
                ]);
            }

            return $workflow->load('steps.office');
        });
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
