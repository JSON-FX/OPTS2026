<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreWorkflowRequest;
use App\Http\Requests\UpdateWorkflowRequest;
use App\Models\Office;
use App\Models\Workflow;
use App\Services\WorkflowService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WorkflowController extends Controller
{
    public function __construct(
        private readonly WorkflowService $workflowService
    ) {}

    /**
     * Display a listing of workflows.
     */
    public function index(Request $request): Response
    {
        $query = Workflow::query()
            ->withCount('steps')
            ->with('steps.office');

        // Filter by category
        if ($request->filled('category')) {
            $query->where('category', $request->input('category'));
        }

        // Filter by status
        if ($request->filled('status')) {
            $isActive = $request->input('status') === 'active';
            $query->where('is_active', $isActive);
        }

        // Search by name
        if ($request->filled('search')) {
            $query->where('name', 'like', '%'.$request->input('search').'%');
        }

        // Sorting
        $sortColumn = $request->input('sort', 'created_at');
        $sortDirection = $request->input('direction', 'desc');
        $allowedSorts = ['name', 'category', 'created_at'];

        if (in_array($sortColumn, $allowedSorts)) {
            $query->orderBy($sortColumn, $sortDirection === 'asc' ? 'asc' : 'desc');
        } elseif ($sortColumn === 'steps_count') {
            // steps_count is a virtual column from withCount, needs special handling
            $query->orderBy('steps_count', $sortDirection === 'asc' ? 'asc' : 'desc');
        } else {
            $query->orderBy('created_at', 'desc');
        }

        $workflows = $query->paginate(20)->withQueryString();

        return Inertia::render('Admin/Workflows/Index', [
            'workflows' => $workflows,
            'filters' => [
                'category' => $request->input('category', ''),
                'status' => $request->input('status', ''),
                'search' => $request->input('search', ''),
                'sort' => $sortColumn,
                'direction' => $sortDirection,
            ],
        ]);
    }

    /**
     * Show the form for creating a new workflow.
     */
    public function create(): Response
    {
        $offices = Office::where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'abbreviation']);

        return Inertia::render('Admin/Workflows/Create', [
            'offices' => $offices,
        ]);
    }

    /**
     * Store a newly created workflow in storage.
     */
    public function store(StoreWorkflowRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $this->workflowService->createWithSteps(
            [
                'name' => $validated['name'],
                'category' => $validated['category'],
                'description' => $validated['description'] ?? null,
                'is_active' => $validated['is_active'] ?? true,
                'created_by_user_id' => $request->user()->id,
            ],
            $validated['steps']
        );

        return redirect()
            ->route('admin.workflows.index')
            ->with('success', 'Workflow created successfully.');
    }

    /**
     * Display the specified workflow.
     */
    public function show(Workflow $workflow): Response
    {
        $workflow->load('steps.office', 'createdBy');

        $totalExpectedDays = $workflow->steps->sum('expected_days');

        return Inertia::render('Admin/Workflows/Show', [
            'workflow' => $workflow,
            'totalExpectedDays' => $totalExpectedDays,
        ]);
    }

    /**
     * Show the form for editing the specified workflow.
     */
    public function edit(Workflow $workflow): Response
    {
        $workflow->load('steps.office');

        $offices = Office::where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'abbreviation']);

        $hasActiveTransactions = $this->workflowService->hasActiveTransactions($workflow);

        return Inertia::render('Admin/Workflows/Edit', [
            'workflow' => $workflow,
            'offices' => $offices,
            'hasActiveTransactions' => $hasActiveTransactions,
        ]);
    }

    /**
     * Update the specified workflow in storage.
     */
    public function update(UpdateWorkflowRequest $request, Workflow $workflow): RedirectResponse
    {
        $validated = $request->validated();

        $this->workflowService->updateWithSteps(
            $workflow,
            [
                'name' => $validated['name'],
                'category' => $validated['category'],
                'description' => $validated['description'] ?? null,
                'is_active' => $validated['is_active'] ?? true,
            ],
            $validated['steps']
        );

        return redirect()
            ->route('admin.workflows.index')
            ->with('success', 'Workflow updated successfully.');
    }

    /**
     * Remove the specified workflow from storage.
     */
    public function destroy(Workflow $workflow): RedirectResponse
    {
        // Check if workflow can be deleted
        if (! $this->workflowService->canDelete($workflow)) {
            return redirect()
                ->route('admin.workflows.index')
                ->with('error', 'Cannot delete workflow that is referenced by transactions.');
        }

        // Check if workflow can be deactivated (soft delete)
        if (! $this->workflowService->canDeactivate($workflow)) {
            return redirect()
                ->route('admin.workflows.index')
                ->with('error', 'Cannot deactivate the only active workflow for this category.');
        }

        // If no transactions reference this workflow, hard delete
        if ($this->workflowService->canDelete($workflow)) {
            $workflow->steps()->delete();
            $workflow->delete();

            return redirect()
                ->route('admin.workflows.index')
                ->with('success', 'Workflow deleted successfully.');
        }

        // Otherwise, soft delete (deactivate)
        $workflow->update(['is_active' => false]);

        return redirect()
            ->route('admin.workflows.index')
            ->with('success', 'Workflow deactivated successfully.');
    }
}
