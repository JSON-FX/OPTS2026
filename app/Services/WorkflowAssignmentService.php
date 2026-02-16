<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\NoActiveWorkflowException;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Workflow;

/**
 * Service for assigning workflows to transactions on creation.
 *
 * Story 3.11 - Workflow Assignment on Transaction Creation
 */
class WorkflowAssignmentService
{
    /**
     * Get the active workflow for a given category.
     *
     * @param  string  $category  PR|PO|VCH
     * @return Workflow|null The active workflow, or null if none exists
     */
    public function getActiveWorkflow(string $category): ?Workflow
    {
        return Workflow::where('category', $category)
            ->where('is_active', true)
            ->with('steps.office')
            ->orderByDesc('created_at')
            ->first();
    }

    /**
     * Check if an active workflow exists for a given category.
     *
     * @param  string  $category  PR|PO|VCH
     */
    public function hasActiveWorkflow(string $category): bool
    {
        return Workflow::where('category', $category)
            ->where('is_active', true)
            ->exists();
    }

    /**
     * Assign the active workflow to a transaction.
     *
     * Sets the workflow, first step, current office/user, and received_at timestamp.
     *
     * @param  Transaction  $transaction  The transaction to assign
     * @param  User  $user  The user creating the transaction
     *
     * @throws NoActiveWorkflowException If no active workflow exists for the category
     */
    public function assignWorkflow(Transaction $transaction, User $user): void
    {
        $workflow = $this->getActiveWorkflow($transaction->category);

        if (! $workflow) {
            throw new NoActiveWorkflowException(
                "No active workflow found for category: {$transaction->category}"
            );
        }

        $firstStep = $workflow->getFirstStep();

        $transaction->update([
            'workflow_id' => $workflow->id,
            'current_step_id' => $firstStep?->id,
            'current_office_id' => $user->office_id,
            'current_user_id' => $user->id,
            'received_at' => now(),
        ]);
    }

    /**
     * Get a preview of the workflow for a given category.
     *
     * Returns the workflow name, total steps, expected days, and step details
     * for display on the transaction creation form.
     *
     * @param  string  $category  PR|PO|VCH
     * @return array{workflow_id: int, workflow_name: string, total_steps: int, total_expected_days: int, steps: array}|null
     */
    public function getWorkflowPreview(string $category): ?array
    {
        $workflow = $this->getActiveWorkflow($category);

        if (! $workflow) {
            return null;
        }

        $steps = $workflow->steps()->orderBy('step_order')->with('office')->get();

        return [
            'workflow_id' => $workflow->id,
            'workflow_name' => $workflow->name,
            'total_steps' => $steps->count(),
            'total_expected_days' => $steps->sum('expected_days'),
            'steps' => $steps->map(fn ($step) => [
                'step_order' => $step->step_order,
                'office_name' => $step->office?->name ?? 'Unknown',
                'office_abbreviation' => $step->office?->abbreviation ?? 'Unknown',
                'expected_days' => $step->expected_days,
                'is_final_step' => $step->is_final_step,
            ])->toArray(),
        ];
    }
}
