<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Office;
use App\Models\Transaction;
use App\Models\TransactionAction;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DashboardService
{
    public function __construct(
        private readonly EtaCalculationService $etaService,
        private readonly EndorsementService $endorsementService,
    ) {}

    /**
     * Load all active transactions with relationships needed for stagnant detection.
     * Call once and pass to getOfficeWorkload() and getStagnantTransactions().
     *
     * @return EloquentCollection<int, Transaction>
     */
    public function loadActiveTransactions(?int $year = null, ?int $officeId = null): EloquentCollection
    {
        return Transaction::query()
            ->whereHas('procurement')
            ->whereIn('status', ['Created', 'In Progress'])
            ->whereNotNull('current_office_id')
            ->when($officeId, fn ($q) => $q->where('current_office_id', $officeId))
            ->when($year, fn ($q) => $q->whereYear('created_at', $year))
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
    public function getSummaryCards(?int $year = null, ?int $officeId = null): array
    {
        $procurementCounts = DB::table('procurements')
            ->when($officeId, fn ($q) => $q->whereExists(fn ($tx) => $tx
                ->selectRaw('1')->from('transactions')
                ->whereColumn('transactions.procurement_id', 'procurements.id')
                ->whereNull('transactions.deleted_at')
                ->where('transactions.current_office_id', $officeId)
                ->when($year, fn ($tx) => $tx->whereYear('transactions.created_at', $year))))
            ->select('status', DB::raw('COUNT(*) as count'))
            ->whereNull('deleted_at')
            ->when($year, fn ($q) => $q->whereYear('created_at', $year))
            ->groupBy('status')
            ->pluck('count', 'status');

        $transactionCounts = DB::table('transactions')
            ->whereExists(fn ($q) => $q->selectRaw('1')->from('procurements')
                ->whereColumn('procurements.id', 'transactions.procurement_id')->whereNull('procurements.deleted_at'))
            ->when($officeId, fn ($q) => $q->where('current_office_id', $officeId))
            ->select('category', 'status', DB::raw('COUNT(*) as count'))
            ->whereNull('deleted_at')
            ->when($year, fn ($q) => $q->whereYear('created_at', $year))
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
    public function getOfficeWorkload(?EloquentCollection $activeTransactions = null, ?int $officeId = null): Collection
    {
        $activeTransactions ??= $this->loadActiveTransactions();
        $workflowOfficeIds = DB::table('workflow_steps')
            ->join('workflows', 'workflow_steps.workflow_id', '=', 'workflows.id')
            ->where('workflows.is_active', true)
            ->pluck('workflow_steps.office_id');

        // Retain offices holding real documents even when their workflow is retired.
        $officeIds = $workflowOfficeIds->merge($activeTransactions->pluck('current_office_id'))->unique()->filter();
        $byOffice = $activeTransactions->groupBy('current_office_id');
        $stagnantCounts = $this->getStagnantCountsByOffice($activeTransactions);

        return Office::withTrashed()->whereIn('id', $officeIds)
            ->when($officeId, fn ($q) => $q->where('id', $officeId))
            ->get(['id', 'name', 'abbreviation'])
            ->map(function (Office $office) use ($byOffice, $stagnantCounts) {
                $transactions = $byOffice->get($office->id, collect());

                return (object) [
                    'office_id' => $office->id,
                    'office_name' => $office->name,
                    'office_abbreviation' => $office->abbreviation,
                    'pr_count' => $transactions->where('category', 'PR')->count(),
                    'po_count' => $transactions->where('category', 'PO')->count(),
                    'vch_count' => $transactions->where('category', 'VCH')->count(),
                    'total' => $transactions->count(),
                    'stagnant_count' => $stagnantCounts[$office->id] ?? 0,
                ];
            })
            ->sortBy('office_name')->sortByDesc('total')->values();
    }

    /**
     * Get recent activity feed entries.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getRecentActivity(int $limit = 20, ?int $year = null, ?int $officeId = null): array
    {
        return TransactionAction::query()
            ->whereHas('transaction.procurement')
            ->when($officeId, fn ($q) => $q->where(fn ($q) => $q
                ->where('from_office_id', $officeId)->orWhere('to_office_id', $officeId)))
            ->with([
                'fromUser:id,name',
                'toUser:id,name',
                'fromOffice' => fn ($q) => $q->withTrashed()->select('id', 'name', 'abbreviation'),
                'toOffice' => fn ($q) => $q->withTrashed()->select('id', 'name', 'abbreviation'),
                'transaction:id,reference_number,category',
                'transaction.purchaseRequest:id,transaction_id',
                'transaction.purchaseOrder:id,transaction_id',
                'transaction.voucher:id,transaction_id',
            ])
            ->select('id', 'transaction_id', 'action_type', 'from_user_id', 'to_user_id', 'from_office_id', 'to_office_id', 'is_out_of_workflow', 'created_at')
            ->whereIn('action_type', ['endorse', 'receive', 'complete'])
            ->when($year, fn ($q) => $q->whereYear('created_at', $year))
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->map(fn (TransactionAction $action) => [
                'id' => $action->id,
                'action_type' => $action->action_type,
                'transaction_reference_number' => $action->transaction->reference_number,
                'transaction_id' => $action->transaction_id,
                'transaction_category' => $action->transaction->category,
                'actor_name' => $action->action_type === 'receive'
                    ? ($action->toUser?->name ?? $action->fromUser?->name ?? 'Unknown')
                    : ($action->fromUser?->name ?? 'Unknown'),
                'from_office' => $action->fromOffice?->abbreviation,
                'from_office_name' => $action->fromOffice?->name,
                'from_office_id' => $action->from_office_id,
                'to_office' => $action->toOffice?->abbreviation,
                'to_office_name' => $action->toOffice?->name,
                'to_office_id' => $action->to_office_id,
                'is_out_of_workflow' => $action->is_out_of_workflow,
                'purchase_request_id' => $action->transaction->purchaseRequest?->id,
                'purchase_order_id' => $action->transaction->purchaseOrder?->id,
                'voucher_id' => $action->transaction->voucher?->id,
                'created_at' => $action->created_at->diffForHumans(),
                'occurred_at' => $action->created_at->toISOString(),
                'url' => $this->documentUrl($action->transaction),
            ])
            ->all();
    }

    /**
     * Get stagnant transactions for the dashboard panel.
     *
     * @param  EloquentCollection<int, Transaction>|null  $activeTransactions  Pre-loaded transactions to avoid duplicate queries.
     * @return array<int, array<string, mixed>>
     */
    public function getStagnantTransactions(?EloquentCollection $activeTransactions = null, ?int $limit = 10): array
    {
        $activeTransactions ??= $this->loadActiveTransactions();

        $activeTransactions->loadMissing(['currentOffice' => fn ($q) => $q->withTrashed()->select('id', 'name', 'abbreviation')]);

        $transactions = $activeTransactions
            ->filter(fn (Transaction $tx) => $this->etaService->isStagnant($tx))
            ->map(fn (Transaction $tx) => $this->mapAttention($tx))
            ->sortByDesc('delay_days');

        return ($limit === null ? $transactions : $transactions->take($limit))->values()->all();
    }

    private function mapAttention(Transaction $tx): array
    {
        return [
            'id' => $tx->id,
            'reference_number' => $tx->reference_number,
            'category' => $tx->category,
            'status' => $tx->status,
            'current_step_order' => $tx->currentStep?->step_order,
            'total_steps' => $tx->workflow?->steps->count() ?? 0,
            'current_office_id' => $tx->current_office_id,
            'current_office_name' => $tx->currentOffice?->name ?? 'Unknown',
            'current_office_abbreviation' => $tx->currentOffice?->abbreviation,
            'delay_days' => $this->etaService->getDelayDays($tx),
            'delay_severity' => $this->etaService->getDelaySeverity($tx),
            'days_at_current_step' => $this->etaService->getDaysAtCurrentStep($tx),
            'expected_days' => $tx->currentStep?->expected_days,
            'idle_days' => $tx->latestAction
                ? $this->etaService->businessDaysBetween($tx->latestAction->created_at, now()) : 0,
            'reason' => $this->etaService->getDelayDays($tx) > 0 ? 'overdue' : 'idle',
            'awaiting_receipt' => $tx->received_at === null,
            'purchase_request_id' => $tx->purchaseRequest?->id,
            'purchase_order_id' => $tx->purchaseOrder?->id,
            'voucher_id' => $tx->voucher?->id,
            'url' => $this->documentUrl($tx),

        ];
    }

    /** Paginated register. Processing remains on the existing document pages. */
    public function getDocuments(int $year, array $filters, ?User $user = null, string $pageName = 'page'): LengthAwarePaginator
    {
        $query = $this->documentQuery()
            ->whereYear('created_at', $year)
            ->when($filters['office_id'], fn ($q, $id) => $q->where('current_office_id', $id))
            ->when($filters['category'] !== 'all', fn ($q) => $q->where('category', $filters['category']))
            ->when($filters['active_only'], fn ($q) => $q->whereIn('status', ['Created', 'In Progress']))
            ->when($filters['search'] !== '', fn ($q) => $q->where(fn ($q) => $q
                ->where('reference_number', 'like', '%'.$filters['search'].'%')
                ->orWhereHas('currentOffice', fn ($q) => $q->withTrashed()
                    ->where('name', 'like', '%'.$filters['search'].'%')
                    ->orWhere('abbreviation', 'like', '%'.$filters['search'].'%'))));

        match ($filters['sort'] ?? 'newest') {
            'reference_asc' => $query->orderBy('reference_number'),
            'reference_desc' => $query->orderByDesc('reference_number'),
            default => $query->orderByDesc('created_at'),
        };

        return $query->orderByDesc('id')->paginate(10, ['*'], $pageName)->withQueryString()
            ->through(fn (Transaction $tx) => $this->mapDocument($tx, $user));
    }

    /** A selected activity or attention record may be outside the current register scope. */
    public function getSelectedDocument(int $id, ?User $user = null): ?array
    {
        $transaction = $this->documentQuery()->find($id);

        return $transaction ? $this->mapDocument($transaction, $user) : null;
    }

    /** Pending receipts belong to the eligible user's office, across selected years. */
    public function getPendingDocuments(?User $user): array
    {
        if (! $user?->canProcessTransactions()) {
            return [];
        }

        return $this->documentQuery()
            ->where('current_office_id', $user->office_id)
            ->where('status', 'In Progress')->whereNull('received_at')
            ->orderByDesc('created_at')->orderByDesc('id')->limit(50)->get()
            ->map(fn (Transaction $tx) => $this->mapDocument($tx, $user))->all();
    }

    private function documentQuery(): Builder
    {
        return Transaction::query()->whereHas('procurement')
            ->with(['procurement:id,purpose', 'currentStep', 'latestAction',
                'currentOffice' => fn ($q) => $q->withTrashed()->select('id', 'name', 'abbreviation'),
                'workflow.steps.office' => fn ($q) => $q->withTrashed()->select('id', 'name', 'abbreviation'),
                'purchaseRequest:id,transaction_id',
                'purchaseOrder:id,transaction_id', 'voucher:id,transaction_id']);
    }

    /** Present a document whose workflow and document relations have been eager loaded. */
    public function mapDocument(Transaction $transaction, ?User $user = null): array
    {
        return [
            'id' => $transaction->id,
            'reference_number' => $transaction->reference_number,
            'category' => $transaction->category,
            'status' => $transaction->status,
            'procurement_id' => $transaction->procurement_id,
            'purpose' => $transaction->procurement?->purpose,
            'current_office_id' => $transaction->current_office_id,
            'current_office_name' => $transaction->currentOffice?->name,
            'current_office_abbreviation' => $transaction->currentOffice?->abbreviation,
            'created_at' => $transaction->created_at?->toISOString(),
            'received_at' => $transaction->received_at?->toISOString(),
            'url' => $this->documentUrl($transaction),
            'next_action' => $this->nextAction($transaction, $user),
            'attention' => in_array($transaction->status, ['Created', 'In Progress'], true)
                && $transaction->current_office_id !== null && $this->etaService->isStagnant($transaction)
                    ? $this->mapAttention($transaction) : null,
            'current_step_id' => $transaction->current_step_id,
            'workflow_steps' => $transaction->workflow?->steps->sortBy('step_order')->map(fn ($step) => [
                'id' => $step->id,
                'step_order' => $step->step_order,
                'office_id' => $step->office_id,
                'office_name' => $step->office?->name,
                'office_abbreviation' => $step->office?->abbreviation,
                'expected_days' => $step->expected_days,
            ])->values()->all() ?? [],
        ];
    }

    /** Links only. Destination policies and existing confirmation forms recheck eligibility. */
    private function nextAction(Transaction $transaction, ?User $user): ?array
    {
        $documentUrl = $this->documentUrl($transaction);
        if (! $user?->canProcessTransactions() || $user->office_id !== $transaction->current_office_id
            || ! in_array($transaction->status, ['Created', 'In Progress'], true) || ! $documentUrl) {
            return null;
        }

        if ($this->endorsementService->canReceive($transaction, $user)) {
            return ['label' => 'Review receipt', 'url' => $documentUrl];
        }

        if ($transaction->currentStep?->is_final_step && $this->endorsementService->canComplete($transaction, $user)) {
            return ['label' => 'Review completion', 'url' => $documentUrl];
        }

        if ($this->endorsementService->canEndorse($transaction, $user)) {
            $nextOffice = $this->endorsementService->getExpectedNextOffice($transaction);

            return [
                'label' => $nextOffice?->abbreviation ? 'Endorse to '.$nextOffice->abbreviation : 'Endorse document',
                'url' => route('transactions.endorse.create', $transaction->id),
            ];
        }

        return null;
    }

    private function documentUrl(Transaction $transaction): ?string
    {
        [$route, $id] = match ($transaction->category) {
            'PR' => ['purchase-requests.show', $transaction->purchaseRequest?->id],
            'PO' => ['purchase-orders.show', $transaction->purchaseOrder?->id],
            'VCH' => ['vouchers.show', $transaction->voucher?->id],
            default => [null, null],
        };

        return $id ? route($route, $id) : null;
    }

    /**
     * Get office performance metrics for SLA panel.
     * Calculates average turnaround time per office from receive→endorse/complete pairs.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getOfficePerformance(int $days = 30, ?int $year = null, ?int $officeId = null): array
    {
        $cutoff = $year
            ? \Illuminate\Support\Carbon::create($year, 1, 1)->startOfYear()
            : now()->subDays($days);

        // Get receive actions within the period
        $receives = DB::table('transaction_actions')
            ->whereExists(fn ($q) => $q->selectRaw('1')->from('transactions')
                ->join('procurements', 'procurements.id', '=', 'transactions.procurement_id')
                ->whereColumn('transactions.id', 'transaction_actions.transaction_id')
                ->whereNull('transactions.deleted_at')->whereNull('procurements.deleted_at'))
            ->where('action_type', 'receive')
            ->where('created_at', '>=', $cutoff)
            ->when($year, fn ($q) => $q->whereYear('created_at', $year))
            ->when($officeId, fn ($q) => $q->where('to_office_id', $officeId))
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
    public function getIncidentSummary(?int $year = null, ?int $officeId = null): array
    {
        $currentMonthStart = now()->startOfMonth();
        $previousMonthStart = now()->subMonth()->startOfMonth();
        $previousMonthEnd = now()->subMonth()->endOfMonth();

        $currentMonth = (int) TransactionAction::query()->whereHas('transaction.procurement')
            ->where('is_out_of_workflow', true)
            ->when($officeId, fn ($q) => $q->where(fn ($q) => $q
                ->where('from_office_id', $officeId)->orWhere('to_office_id', $officeId)))
            ->where('created_at', '>=', $currentMonthStart)
            ->when($year, fn ($q) => $q->whereYear('created_at', $year))
            ->count();

        $previousMonth = (int) TransactionAction::query()->whereHas('transaction.procurement')
            ->where('is_out_of_workflow', true)
            ->when($officeId, fn ($q) => $q->where(fn ($q) => $q
                ->where('from_office_id', $officeId)->orWhere('to_office_id', $officeId)))
            ->whereBetween('created_at', [$previousMonthStart, $previousMonthEnd])
            ->when($year, fn ($q) => $q->whereYear('created_at', $year))
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
    public function getVolumeSummary(?int $year = null, ?int $officeId = null): array
    {
        $currentMonthStart = now()->startOfMonth();
        $previousMonthStart = now()->subMonth()->startOfMonth();
        $previousMonthEnd = now()->subMonth()->endOfMonth();

        $currentCounts = DB::table('transactions')
            ->whereExists(fn ($q) => $q->selectRaw('1')->from('procurements')
                ->whereColumn('procurements.id', 'transactions.procurement_id')->whereNull('procurements.deleted_at'))
            ->when($officeId, fn ($q) => $q->where('current_office_id', $officeId))
            ->whereNull('deleted_at')
            ->where('created_at', '>=', $currentMonthStart)
            ->when($year, fn ($q) => $q->whereYear('created_at', $year))
            ->select('category', DB::raw('COUNT(*) as count'))
            ->groupBy('category')
            ->pluck('count', 'category');

        $previousCounts = DB::table('transactions')
            ->whereExists(fn ($q) => $q->selectRaw('1')->from('procurements')
                ->whereColumn('procurements.id', 'transactions.procurement_id')->whereNull('procurements.deleted_at'))
            ->when($officeId, fn ($q) => $q->where('current_office_id', $officeId))
            ->whereNull('deleted_at')
            ->whereBetween('created_at', [$previousMonthStart, $previousMonthEnd])
            ->when($year, fn ($q) => $q->whereYear('created_at', $year))
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

    public function getAnnualIncidentCount(int $year, ?int $officeId = null): int
    {
        return TransactionAction::query()->whereHas('transaction.procurement')
            ->where('is_out_of_workflow', true)
            ->whereYear('created_at', $year)
            ->when($officeId, fn ($q) => $q->where(fn ($q) => $q
                ->where('from_office_id', $officeId)->orWhere('to_office_id', $officeId)))
            ->count();
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
