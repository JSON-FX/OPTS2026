<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Exceptions\NoActiveWorkflowException;
use App\Http\Requests\StorePurchaseRequestRequest;
use App\Http\Requests\UpdatePurchaseRequestRequest;
use App\Models\ActionTaken;
use App\Models\FundType;
use App\Models\Procurement;
use App\Models\PurchaseRequest;
use App\Models\Transaction;
use App\Services\EndorsementService;
use App\Services\ProcurementBusinessRules;
use App\Services\ReferenceNumberService;
use App\Services\TimelineService;
use App\Services\WorkflowAssignmentService;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
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

        $procurement->load([
            'endUser:id,name,abbreviation',
            'particular:id,description',
            'purchaseRequest.transaction',
            'purchaseOrder.transaction',
            'voucher.transaction',
        ]);

        $fundTypes = FundType::whereNull('deleted_at')
            ->orderBy('name')
            ->get();

        $workflows = \App\Models\Workflow::where('is_active', true)
            ->where('category', 'PR')
            ->whereHas('steps')
            ->whereDoesntHave('steps', fn ($steps) => $steps->whereDoesntHave('office', fn ($offices) => $offices->where('is_active', true)->whereNotNull('sso_id')))
            ->with('steps.office')
            ->orderBy('name')
            ->get();

        return Inertia::render('PurchaseRequests/Create', [
            'procurement' => $procurement,
            'fundTypes' => $fundTypes,
            'workflows' => $workflows,
            'workflowPreview' => $this->workflowService->getWorkflowPreview('PR'),
            'referenceNumbering' => $this->refNumberService->nextNumberingDefaults('PR'),
        ]);
    }

    public function store(StorePurchaseRequestRequest $request, Procurement $procurement): RedirectResponse
    {
        if (! $this->businessRules->canCreatePR($procurement)) {
            return back()->withInput()->withErrors(['procurement' => 'This procurement already has a Purchase Request.']);
        }

        try {
            $fundType = FundType::findOrFail($request->input('fund_type_id'));

            $referenceNumber = DB::transaction(function () use ($request, $procurement, $fundType) {
                $lockedProcurement = Procurement::whereKey($procurement->id)->lockForUpdate()->firstOrFail();
                if (! $this->businessRules->canCreatePR($lockedProcurement)) {
                    throw \Illuminate\Validation\ValidationException::withMessages(['procurement' => 'The procurement already contains this document or its prerequisite is missing.']);
                }

                if ($request->boolean('manual_reference')) {
                    $referenceNumber = $request->string('reference_number')->toString();
                    $this->refNumberService->trackManualReference('PR', $referenceNumber);
                } else {
                    $referenceNumber = $this->refNumberService->allocateDocumentReference(
                        'PR', $request->string('ref_year')->toString(), $request->string('ref_month')->toString(),
                        $fundType->abbreviation, $request->boolean('is_continuation')
                    );
                }

                $transaction = Transaction::create([
                    'procurement_id' => $procurement->id,
                    'category' => Transaction::CATEGORY_PURCHASE_REQUEST,
                    'reference_number' => $referenceNumber,
                    'is_continuation' => $request->boolean('is_continuation'),
                    'status' => 'Created',
                    'created_by_user_id' => auth()->id(),
                ]);

                try {
                    $this->workflowService->assignWorkflow($transaction, auth()->user(), $request->filled('workflow_id') ? $request->integer('workflow_id') : null);
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

                return $referenceNumber;
            }, 5);

            return redirect()
                ->route('procurements.show', $procurement)
                ->with('success', "Purchase Request {$referenceNumber} created successfully");
        } catch (ValidationException $e) {
            throw $e;
        } catch (QueryException $e) {
            if ($this->refNumberService->isDuplicateReferenceException($e)) {
                throw ValidationException::withMessages(['reference_number' => 'This reference number is already in use. Enter a different reference number.']);
            }

            throw $e;
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
            'transaction.procurement.purchaseRequest.transaction',
            'transaction.procurement.purchaseOrder.transaction',
            'transaction.procurement.voucher.transaction',
            'transaction.currentStep.office',
            'transaction.currentOffice:id,name,abbreviation',
            'fundType',
            'transaction.createdBy:id,name',
        ])->findOrFail($id);

        $user = auth()->user();
        $transaction = $purchaseRequest->transaction;

        $canEdit = $user->canProcessTransactions();
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
            'transaction.procurement.endUser:id,name,abbreviation',
            'transaction.procurement.particular:id,description',
            'transaction.procurement.purchaseRequest.transaction',
            'transaction.procurement.purchaseOrder.transaction',
            'transaction.procurement.voucher.transaction',
            'transaction.currentOffice:id,name,abbreviation',
            'transaction.createdBy:id,name',
            'transaction.workflow.steps.office:id,name,abbreviation',
            'fundType',
        ])->findOrFail($id);

        if (! auth()->user()->canProcessTransactions()) {
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

        DB::transaction(function () use ($request, $purchaseRequest) {
            $purchaseRequest->update([
                'fund_type_id' => $request->input('fund_type_id'),
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
