<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Transaction;
use Illuminate\Support\Carbon;

class EtaCalculationService
{
    protected int $idleThresholdDays;

    public function __construct()
    {
        $this->idleThresholdDays = (int) config('opts.idle_threshold_days', 2);
    }

    /**
     * Calculate ETA for current step completion.
     * Returns the date when the current step should be completed.
     */
    public function getCurrentStepEta(Transaction $transaction): ?Carbon
    {
        if ($transaction->status === 'Completed' || $transaction->status === 'Cancelled') {
            return null;
        }

        if (! $transaction->received_at || ! $transaction->currentStep) {
            return null;
        }

        $expectedDays = $transaction->currentStep->expected_days;

        return $this->addBusinessDays(
            Carbon::parse($transaction->received_at),
            $expectedDays
        );
    }

    /**
     * Calculate ETA for overall transaction completion.
     * Sums expected_days for all remaining steps from the current position.
     */
    public function getCompletionEta(Transaction $transaction): ?Carbon
    {
        if ($transaction->status === 'Completed' || $transaction->status === 'Cancelled') {
            return null;
        }

        $currentStepOrder = $transaction->currentStep?->step_order ?? 0;
        $workflow = $transaction->workflow;

        if (! $workflow) {
            return null;
        }

        $remainingSteps = $workflow->steps()
            ->where('step_order', '>=', $currentStepOrder)
            ->get();

        if ($remainingSteps->isEmpty()) {
            return null;
        }

        $startDate = $transaction->received_at
            ? Carbon::parse($transaction->received_at)
            : now();

        $totalDays = $remainingSteps->sum('expected_days');

        if ($transaction->received_at) {
            $daysSpent = $this->businessDaysBetween(
                Carbon::parse($transaction->received_at),
                now()
            );
            $totalDays = max(0, $totalDays - $daysSpent);
        }

        return $this->addBusinessDays(now(), $totalDays);
    }

    /**
     * Calculate delay in business days for current step.
     */
    public function getDelayDays(Transaction $transaction): int
    {
        $eta = $this->getCurrentStepEta($transaction);

        if (! $eta || now()->lte($eta)) {
            return 0;
        }

        return $this->businessDaysBetween($eta, now());
    }

    /**
     * Determine if transaction is stagnant.
     * Stagnant when delayed OR no movement for idle threshold days.
     */
    public function isStagnant(Transaction $transaction): bool
    {
        if ($transaction->status === 'Completed' || $transaction->status === 'Cancelled') {
            return false;
        }

        if ($this->getDelayDays($transaction) > 0) {
            return true;
        }

        $lastAction = $transaction->actions()->latest()->first();
        if (! $lastAction) {
            return false;
        }

        $daysSinceAction = $this->businessDaysBetween(
            Carbon::parse($lastAction->created_at),
            now()
        );

        return $daysSinceAction >= $this->idleThresholdDays;
    }

    /**
     * Get delay severity level.
     */
    public function getDelaySeverity(Transaction $transaction): string
    {
        if ($transaction->status === 'Completed' || $transaction->status === 'Cancelled') {
            return 'on_track';
        }

        $delayDays = $this->getDelayDays($transaction);

        return match (true) {
            $delayDays === 0 => 'on_track',
            $delayDays <= 2 => 'warning',
            default => 'overdue',
        };
    }

    /**
     * Get days at current step (business days since received_at).
     */
    public function getDaysAtCurrentStep(Transaction $transaction): int
    {
        if (! $transaction->received_at) {
            return 0;
        }

        if ($transaction->status === 'Completed' || $transaction->status === 'Cancelled') {
            return 0;
        }

        return $this->businessDaysBetween(
            Carbon::parse($transaction->received_at),
            now()
        );
    }

    /**
     * Calculate business days between two dates (excluding weekends).
     */
    public function businessDaysBetween(Carbon $start, Carbon $end): int
    {
        $days = 0;
        $current = $start->copy()->startOfDay();
        $endDay = $end->copy()->startOfDay();

        while ($current->lt($endDay)) {
            if (! $current->isWeekend()) {
                $days++;
            }
            $current->addDay();
        }

        return $days;
    }

    /**
     * Add business days to a date (skipping weekends).
     */
    public function addBusinessDays(Carbon $date, int $days): Carbon
    {
        $result = $date->copy();

        if ($days === 0) {
            return $result;
        }

        $added = 0;

        while ($added < $days) {
            $result->addDay();
            if (! $result->isWeekend()) {
                $added++;
            }
        }

        return $result;
    }
}
