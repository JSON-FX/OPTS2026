<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Transaction;
use App\Models\TransactionAction;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DashboardService
{
    public function __construct(
        private readonly EtaCalculationService $etaService,
    ) {}

    /**
     * Load all active transactions with relationships needed for stagnant detection.
     * Call once and pass to getOfficeWorkload() and getStagnantTransactions().
     *
     * @return EloquentCollection<int, Transaction>
     */
    public function loadActiveTransactions(): EloquentCollection
    {
        return Transaction::query()
            ->whereIn('status', ['Created', 'In Progress'])
            ->whereNotNull('current_office_id')
            ->with([
                'currentStep',
                'workflow.steps',
                'latestAction',
                'purchaseRequest:id,transaction_id',
                'purchaseOrder:id,transaction_id',
                'voucher:id,transaction_id',
            ])
            ->get();
    }

    /**
     * Get summary card counts for procurements and each transaction category.
     *
     * @return array{procurements: array, purchase_requests: array, purchase_orders: array, vouchers: array}
     */
    public function getSummaryCards(): array
    {
        $procurementCounts = DB::table('procurements')
            ->select('status', DB::raw('COUNT(*) as count'))
            ->whereNull('deleted_at')
            ->groupBy('status')
            ->pluck('count', 'status');

        $transactionCounts = DB::table('transactions')
            ->select('category', 'status', DB::raw('COUNT(*) as count'))
            ->whereNull('deleted_at')
            ->groupBy('category', 'status')
            ->get();

        return [
            'procurements' => $this->buildStatusCounts($procurementCounts),
            'purchase_requests' => $this->buildStatusCounts(
                $transactionCounts->where('category', 'PR')->pluck('count', 'status')
            ),
            'purchase_orders' => $this->buildStatusCounts(
                $transactionCounts->where('category', 'PO')->pluck('count', 'status')
            ),
            'vouchers' => $this->buildStatusCounts(
                $transactionCounts->where('category', 'VCH')->pluck('count', 'status')
            ),
        ];
    }

    /**
     * Get office workload data: transaction counts per office for active transactions.
     *
     * @param  EloquentCollection<int, Transaction>|null  $activeTransactions  Pre-loaded transactions to avoid duplicate queries.
     * @return Collection<int, object>
     */
    public function getOfficeWorkload(?EloquentCollection $activeTransactions = null): Collection
    {
        // Get all distinct offices from active workflow steps
        $workflowOffices = DB::table('workflow_steps')
            ->join('workflows', 'workflow_steps.workflow_id', '=', 'workflows.id')
            ->join('offices', 'workflow_steps.office_id', '=', 'offices.id')
            ->where('workflows.is_active', true)
            ->select(
                'offices.id as office_id',
                'offices.name as office_name',
                'offices.abbreviation as office_abbreviation'
            )
            ->distinct()
            ->get();

        if ($workflowOffices->isEmpty()) {
            return collect();
        }

        // Get transaction counts per office for active transactions
        $transactionCounts = DB::table('transactions')
            ->select(
                'current_office_id as office_id',
                DB::raw("SUM(CASE WHEN category = 'PR' THEN 1 ELSE 0 END) as pr_count"),
                DB::raw("SUM(CASE WHEN category = 'PO' THEN 1 ELSE 0 END) as po_count"),
                DB::raw("SUM(CASE WHEN category = 'VCH' THEN 1 ELSE 0 END) as vch_count"),
                DB::raw('COUNT(*) as total')
            )
            ->whereNull('deleted_at')
            ->whereIn('status', ['Created', 'In Progress'])
            ->groupBy('current_office_id')
            ->get()
            ->keyBy('office_id');

        // Compute stagnant counts per office using pre-loaded transactions
        $stagnantCounts = $this->getStagnantCountsByOffice($activeTransactions);

        // Merge: all workflow offices with their transaction counts (defaulting to 0)
        return $workflowOffices->map(function ($office) use ($transactionCounts, $stagnantCounts) {
            $counts = $transactionCounts[(int) $office->office_id] ?? null;

            return (object) [
                'office_id' => (int) $office->office_id,
                'office_name' => $office->office_name,
                'office_abbreviation' => $office->office_abbreviation,
                'pr_count' => (int) ($counts->pr_count ?? 0),
                'po_count' => (int) ($counts->po_count ?? 0),
                'vch_count' => (int) ($counts->vch_count ?? 0),
                'total' => (int) ($counts->total ?? 0),
                'stagnant_count' => $stagnantCounts[(int) $office->office_id] ?? 0,
            ];
        })
            ->sortBy('office_name')
            ->sortByDesc('total')
            ->values();
    }

    /**
     * Get recent activity feed entries.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getRecentActivity(int $limit = 20): array
    {
        return TransactionAction::query()
            ->with([
                'fromUser:id,name',
                'fromOffice:id,name,abbreviation',
                'toOffice:id,name,abbreviation',
                'transaction:id,reference_number,category',
                'transaction.purchaseRequest:id,transaction_id',
                'transaction.purchaseOrder:id,transaction_id',
                'transaction.voucher:id,transaction_id',
            ])
            ->select('id', 'transaction_id', 'action_type', 'from_user_id', 'from_office_id', 'to_office_id', 'is_out_of_workflow', 'created_at')
            ->whereIn('action_type', ['endorse', 'receive', 'complete'])
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->map(fn (TransactionAction $action) => [
                'id' => $action->id,
                'action_type' => $action->action_type,
                'transaction_reference_number' => $action->transaction->reference_number,
                'transaction_id' => $action->transaction_id,
                'transaction_category' => $action->transaction->category,
                'actor_name' => $action->fromUser->name,
                'from_office' => $action->fromOffice?->abbreviation,
                'to_office' => $action->toOffice?->abbreviation,
                'is_out_of_workflow' => $action->is_out_of_workflow,
                'purchase_request_id' => $action->transaction->purchaseRequest?->id,
                'purchase_order_id' => $action->transaction->purchaseOrder?->id,
                'voucher_id' => $action->transaction->voucher?->id,
                'created_at' => $action->created_at->diffForHumans(),
            ])
            ->all();
    }

    /**
     * Get stagnant transactions for the dashboard panel.
     *
     * @param  EloquentCollection<int, Transaction>|null  $activeTransactions  Pre-loaded transactions to avoid duplicate queries.
     * @return array<int, array<string, mixed>>
     */
    public function getStagnantTransactions(?EloquentCollection $activeTransactions = null, int $limit = 10): array
    {
        $activeTransactions ??= $this->loadActiveTransactions();

        $officeIds = $activeTransactions->pluck('current_office_id')->unique()->filter()->all();
        $officeNames = DB::table('offices')
            ->whereIn('id', $officeIds)
            ->pluck('name', 'id');

        return $activeTransactions
            ->filter(fn (Transaction $tx) => $this->etaService->isStagnant($tx))
            ->map(fn (Transaction $tx) => [
                'id' => $tx->id,
                'reference_number' => $tx->reference_number,
                'category' => $tx->category,
                'current_office_name' => $officeNames[$tx->current_office_id] ?? 'Unknown',
                'delay_days' => $this->etaService->getDelayDays($tx),
                'delay_severity' => $this->etaService->getDelaySeverity($tx),
                'days_at_current_step' => $this->etaService->getDaysAtCurrentStep($tx),
                'purchase_request_id' => $tx->purchaseRequest?->id,
                'purchase_order_id' => $tx->purchaseOrder?->id,
                'voucher_id' => $tx->voucher?->id,
            ])
            ->sortByDesc('delay_days')
            ->take($limit)
            ->values()
            ->all();
    }

    /**
     * Get office performance metrics for SLA panel.
     * Calculates average turnaround time per office from receive→endorse/complete pairs.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getOfficePerformance(int $days = 30): array
    {
        $cutoff = now()->subDays($days);

        // Get receive actions within the period
        $receives = DB::table('transaction_actions')
            ->where('action_type', 'receive')
            ->where('created_at', '>=', $cutoff)
            ->whereNotNull('to_office_id')
            ->select('id', 'transaction_id', 'to_office_id', 'created_at')
            ->orderBy('id')
            ->get();

        if ($receives->isEmpty()) {
            return [];
        }

        // Get all endorse/complete actions for the same transactions
        $transactionIds = $receives->pluck('transaction_id')->unique()->all();
        $responses = DB::table('transaction_actions')
            ->whereIn('transaction_id', $transactionIds)
            ->whereIn('action_type', ['endorse', 'complete'])
            ->whereNotNull('from_office_id')
            ->select('id', 'transaction_id', 'from_office_id', 'created_at')
            ->orderBy('id')
            ->get();

        // Match receive→endorse/complete pairs and compute turnaround per office
        $officeTurnarounds = [];
        foreach ($receives as $receive) {
            // Find the next endorse/complete from the same office on same transaction
            $nextAction = $responses->first(function ($action) use ($receive) {
                return $action->transaction_id === $receive->transaction_id
                    && $action->from_office_id === $receive->to_office_id
                    && $action->id > $receive->id;
            });

            if ($nextAction) {
                $officeId = (int) $receive->to_office_id;
                $calendarDays = $this->etaService->businessDaysBetween(
                    \Illuminate\Support\Carbon::parse($receive->created_at),
                    \Illuminate\Support\Carbon::parse($nextAction->created_at)
                );

                if (! isset($officeTurnarounds[$officeId])) {
                    $officeTurnarounds[$officeId] = ['total_days' => 0, 'count' => 0];
                }
                $officeTurnarounds[$officeId]['total_days'] += $calendarDays;
                $officeTurnarounds[$officeId]['count']++;
            }
        }

        if (empty($officeTurnarounds)) {
            return [];
        }

        // Get office details
        $officeDetails = DB::table('offices')
            ->whereIn('id', array_keys($officeTurnarounds))
            ->select('id', 'name', 'abbreviation')
            ->get()
            ->keyBy('id');

        // Get expected_days per office from active workflow_steps
        $expectedDaysByOffice = DB::table('workflow_steps')
            ->join('workflows', 'workflow_steps.workflow_id', '=', 'workflows.id')
            ->where('workflows.is_active', true)
            ->select('workflow_steps.office_id', DB::raw('AVG(workflow_steps.expected_days) as avg_expected'))
            ->groupBy('workflow_steps.office_id')
            ->pluck('avg_expected', 'office_id');

        $results = [];
        foreach ($officeTurnarounds as $officeId => $data) {
            $office = $officeDetails[$officeId] ?? null;
            if (! $office) {
                continue;
            }

            $avgBusinessDays = round($data['total_days'] / $data['count'], 1);
            $expectedDays = (float) ($expectedDaysByOffice[$officeId] ?? 3);

            $rating = match (true) {
                $avgBusinessDays <= $expectedDays => 'good',
                $avgBusinessDays <= $expectedDays * 1.5 => 'warning',
                default => 'poor',
            };

            $results[] = [
                'office_id' => $officeId,
                'office_name' => $office->name,
                'office_abbreviation' => $office->abbreviation,
                'avg_turnaround_days' => $avgBusinessDays,
                'expected_days' => round($expectedDays, 1),
                'performance_rating' => $rating,
                'actions_count' => $data['count'],
            ];
        }

        return $results;
    }

    /**
     * Get out-of-workflow incident summary for current and previous month.
     *
     * @return array{current_month: int, previous_month: int, trend_percentage: float}
     */
    public function getIncidentSummary(): array
    {
        $currentMonthStart = now()->startOfMonth();
        $previousMonthStart = now()->subMonth()->startOfMonth();
        $previousMonthEnd = now()->subMonth()->endOfMonth();

        $currentMonth = (int) DB::table('transaction_actions')
            ->where('is_out_of_workflow', true)
            ->where('created_at', '>=', $currentMonthStart)
            ->count();

        $previousMonth = (int) DB::table('transaction_actions')
            ->where('is_out_of_workflow', true)
            ->whereBetween('created_at', [$previousMonthStart, $previousMonthEnd])
            ->count();

        $trendPercentage = $previousMonth > 0
            ? round(($currentMonth - $previousMonth) / $previousMonth * 100, 1)
            : ($currentMonth > 0 ? 100.0 : 0.0);

        return [
            'current_month' => $currentMonth,
            'previous_month' => $previousMonth,
            'trend_percentage' => $trendPercentage,
        ];
    }

    /**
     * Get transaction volume summary by category for current and previous month.
     *
     * @return array<int, array{category: string, current_month: int, previous_month: int, trend_percentage: float}>
     */
    public function getVolumeSummary(): array
    {
        $currentMonthStart = now()->startOfMonth();
        $previousMonthStart = now()->subMonth()->startOfMonth();
        $previousMonthEnd = now()->subMonth()->endOfMonth();

        $currentCounts = DB::table('transactions')
            ->whereNull('deleted_at')
            ->where('created_at', '>=', $currentMonthStart)
            ->select('category', DB::raw('COUNT(*) as count'))
            ->groupBy('category')
            ->pluck('count', 'category');

        $previousCounts = DB::table('transactions')
            ->whereNull('deleted_at')
            ->whereBetween('created_at', [$previousMonthStart, $previousMonthEnd])
            ->select('category', DB::raw('COUNT(*) as count'))
            ->groupBy('category')
            ->pluck('count', 'category');

        $categories = ['PR', 'PO', 'VCH'];

        return collect($categories)->map(function ($category) use ($currentCounts, $previousCounts) {
            $current = (int) ($currentCounts[$category] ?? 0);
            $previous = (int) ($previousCounts[$category] ?? 0);

            $trendPercentage = $previous > 0
                ? round(($current - $previous) / $previous * 100, 1)
                : ($current > 0 ? 100.0 : 0.0);

            return [
                'category' => $category,
                'current_month' => $current,
                'previous_month' => $previous,
                'trend_percentage' => $trendPercentage,
            ];
        })->all();
    }

    /**
     * Build a status counts array from a plucked collection.
     *
     * @param  Collection<string, int>|\Illuminate\Support\Collection  $counts
     * @return array{total: int, created: int, in_progress: int, completed: int, on_hold: int, cancelled: int}
     */
    private function buildStatusCounts($counts): array
    {
        $created = (int) ($counts['Created'] ?? 0);
        $inProgress = (int) ($counts['In Progress'] ?? 0);
        $completed = (int) ($counts['Completed'] ?? 0);
        $onHold = (int) ($counts['On Hold'] ?? 0);
        $cancelled = (int) ($counts['Cancelled'] ?? 0);

        return [
            'total' => $created + $inProgress + $completed + $onHold + $cancelled,
            'created' => $created,
            'in_progress' => $inProgress,
            'completed' => $completed,
            'on_hold' => $onHold,
            'cancelled' => $cancelled,
        ];
    }

    /**
     * Get stagnant transaction counts grouped by office.
     * Uses EtaCalculationService for accurate stagnant detection.
     *
     * @param  EloquentCollection<int, Transaction>|null  $activeTransactions  Pre-loaded transactions to avoid duplicate queries.
     * @return array<int, int>
     */
    private function getStagnantCountsByOffice(?EloquentCollection $activeTransactions = null): array
    {
        $activeTransactions ??= $this->loadActiveTransactions();

        $counts = [];
        foreach ($activeTransactions as $transaction) {
            if ($this->etaService->isStagnant($transaction)) {
                $officeId = (int) $transaction->current_office_id;
                $counts[$officeId] = ($counts[$officeId] ?? 0) + 1;
            }
        }

        return $counts;
    }
}
