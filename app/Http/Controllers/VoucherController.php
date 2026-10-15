<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Exceptions\NoActiveWorkflowException;
use App\Http\Requests\StoreVoucherRequest;
use App\Http\Requests\UpdateVoucherRequest;
use App\Models\ActionTaken;
use App\Models\Procurement;
use App\Models\Transaction;
use App\Models\Voucher;
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

class VoucherController extends Controller
{
    public function __construct(
        private readonly ReferenceNumberService $refNumberService,
        private readonly ProcurementBusinessRules $businessRules,
        private readonly EndorsementService $endorsementService,
        private readonly WorkflowAssignmentService $workflowService,
        private readonly TimelineService $timelineService
    ) {}

    /**
     * Story 2.8 AC#6 - Display VCH creation form with complete procurement hierarchy.
     */
    public function create(Procurement $procurement): Response|RedirectResponse
    {
        if (! $this->businessRules->canCreateVCH($procurement)) {
            return redirect()
                ->route('procurements.show', $procurement)
                ->with('error', 'Purchase Order required before creating Voucher');
        }

        // Eager load relationships for COMPLETE context display
        // VCH Create needs full hierarchy: Procurement → PR → PO → VCH
        $procurement->load([
            'endUser:id,name,abbreviation',
            'particular:id,description',
            'purchaseRequest.transaction',
            'purchaseRequest.fundType:id,name,abbreviation', // For PR card display
            'purchaseOrder.transaction',
            'purchaseOrder.supplier:id,name', // For PO card display
            'voucher.transaction',
        ])->makeVisible(['abc_amount']);

        $workflows = \App\Models\Workflow::where('is_active', true)
            ->where('category', 'VCH')
            ->whereHas('steps')
            ->whereDoesntHave('steps', fn ($steps) => $steps->whereDoesntHave('office', fn ($offices) => $offices->where('is_active', true)->whereNotNull('sso_id')))
            ->with('steps.office')
            ->orderBy('name')
            ->get();

        return Inertia::render('Vouchers/Create', [
            'procurement' => $procurement,
            'purchaseRequest' => $procurement->purchaseRequest, // Includes transaction + fundType
            'purchaseOrder' => $procurement->purchaseOrder, // Includes transaction + supplier + contract_price
            'workflows' => $workflows,
            'workflowPreview' => $this->workflowService->getWorkflowPreview('VCH'),
            'referenceNumbering' => $this->refNumberService->nextNumberingDefaults('VCH'),
        ]);
    }

    /**
     * Story 2.8 AC#8-9 - Create VCH with manual reference number input.
     */
    public function store(StoreVoucherRequest $request, Procurement $procurement): RedirectResponse
    {
        // AC#5 - Business rule validation
        if (! $this->businessRules->canCreateVCH($procurement)) {
            return back()
                ->withInput()
                ->withErrors(['procurement' => 'Purchase Order required before creating Voucher']);
        }

        try {
            $referenceNumber = DB::transaction(function () use ($request, $procurement) {
                $lockedProcurement = Procurement::whereKey($procurement->id)->lockForUpdate()->firstOrFail();
                if (! $this->businessRules->canCreateVCH($lockedProcurement)) {
                    throw \Illuminate\Validation\ValidationException::withMessages(['procurement' => 'The procurement already contains this document or its prerequisite is missing.']);
                }

                if ($request->boolean('manual_reference')) {
                    $referenceNumber = $request->string('reference_number')->toString();
                    $this->refNumberService->trackManualReference('VCH', $referenceNumber);
                } else {
                    $referenceNumber = $this->refNumberService->allocateDocumentReference(
                        'VCH', $request->string('ref_year')->toString(), $request->string('ref_month')->toString()
                    );
                }

                $transaction = Transaction::create([
                    'procurement_id' => $procurement->id,
                    'category' => Transaction::CATEGORY_VOUCHER,
                    'reference_number' => $referenceNumber,
                    'status' => 'Created',
                    'created_by_user_id' => auth()->id(),
                ]);

                try {
                    $this->workflowService->assignWorkflow($transaction, auth()->user(), $request->filled('workflow_id') ? $request->integer('workflow_id') : null);
                } catch (NoActiveWorkflowException $e) {
                    // Workflow is optional - continue without it
                }

                Voucher::create([
                    'transaction_id' => $transaction->id,
                    'payee' => $request->input('payee'),
                ]);

                // AC#10 - Update procurement status if still 'Created'
                if ($procurement->status === 'Created') {
                    $procurement->update(['status' => 'In Progress']);
                }

                return $referenceNumber;
            }, 5);

            // AC#16 - Success toast notification
            return redirect()
                ->route('procurements.show', $procurement)
                ->with('success', "Voucher {$referenceNumber} created successfully for Payee: {$request->input('payee')}");
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
                ->with('error', 'Error creating Voucher: '.$e->getMessage());
        }
    }

