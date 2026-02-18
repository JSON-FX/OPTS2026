<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\DashboardService;
use Illuminate\Http\Request;
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
        $summary = $this->dashboardService->getSummaryCards();
        $officeWorkload = $this->dashboardService->getOfficeWorkload();
        $activityFeed = $this->dashboardService->getRecentActivity(100);
        $stagnantTransactions = $this->dashboardService->getStagnantTransactions(100);

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
}
