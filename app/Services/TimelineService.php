<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Carbon;

class TimelineService
{
    public function __construct(
        protected EtaCalculationService $etaService
    ) {}

    /**
     * Build timeline data for a transaction.
     *
     * @return array{steps: list<array>, progress_percentage: int, total_steps: int, completed_steps: int, is_out_of_workflow: bool}
     */
    public function getTimeline(Transaction $transaction): array
    {
        $workflow = $transaction->workflow;
        if (! $workflow) {
            return $this->getEmptyTimeline();
        }

        $steps = $workflow->steps()->ordered()->with('office')->get();
        $currentStepOrder = $transaction->currentStep?->step_order ?? 0;

        // Get receive actions keyed by workflow_step_id for completed step data
        $receiveActions = $transaction->actions()
            ->whereIn('action_type', ['receive', 'endorse'])
            ->with(['fromUser:id,name', 'toUser:id,name', 'toOffice:id,name'])
            ->orderBy('created_at')
            ->get();

        $timelineSteps = [];
        $completedCount = 0;
        $isCompleted = $transaction->status === 'Completed';
        $isCancelled = $transaction->status === 'Cancelled';

        // For upcoming steps, calculate running ETA from current step ETA
        $runningEta = $transaction->eta_current_step
            ? Carbon::parse($transaction->eta_current_step)
            : now();

        foreach ($steps as $step) {
            if ($isCompleted || $isCancelled) {
                // All steps are completed for a completed transaction
                $action = $receiveActions->where('workflow_step_id', $step->id)->first();
                $timelineSteps[] = [
                    'step_order' => $step->step_order,
                    'office' => $step->office,
                    'expected_days' => $step->expected_days,
                    'is_final_step' => $step->is_final_step,
                    'status' => 'completed',
                    'completed_at' => $action?->created_at?->toDateTimeString(),
                    'completed_by' => $action?->fromUser,
                    'actual_days' => null,
                ];
                $completedCount++;
            } elseif ($step->step_order < $currentStepOrder) {
                // Completed step
                $action = $receiveActions->where('workflow_step_id', $step->id)->first();

                // Calculate actual days spent at this step
                $actualDays = $this->calculateActualDays($step->id, $receiveActions);

                $timelineSteps[] = [
                    'step_order' => $step->step_order,
                    'office' => $step->office,
                    'expected_days' => $step->expected_days,
                    'is_final_step' => $step->is_final_step,
                    'status' => 'completed',
                    'completed_at' => $action?->created_at?->toDateTimeString(),
                    'completed_by' => $action?->fromUser,
                    'actual_days' => $actualDays,
                ];
                $completedCount++;
            } elseif ($step->step_order === $currentStepOrder) {
                // Current step
                $currentUser = $transaction->current_user_id
                    ? User::select(['id', 'name'])->find($transaction->current_user_id)
                    : null;

                $timelineSteps[] = [
                    'step_order' => $step->step_order,
                    'office' => $step->office,
                    'expected_days' => $step->expected_days,
                    'is_final_step' => $step->is_final_step,
                    'status' => 'current',
                    'current_holder' => $currentUser,
                    'days_at_step' => $transaction->days_at_current_step,
                    'eta' => $transaction->eta_current_step,
                    'is_overdue' => $transaction->delay_days > 0,
                ];
            } else {
                // Upcoming step
                $runningEta = $this->etaService->addBusinessDays($runningEta, $step->expected_days);
                $timelineSteps[] = [
                    'step_order' => $step->step_order,
                    'office' => $step->office,
                    'expected_days' => $step->expected_days,
                    'is_final_step' => $step->is_final_step,
                    'status' => 'upcoming',
                    'estimated_arrival' => $runningEta->toDateString(),
                ];
            }
        }

        $totalSteps = $steps->count();

        return [
            'steps' => $timelineSteps,
            'progress_percentage' => $totalSteps > 0
                ? (int) round(($completedCount / $totalSteps) * 100)
                : 0,
            'total_steps' => $totalSteps,
            'completed_steps' => $completedCount,
            'is_out_of_workflow' => $transaction->actions()
                ->where('is_out_of_workflow', true)
                ->exists(),
        ];
    }

    /**
     * Get action history for a transaction.
     *
     * @return list<array>
     */
    public function getActionHistory(Transaction $transaction): array
    {
        return $transaction->actions()
            ->with([
                'fromUser:id,name',
                'toUser:id,name',
                'fromOffice:id,name,abbreviation',
                'toOffice:id,name,abbreviation',
                'actionTaken:id,description',
                'workflowStep:id,step_order',
            ])
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn ($action) => [
                'id' => $action->id,
                'action_type' => $action->action_type,
                'from_user' => $action->fromUser ? ['id' => $action->fromUser->id, 'name' => $action->fromUser->name] : null,
                'to_user' => $action->toUser ? ['id' => $action->toUser->id, 'name' => $action->toUser->name] : null,
                'from_office' => $action->fromOffice ? ['id' => $action->fromOffice->id, 'name' => $action->fromOffice->name, 'abbreviation' => $action->fromOffice->abbreviation] : null,
                'to_office' => $action->toOffice ? ['id' => $action->toOffice->id, 'name' => $action->toOffice->name, 'abbreviation' => $action->toOffice->abbreviation] : null,
                'action_taken' => $action->actionTaken?->description,
                'notes' => $action->notes,
                'reason' => $action->reason,
                'is_out_of_workflow' => $action->is_out_of_workflow,
                'workflow_step_order' => $action->workflowStep?->step_order,
                'created_at' => $action->created_at->toDateTimeString(),
            ])
            ->all();
    }

    /**
     * Calculate actual business days spent at a step using action timestamps.
     */
    private function calculateActualDays(int $stepId, $actions): ?int
    {
        // Find the receive action for this step (when it arrived)
        $receiveAction = $actions->where('workflow_step_id', $stepId)
            ->whereIn('action_type', ['receive', 'endorse'])
            ->sortBy('created_at')
            ->first();

        if (! $receiveAction) {
            return null;
        }

        // Find the next action after this (endorse from this step = departure)
        $departureAction = $actions
            ->where('created_at', '>', $receiveAction->created_at)
            ->sortBy('created_at')
            ->first();

        if (! $departureAction) {
            return null;
        }

        return $this->etaService->businessDaysBetween(
            Carbon::parse($receiveAction->created_at),
            Carbon::parse($departureAction->created_at)
        );
    }

    /**
     * @return array{steps: list<never>, progress_percentage: int, total_steps: int, completed_steps: int, is_out_of_workflow: bool}
     */
    private function getEmptyTimeline(): array
    {
        return [
            'steps' => [],
            'progress_percentage' => 0,
            'total_steps' => 0,
            'completed_steps' => 0,
            'is_out_of_workflow' => false,
        ];
    }
}
