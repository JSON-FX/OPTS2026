<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Transaction;
use App\Models\TransactionAction;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DashboardService
{
    public function __construct(
        private readonly EtaCalculationService $etaService,
    ) {}

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
     * @return Collection<int, object>
     */
    public function getOfficeWorkload(): Collection
    {
        $workload = DB::table('transactions')
            ->join('offices', 'transactions.current_office_id', '=', 'offices.id')
            ->select(
                'offices.id as office_id',
                'offices.name as office_name',
                'offices.abbreviation as office_abbreviation',
                DB::raw("SUM(CASE WHEN transactions.category = 'PR' THEN 1 ELSE 0 END) as pr_count"),
                DB::raw("SUM(CASE WHEN transactions.category = 'PO' THEN 1 ELSE 0 END) as po_count"),
                DB::raw("SUM(CASE WHEN transactions.category = 'VCH' THEN 1 ELSE 0 END) as vch_count"),
                DB::raw('COUNT(*) as total')
            )
            ->whereNull('transactions.deleted_at')
            ->whereIn('transactions.status', ['Created', 'In Progress'])
            ->groupBy('offices.id', 'offices.name', 'offices.abbreviation')
            ->orderByDesc('total')
            ->get();

        // Compute stagnant counts per office using EtaCalculationService
        $stagnantCounts = $this->getStagnantCountsByOffice();

        return $workload->map(function ($row) use ($stagnantCounts) {
            $row->stagnant_count = $stagnantCounts[(int) $row->office_id] ?? 0;

            return $row;
        });
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
     * @return array<int, array<string, mixed>>
     */
    public function getStagnantTransactions(int $limit = 10): array
    {
        // Load office names via a map to avoid N+1 from currentHolder accessor
        $activeTransactions = Transaction::query()
            ->whereIn('status', ['Created', 'In Progress'])
            ->whereNotNull('current_office_id')
            ->with([
                'currentStep',
                'workflow.steps',
                'purchaseRequest:id,transaction_id',
                'purchaseOrder:id,transaction_id',
                'voucher:id,transaction_id',
            ])
            ->get();

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
     * @return array<int, int>
     */
    private function getStagnantCountsByOffice(): array
    {
        $activeTransactions = Transaction::query()
            ->whereIn('status', ['Created', 'In Progress'])
            ->whereNotNull('current_office_id')
            ->with(['currentStep', 'workflow.steps'])
            ->get();

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
