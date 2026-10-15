<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Office;
use App\Services\DashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(private readonly DashboardService $dashboardService)
    {
        $this->middleware(['auth']);
    }

    public function index(Request $request): Response
    {
        $request->validate([
            'office_id' => 'nullable|integer|exists:offices,id',
            'category' => 'nullable|in:all,PR,PO,VCH',
            'search' => 'nullable|string|max:100',
            'active_only' => 'nullable|boolean',
            'page' => 'nullable|integer|min:1',
            'sort' => 'nullable|in:newest,reference_asc,reference_desc',
            'document_id' => 'nullable|integer|exists:transactions,id',
            'queue_office_id' => 'nullable|integer|exists:offices,id',
            'queue_category' => 'nullable|in:all,PR,PO,VCH',
            'queue_search' => 'nullable|string|max:100',
            'queue_sort' => 'nullable|in:newest,reference_asc,reference_desc',
            'queue_active_only' => 'nullable|boolean',
            'queue_page' => 'nullable|integer|min:1',
        ]);
        $year = $request->user()?->selected_year ?? (int) now()->year;
        $filters = [
            'office_id' => $request->filled('office_id') ? $request->integer('office_id') : null,
            'category' => $request->input('category') ?: 'all',
            'search' => trim($request->string('search')->toString()),
            'active_only' => $request->boolean('active_only'),
            'sort' => $request->input('sort') ?: 'newest',
        ];
        $officeId = $filters['office_id'];
        $queueFilters = $request->filled('queue_office_id') ? [
            'office_id' => $request->integer('queue_office_id'),
            'category' => $request->input('queue_category') ?: 'all',
            'search' => trim($request->string('queue_search')->toString()),
            'sort' => $request->input('queue_sort') ?: 'newest',
            'active_only' => $request->boolean('queue_active_only', true),
        ] : null;

        // Reuse one loaded collection for workload and authoritative attention calculations.
        $activeTransactions = $this->dashboardService->loadActiveTransactions($year, $officeId);
        $stagnantTransactions = $this->dashboardService->getStagnantTransactions($activeTransactions, null);
        $overdueCount = collect($stagnantTransactions)->where('reason', 'overdue')->count();

        // Include historical offices that still hold documents in the selected year.
        $historicalOfficeIds = DB::table('transactions')->whereNull('deleted_at')
            ->whereExists(fn ($q) => $q->selectRaw('1')->from('procurements')
                ->whereColumn('procurements.id', 'transactions.procurement_id')->whereNull('procurements.deleted_at'))
            ->whereYear('created_at', $year)->whereNotNull('current_office_id')
            ->pluck('current_office_id');
        $offices = Office::withTrashed()->where(fn ($q) => $q->where(fn ($q) => $q->where('is_active', true)->whereNull('deleted_at'))
            ->orWhereIn('id', $historicalOfficeIds)
            ->when($officeId, fn ($q) => $q->orWhere('id', $officeId)))
            ->orderBy('abbreviation')->get(['id', 'name', 'abbreviation']);

        return Inertia::render('Dashboard', [
            'summary' => $this->dashboardService->getSummaryCards($year, $officeId),
            'officeWorkload' => $this->dashboardService->getOfficeWorkload($activeTransactions, $officeId),
            'activityFeed' => $this->dashboardService->getRecentActivity(25, $year, $officeId),
            'stagnantTransactions' => $stagnantTransactions,
            'attentionMeta' => [
                'total' => count($stagnantTransactions),
                'overdue_count' => $overdueCount,
                'idle_count' => count($stagnantTransactions) - $overdueCount,
                'idle_threshold_days' => (int) config('opts.idle_threshold_days', 2),
            ],
            'slaPerformance' => [
                'office_performance' => $this->dashboardService->getOfficePerformance(365, $year, $officeId),
                'incidents' => $this->dashboardService->getIncidentSummary($year, $officeId),
                'volume' => $this->dashboardService->getVolumeSummary($year, $officeId),
            ],
            'annualIncidentCount' => $this->dashboardService->getAnnualIncidentCount($year, $officeId),
            'documents' => $this->dashboardService->getDocuments($year, $filters, $request->user()),
            'queueFilters' => $queueFilters,
            'queueDocuments' => $queueFilters
                ? fn () => $this->dashboardService->getDocuments($year, $queueFilters, $request->user(), 'queue_page') : null,
            'selectedDocument' => $request->filled('document_id')
                ? fn () => $this->dashboardService->getSelectedDocument($request->integer('document_id'), $request->user()) : null,
            'pendingDocuments' => fn () => $this->dashboardService->getPendingDocuments($request->user()),
            'filters' => $filters,
            'offices' => $offices,
            'announcements' => $this->activeAnnouncements(),
            'userOfficeId' => $request->user()?->office_id,
        ]);
    }

    /** Deployment-configured notices only; malformed or inactive entries remain hidden. */
    private function activeAnnouncements(): array
    {
        return collect(config('opts.announcements', []))->filter(function ($notice) {
            if (! is_array($notice)
                || ! in_array($notice['kind'] ?? null, ['update', 'maintenance', 'emergency', 'reminder'], true)
                || ! is_string($notice['id'] ?? null) || trim($notice['id']) === ''
                || ! is_string($notice['title'] ?? null) || trim($notice['title']) === ''
                || ! is_string($notice['message'] ?? null) || trim($notice['message']) === ''
                || (isset($notice['detail']) && ! is_string($notice['detail']))
                || (isset($notice['audience']) && ! is_string($notice['audience']))
                || (isset($notice['publisher']) && ! is_string($notice['publisher']))
                || (isset($notice['starts_at']) && ! is_string($notice['starts_at']))
                || (isset($notice['ends_at']) && ! is_string($notice['ends_at']))) {
                return false;
            }

            try {
                return (! isset($notice['starts_at']) || now()->gte(Carbon::parse($notice['starts_at'])))
                    && (! isset($notice['ends_at']) || now()->lt(Carbon::parse($notice['ends_at'])));
            } catch (\Throwable) {
                return false;
            }
        })->map(fn ($notice) => collect($notice)->only([
            'id', 'kind', 'title', 'message', 'starts_at', 'ends_at', 'detail', 'audience', 'publisher',
        ])->all())->values()->all();
    }

    /**
     * Return transactions for a specific office and category (JSON for modal).
     */
    public function workloadDetail(Request $request): JsonResponse
    {
        $request->validate([
            'office_id' => 'required|integer|exists:offices,id',
            'category' => 'required|in:PR,PO,VCH',
        ]);

        $category = $request->string('category')->toString();

        $entityTable = match ($category) {
            'PR' => 'purchase_requests',
            'PO' => 'purchase_orders',
            'VCH' => 'vouchers',
        };

        $year = $request->user()?->selected_year ?? (int) now()->year;

        $transactions = DB::table('transactions')
            ->select(
                "{$entityTable}.id as entity_id",
                'transactions.reference_number',
                'transactions.category',
                'transactions.status',
                'transactions.procurement_id',
                'transactions.created_at',
                'procurements.purpose as procurement_purpose',
                'offices.name as end_user_office',
                'users.name as created_by_name',
            )
            ->join($entityTable, "{$entityTable}.transaction_id", '=', 'transactions.id')
            ->join('procurements', 'transactions.procurement_id', '=', 'procurements.id')
            ->join('offices', 'procurements.end_user_id', '=', 'offices.id')
            ->join('users', 'transactions.created_by_user_id', '=', 'users.id')
            ->where('transactions.current_office_id', $request->integer('office_id'))
            ->where('transactions.category', $category)
            ->whereIn('transactions.status', ['Created', 'In Progress'])
            ->whereNull('transactions.deleted_at')
            ->whereYear('transactions.created_at', $year)
            ->orderBy('transactions.created_at', 'desc')
            ->get();

        return response()->json(['data' => $transactions]);
    }
}