    /**
     * Story 2.8 AC#14 - Display VCH details with related transactions.
     */
    public function show(int $id): Response
    {
        $voucher = Voucher::with([
            'transaction.procurement.endUser:id,name,abbreviation',
            'transaction.procurement.particular:id,description',
            'transaction.procurement.purchaseRequest.transaction',
            'transaction.procurement.purchaseOrder.transaction',
            'transaction.procurement.voucher.transaction',
            'transaction.currentStep.office',
            'transaction.currentOffice:id,name,abbreviation',
            'transaction.createdBy:id,name',
        ])->findOrFail($id);

        // Load related PR and PO
        $purchaseRequest = $voucher->transaction->procurement->purchaseRequest;
        if ($purchaseRequest) {
            $purchaseRequest->loadMissing('transaction');
        }

        $purchaseOrder = $voucher->transaction->procurement->purchaseOrder;
        if ($purchaseOrder) {
            $purchaseOrder->loadMissing(['transaction', 'supplier:id,name']);
        }

        $user = auth()->user();
        $transaction = $voucher->transaction;

        $canEdit = $user->canProcessTransactions();
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

        return Inertia::render('Vouchers/Show', [
            'voucher' => $voucher,
            'purchaseRequest' => $purchaseRequest,
            'purchaseOrder' => $purchaseOrder,
            'canEdit' => $canEdit,
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

    /**
     * Story 2.8 AC#12 - Display VCH edit form.
     */
    public function edit(int $id): Response
    {
        $voucher = Voucher::with([
            'transaction.procurement.endUser:id,name,abbreviation',
            'transaction.procurement.particular:id,description',
            'transaction.procurement.purchaseRequest.transaction',
            'transaction.procurement.purchaseOrder.transaction',
            'transaction.procurement.voucher.transaction',
            'transaction.currentOffice:id,name,abbreviation',
            'transaction.createdBy:id,name',
            'transaction.workflow.steps.office:id,name,abbreviation',
        ])->findOrFail($id);

        if (! auth()->user()->canProcessTransactions()) {
            abort(403);
        }

        return Inertia::render('Vouchers/Edit', [
            'voucher' => $voucher,
        ]);
    }

    /**
     * Story 2.8 AC#12 - Update VCH reference number and payee.
     */
    public function update(UpdateVoucherRequest $request, int $id): RedirectResponse
    {
        $voucher = Voucher::with('transaction')->findOrFail($id);

        try {
            DB::transaction(function () use ($request, $voucher) {
                $this->refNumberService->trackManualReference('VCH', $request->string('reference_number')->toString());
                $voucher->update([
                    'payee' => $request->input('payee'),
                ]);

                $voucher->transaction->update([
                    'reference_number' => $request->input('reference_number'),
                    'workflow_id' => $request->has('workflow_id') ? $request->input('workflow_id') : $voucher->transaction->workflow_id,
                ]);
            }, 5);
        } catch (QueryException $e) {
            if ($this->refNumberService->isDuplicateReferenceException($e)) {
                throw ValidationException::withMessages(['reference_number' => 'This reference number is already in use. Enter a different reference number.']);
            }

            throw $e;
        }

        return redirect()
            ->route('vouchers.show', $id)
            ->with('success', 'Voucher updated successfully');
    }

    /**
     * Story 2.8 AC#13 - Soft delete VCH with audit warning.
     */
    public function destroy(int $id): RedirectResponse
    {
        $voucher = Voucher::with('transaction.procurement')->findOrFail($id);
        $procurement = $voucher->transaction->procurement;

        DB::transaction(function () use ($voucher) {
            $voucher->transaction->delete(); // Soft delete
            $voucher->delete(); // Soft delete
        });

        return redirect()
            ->route('procurements.show', $procurement->id)
            ->with('success', 'Voucher deleted successfully');
    }
}
