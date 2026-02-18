<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StorePurchaseRequestRequest;
use App\Http\Requests\UpdatePurchaseRequestRequest;
use App\Models\ActionTaken;
use App\Models\FundType;
use App\Models\Procurement;
use App\Models\PurchaseRequest;
use App\Models\Transaction;
use App\Exceptions\NoActiveWorkflowException;
use App\Services\EndorsementService;
use App\Services\ProcurementBusinessRules;
use App\Services\ReferenceNumberService;
use App\Services\TimelineService;
use App\Services\WorkflowAssignmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class PurchaseRequestController extends Controller
{
    public function __construct(
        private readonly ReferenceNumberService $refNumberService,
        private readonly ProcurementBusinessRules $businessRules,
        private readonly EndorsementService $endorsementService,
        private readonly WorkflowAssignmentService $workflowService,
        private readonly TimelineService $timelineService
    ) {}

    public function create(Procurement $procurement): Response|RedirectResponse
    {
        if (! $this->businessRules->canCreatePR($procurement)) {
            abort(403, 'Cannot create Purchase Request for this procurement');
        }

        $procurement->load(['endUser', 'particular', 'purchaseRequest']);

        $fundTypes = FundType::whereNull('deleted_at')
            ->orderBy('name')
            ->get();

        $workflows = \App\Models\Workflow::where('is_active', true)
            ->where('category', 'PR')
            ->with('steps.office')
            ->orderBy('name')
            ->get();

        return Inertia::render('PurchaseRequests/Create', [
            'procurement' => $procurement,
            'fundTypes' => $fundTypes,
            'workflows' => $workflows,
            'workflowPreview' => $this->workflowService->getWorkflowPreview('PR'),
        ]);
    }

    public function store(StorePurchaseRequestRequest $request, Procurement $procurement): RedirectResponse
    {
        try {
            $fundType = FundType::findOrFail($request->input('fund_type_id'));

            $referenceNumber = $this->refNumberService->buildPRReferenceNumber(
                $fundType->abbreviation,
                $request->input('ref_year'),
                $request->input('ref_month'),
                $request->input('ref_number'),
                $request->boolean('is_continuation')
            );

            DB::transaction(function () use ($request, $procurement, $referenceNumber) {
                $transaction = Transaction::create([
                    'procurement_id' => $procurement->id,
                    'category' => Transaction::CATEGORY_PURCHASE_REQUEST,
                    'reference_number' => $referenceNumber,
                    'is_continuation' => $request->boolean('is_continuation'),
                    'status' => 'Created',
                    'workflow_id' => $request->input('workflow_id'),
                    'created_by_user_id' => auth()->id(),
                ]);

                try {
                    $this->workflowService->assignWorkflow($transaction, auth()->user());
                } catch (NoActiveWorkflowException $e) {
                    // Workflow is optional - continue without it
                }

                PurchaseRequest::create([
                    'transaction_id' => $transaction->id,
                    'fund_type_id' => $request->input('fund_type_id'),
                ]);

                if ($procurement->status === 'Created') {
                    $procurement->update(['status' => 'In Progress']);
                }
            });

            return redirect()
                ->route('procurements.show', $procurement)
                ->with('success', "Purchase Request {$referenceNumber} created successfully");
        } catch (\Exception $e) {
            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'Error creating Purchase Request: '.$e->getMessage());
        }
    }

    public function show(int $id): Response
    {
        $purchaseRequest = PurchaseRequest::with([
            'transaction.procurement.endUser',
            'transaction.procurement.particular',
            'transaction.procurement', // Ensure procurement is fully loaded
            'transaction.currentStep.office',
            'fundType',
            'transaction.createdBy',
        ])->findOrFail($id);

        $user = auth()->user();
        $transaction = $purchaseRequest->transaction;

        $canEdit = $user->hasAnyRole(['Endorser', 'Administrator']);
        $canDelete = $canEdit && $this->businessRules->canDeletePR($transaction->procurement);
        $canEndorse = $this->endorsementService->canEndorse($transaction, $user);
        $cannotEndorseReason = $canEndorse ? null : $this->endorsementService->getCannotEndorseReason($transaction, $user);
        $canReceive = $this->endorsementService->canReceive($transaction, $user);
        $cannotReceiveReason = $canReceive ? null : $this->endorsementService->getCannotReceiveReason($transaction, $user);
        $canComplete = $this->endorsementService->canComplete($transaction, $user);
        $cannotCompleteReason = $canComplete ? null : $this->endorsementService->getCannotCompleteReason($transaction, $user);
        $canHold = $this->endorsementService->canHold($transaction, $user);
        $cannotHoldReason = $canHold ? null : $this->endorsementService->getCannotHoldReason($transaction, $user);
        $canCancel = $this->endorsementService->canCancel($transaction, $user);
        $cannotCancelReason = $canCancel ? null : $this->endorsementService->getCannotCancelReason($transaction, $user);
        $canResume = $this->endorsementService->canResume($transaction, $user);
        $cannotResumeReason = $canResume ? null : $this->endorsementService->getCannotResumeReason($transaction, $user);

        // Out-of-workflow detection (Story 3.8)
        $outOfWorkflowAction = $transaction->actions()
            ->where('is_out_of_workflow', true)
            ->with(['toOffice:id,name'])
            ->latest()
            ->first();

        $outOfWorkflowInfo = $outOfWorkflowAction ? [
            'is_out_of_workflow' => true,
            'expected_office_name' => $this->endorsementService->getExpectedNextOffice($transaction)?->name,
            'actual_office_name' => $outOfWorkflowAction->toOffice?->name,
        ] : null;

        return Inertia::render('PurchaseRequests/Show', [
            'purchaseRequest' => $purchaseRequest,
            'canEdit' => $canEdit,
            'canDelete' => $canDelete,
            'canEndorse' => $canEndorse,
            'cannotEndorseReason' => $cannotEndorseReason,
            'canReceive' => $canReceive,
            'cannotReceiveReason' => $cannotReceiveReason,
            'canComplete' => $canComplete,
            'cannotCompleteReason' => $cannotCompleteReason,
            'canHold' => $canHold,
            'cannotHoldReason' => $cannotHoldReason,
            'canCancel' => $canCancel,
            'cannotCancelReason' => $cannotCancelReason,
            'canResume' => $canResume,
            'cannotResumeReason' => $cannotResumeReason,
            'outOfWorkflowInfo' => $outOfWorkflowInfo,
            'timeline' => $this->timelineService->getTimeline($transaction),
            'actionHistory' => $this->timelineService->getActionHistory($transaction),
            'actionTakenOptions' => ActionTaken::query()
                ->where('is_active', true)
                ->orderBy('description')
                ->get(['id', 'description']),
            'defaultActionTakenId' => $transaction->currentStep?->action_taken_id,
        ]);
    }

    public function edit(int $id): Response
    {
        $purchaseRequest = PurchaseRequest::with([
            'transaction.procurement',
            'fundType',
        ])->findOrFail($id);

        if (! auth()->user()->hasAnyRole(['Endorser', 'Administrator'])) {
            abort(403);
        }

        $fundTypes = FundType::whereNull('deleted_at')
            ->orderBy('name')
            ->get();

        return Inertia::render('PurchaseRequests/Edit', [
            'purchaseRequest' => $purchaseRequest,
            'fundTypes' => $fundTypes,
        ]);
    }

    public function update(UpdatePurchaseRequestRequest $request, int $id): RedirectResponse
    {
        $purchaseRequest = PurchaseRequest::with('transaction')->findOrFail($id);

        $fundType = FundType::findOrFail($request->input('fund_type_id'));

        $newReferenceNumber = $this->refNumberService->buildPRReferenceNumber(
            $fundType->abbreviation,
            $request->input('ref_year'),
            $request->input('ref_month'),
            $request->input('ref_number'),
            $request->boolean('is_continuation')
        );

        DB::transaction(function () use ($request, $purchaseRequest, $newReferenceNumber) {
            $purchaseRequest->update([
                'fund_type_id' => $request->input('fund_type_id'),
            ]);

            $purchaseRequest->transaction->update([
                'reference_number' => $newReferenceNumber,
                'is_continuation' => $request->boolean('is_continuation'),
            ]);
        });

        return redirect()
            ->route('purchase-requests.show', $id)
            ->with('success', 'Purchase Request updated successfully');
    }

    public function destroy(int $id): RedirectResponse
    {
        $purchaseRequest = PurchaseRequest::with('transaction.procurement')->findOrFail($id);

        if (! $this->businessRules->canDeletePR($purchaseRequest->transaction->procurement)) {
            abort(422, 'Cannot delete Purchase Request because Purchase Order exists for this procurement.');
        }

        DB::transaction(function () use ($purchaseRequest) {
            $purchaseRequest->transaction->delete();
            $purchaseRequest->delete();
        });

        return redirect()
            ->route('procurements.show', $purchaseRequest->transaction->procurement_id)
            ->with('success', 'Purchase Request deleted successfully');
    }
}
