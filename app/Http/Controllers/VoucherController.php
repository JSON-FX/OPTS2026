<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreVoucherRequest;
use App\Http\Requests\UpdateVoucherRequest;
use App\Exceptions\NoActiveWorkflowException;
use App\Models\ActionTaken;
use App\Models\Procurement;
use App\Models\Transaction;
use App\Models\Voucher;
use App\Services\EndorsementService;
use App\Services\ProcurementBusinessRules;
use App\Services\ReferenceNumberService;
use App\Services\TimelineService;
use App\Services\WorkflowAssignmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
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
        ])->makeVisible(['abc_amount']);

        $workflows = \App\Models\Workflow::where('is_active', true)
            ->where('category', 'VCH')
            ->with('steps.office')
            ->orderBy('name')
            ->get();

        return Inertia::render('Vouchers/Create', [
            'procurement' => $procurement,
            'purchaseRequest' => $procurement->purchaseRequest, // Includes transaction + fundType
            'purchaseOrder' => $procurement->purchaseOrder, // Includes transaction + supplier + contract_price
            'workflows' => $workflows,
            'workflowPreview' => $this->workflowService->getWorkflowPreview('VCH'),
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
            // AC#8 - Manual reference number input (validated in Form Request)
            $referenceNumber = $request->input('reference_number');

            DB::transaction(function () use ($request, $procurement, $referenceNumber) {
                $transaction = Transaction::create([
                    'procurement_id' => $procurement->id,
                    'category' => Transaction::CATEGORY_VOUCHER,
                    'reference_number' => $referenceNumber,
                    'status' => 'Created',
                    'workflow_id' => $request->input('workflow_id'),
                    'created_by_user_id' => auth()->id(),
                ]);

                try {
                    $this->workflowService->assignWorkflow($transaction, auth()->user());
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
            });

            // AC#16 - Success toast notification
            return redirect()
                ->route('procurements.show', $procurement)
                ->with('success', "Voucher {$referenceNumber} created successfully for Payee: {$request->input('payee')}");
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
            'transaction.currentStep.office',
            'transaction.createdBy:id,name',
        ])->findOrFail($id);

        // Load related PR and PO
        $purchaseRequest = $voucher->transaction->procurement->purchaseRequest;
        if ($purchaseRequest) {
            $purchaseRequest->load('transaction:id,reference_number');
        }

        $purchaseOrder = $voucher->transaction->procurement->purchaseOrder;
        if ($purchaseOrder) {
            $purchaseOrder->load(['transaction:id,reference_number', 'supplier:id,name']);
        }

        $user = auth()->user();
        $transaction = $voucher->transaction;

        $canEdit = $user->hasAnyRole(['Endorser', 'Administrator']);
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
            'transaction.procurement.endUser:id,name',
            'transaction.procurement.particular:id,description',
        ])->findOrFail($id);

        if (! auth()->user()->hasAnyRole(['Endorser', 'Administrator'])) {
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

        DB::transaction(function () use ($request, $voucher) {
            $voucher->update([
                'payee' => $request->input('payee'),
            ]);

            $voucher->transaction->update([
                'reference_number' => $request->input('reference_number'),
                'workflow_id' => $request->input('workflow_id'),
            ]);
        });

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
