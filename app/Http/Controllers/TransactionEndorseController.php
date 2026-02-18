<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\EndorseTransactionRequest;
use App\Models\ActionTaken;
use App\Models\Office;
use App\Models\Transaction;
use App\Services\EndorsementService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Controller for transaction endorsement operations.
 *
 * Story 3.4 - Endorse Action Implementation
 */
class TransactionEndorseController extends Controller
{
    public function __construct(
        private readonly EndorsementService $endorsementService
    ) {}

    /**
     * Show the endorsement form.
     */
    public function create(Transaction $transaction): Response
    {
        $this->authorize('endorse', $transaction);

        $transaction->load([
            'currentStep.office',
            'procurement.endUser',
        ]);

        // Get workflow steps for displaying workflow progress
        $workflowSteps = [];
        if ($transaction->workflow_id) {
            $workflowSteps = $transaction->load('currentStep.workflow.steps.office')
                ->currentStep
                ?->workflow
                ?->steps
                ?->map(fn ($step) => [
                    'id' => $step->id,
                    'step_order' => $step->step_order,
                    'office_name' => $step->office->name,
                    'is_final_step' => $step->is_final_step,
                ])
                ->toArray() ?? [];
        }

        $expectedNextOffice = $this->endorsementService->getExpectedNextOffice($transaction);

        // Resolve the entity-specific show route and ID for back/cancel links
        $entityShowRoute = $this->resolveEntityShowRoute($transaction);

        return Inertia::render('Transactions/Endorse', [
            'transaction' => [
                'id' => $transaction->id,
                'reference_number' => $transaction->reference_number,
                'category' => $transaction->category,
                'status' => $transaction->status,
                'current_step_id' => $transaction->current_step_id,
                'current_step' => $transaction->currentStep ? [
                    'id' => $transaction->currentStep->id,
                    'step_order' => $transaction->currentStep->step_order,
                    'office' => $transaction->currentStep->office ? [
                        'id' => $transaction->currentStep->office->id,
                        'name' => $transaction->currentStep->office->name,
                    ] : null,
                ] : null,
                'procurement' => $transaction->procurement ? [
                    'id' => $transaction->procurement->id,
                    'purpose' => $transaction->procurement->purpose,
                    'end_user' => $transaction->procurement->endUser ? [
                        'id' => $transaction->procurement->endUser->id,
                        'name' => $transaction->procurement->endUser->name,
                    ] : null,
                ] : null,
            ],
            'entityShowRoute' => $entityShowRoute,
            'workflowSteps' => $workflowSteps,
            'actionTakenOptions' => ActionTaken::query()
                ->where('is_active', true)
                ->orderBy('description')
                ->get(['id', 'description']),
            'officeOptions' => Office::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name']),
            'expectedNextOffice' => $expectedNextOffice ? [
                'id' => $expectedNextOffice->id,
                'name' => $expectedNextOffice->name,
            ] : null,
        ]);
    }

    /**
     * Process the endorsement.
     */
    public function store(EndorseTransactionRequest $request, Transaction $transaction): RedirectResponse
    {
        $this->authorize('endorse', $transaction);

        $action = $this->endorsementService->endorse(
            $transaction,
            $request->user(),
            (int) $request->validated('action_taken_id'),
            (int) $request->validated('to_office_id'),
            $request->validated('notes')
        );

        $entityShowRoute = $this->resolveEntityShowRoute($transaction);

        return redirect()
            ->route($entityShowRoute['route'], $entityShowRoute['id'])
            ->with('success', "Transaction endorsed to {$action->toOffice->name}");
    }

    /**
     * Resolve the entity-specific show route name and ID for a transaction.
     *
     * @return array{route: string, id: int}
     */
    private function resolveEntityShowRoute(Transaction $transaction): array
    {
        $routeName = match ($transaction->category) {
            'PO' => 'purchase-orders.show',
            'VCH' => 'vouchers.show',
            default => 'purchase-requests.show',
        };

        $entityId = match ($transaction->category) {
            'PO' => $transaction->purchaseOrder?->id ?? $transaction->id,
            'VCH' => $transaction->voucher?->id ?? $transaction->id,
            default => $transaction->purchaseRequest?->id ?? $transaction->id,
        };

        return ['route' => $routeName, 'id' => $entityId];
    }
}
