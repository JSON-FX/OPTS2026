<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\DashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
        // Load active transactions once and share between workload + stagnant panels
        $activeTransactions = $this->dashboardService->loadActiveTransactions();

        $summary = $this->dashboardService->getSummaryCards();
        $officeWorkload = $this->dashboardService->getOfficeWorkload($activeTransactions);
        $activityFeed = $this->dashboardService->getRecentActivity(25);
        $stagnantTransactions = $this->dashboardService->getStagnantTransactions($activeTransactions, 25);

        $slaPerformance = [
            'office_performance' => $this->dashboardService->getOfficePerformance(),
            'incidents' => $this->dashboardService->getIncidentSummary(),
            'volume' => $this->dashboardService->getVolumeSummary(),
        ];

        return Inertia::render('Dashboard', [
            'summary' => $summary,
            'officeWorkload' => $officeWorkload,
            'activityFeed' => $activityFeed,
            'stagnantTransactions' => $stagnantTransactions,
            'slaPerformance' => $slaPerformance,
            'userOfficeId' => $request->user()?->office_id,
        ]);
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
            ->orderBy('transactions.created_at', 'desc')
            ->get();

        return response()->json(['data' => $transactions]);
    }
}
