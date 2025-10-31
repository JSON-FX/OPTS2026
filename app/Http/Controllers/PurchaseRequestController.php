<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StorePurchaseRequestRequest;
use App\Http\Requests\UpdatePurchaseRequestRequest;
use App\Models\FundType;
use App\Models\Procurement;
use App\Models\PurchaseRequest;
use App\Models\Transaction;
use App\Services\ProcurementBusinessRules;
use App\Services\ReferenceNumberService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class PurchaseRequestController extends Controller
{
    public function __construct(
        private readonly ReferenceNumberService $refNumberService,
        private readonly ProcurementBusinessRules $businessRules
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

        return Inertia::render('PurchaseRequests/Create', [
            'procurement' => $procurement,
            'fundTypes' => $fundTypes,
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
            'fundType',
            'transaction.createdBy',
        ])->findOrFail($id);

        $canEdit = auth()->user()->hasAnyRole(['Endorser', 'Administrator']);
        $canDelete = $canEdit && $this->businessRules->canDeletePR($purchaseRequest->transaction->procurement);

        return Inertia::render('PurchaseRequests/Show', [
            'purchaseRequest' => $purchaseRequest,
            'canEdit' => $canEdit,
            'canDelete' => $canDelete,
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
