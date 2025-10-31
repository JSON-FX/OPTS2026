<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StorePurchaseOrderRequest;
use App\Http\Requests\UpdatePurchaseOrderRequest;
use App\Models\Procurement;
use App\Models\PurchaseOrder;
use App\Models\Transaction;
use App\Services\ProcurementBusinessRules;
use App\Services\ReferenceNumberService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class PurchaseOrderController extends Controller
{
    public function __construct(
        private readonly ReferenceNumberService $refNumberService,
        private readonly ProcurementBusinessRules $businessRules
    ) {}

    public function create(Procurement $procurement): Response|RedirectResponse
    {
        if (! $this->businessRules->canCreatePO($procurement)) {
            abort(403, 'Cannot create Purchase Order for this procurement');
        }

        $procurement->load(['endUser', 'particular', 'purchaseRequest']);

        return Inertia::render('PurchaseOrders/Create', [
            'procurement' => $procurement,
        ]);
    }

    public function store(StorePurchaseOrderRequest $request, Procurement $procurement): RedirectResponse
    {
        try {
            $referenceNumber = $this->refNumberService->buildPOReferenceNumber(
                $request->input('ref_year'),
                $request->input('ref_month'),
                $request->input('ref_number'),
                $request->boolean('is_continuation')
            );

            DB::transaction(function () use ($request, $procurement, $referenceNumber) {
                $transaction = Transaction::create([
                    'procurement_id' => $procurement->id,
                    'category' => Transaction::CATEGORY_PURCHASE_ORDER,
                    'reference_number' => $referenceNumber,
                    'is_continuation' => $request->boolean('is_continuation'),
                    'status' => 'Created',
                    'workflow_id' => $request->input('workflow_id'),
                    'created_by_user_id' => auth()->id(),
                ]);

                PurchaseOrder::create([
                    'transaction_id' => $transaction->id,
                    'supplier_id' => $request->input('supplier_id'),
                    'supplier_address' => $request->input('supplier_address'),
                    'purchase_request_id' => $request->input('purchase_request_id'),
                    'particulars' => $request->input('particulars'),
                    'fund_type_id' => $request->input('fund_type_id'),
                    'total_cost' => $request->input('total_cost'),
                    'date_of_po' => $request->input('date_of_po'),
                    'delivery_date' => $request->input('delivery_date'),
                    'delivery_term' => $request->input('delivery_term'),
                    'payment_term' => $request->input('payment_term'),
                    'amount_in_words' => $request->input('amount_in_words'),
                    'mode_of_procurement' => $request->input('mode_of_procurement'),
                ]);

                if ($procurement->status === 'Created') {
                    $procurement->update(['status' => 'In Progress']);
                }
            });

            return redirect()
                ->route('procurements.show', $procurement)
                ->with('success', "Purchase Order {$referenceNumber} created successfully");
        } catch (\Exception $e) {
            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'Error creating Purchase Order: '.$e->getMessage());
        }
    }

    public function show(int $id): Response
    {
        $purchaseOrder = PurchaseOrder::with([
            'transaction.procurement.endUser',
            'transaction.procurement.particular',
            'transaction.procurement', // Ensure procurement is fully loaded
            'transaction.createdBy',
        ])->findOrFail($id);

        $canEdit = auth()->user()->hasAnyRole(['Endorser', 'Administrator']);

        return Inertia::render('PurchaseOrders/Show', [
            'purchaseOrder' => $purchaseOrder,
            'canEdit' => $canEdit,
        ]);
    }

    public function edit(int $id): Response
    {
        $purchaseOrder = PurchaseOrder::with([
            'transaction.procurement',
        ])->findOrFail($id);

        if (! auth()->user()->hasAnyRole(['Endorser', 'Administrator'])) {
            abort(403);
        }

        return Inertia::render('PurchaseOrders/Edit', [
            'purchaseOrder' => $purchaseOrder,
        ]);
    }

    public function update(UpdatePurchaseOrderRequest $request, int $id): RedirectResponse
    {
        $purchaseOrder = PurchaseOrder::with('transaction')->findOrFail($id);

        $newReferenceNumber = $this->refNumberService->buildPOReferenceNumber(
            $request->input('ref_year'),
            $request->input('ref_month'),
            $request->input('ref_number'),
            $request->boolean('is_continuation')
        );

        DB::transaction(function () use ($request, $purchaseOrder, $newReferenceNumber) {
            $purchaseOrder->update([
                'supplier_id' => $request->input('supplier_id'),
                'supplier_address' => $request->input('supplier_address'),
                'purchase_request_id' => $request->input('purchase_request_id'),
                'particulars' => $request->input('particulars'),
                'fund_type_id' => $request->input('fund_type_id'),
                'total_cost' => $request->input('total_cost'),
                'date_of_po' => $request->input('date_of_po'),
                'delivery_date' => $request->input('delivery_date'),
                'delivery_term' => $request->input('delivery_term'),
                'payment_term' => $request->input('payment_term'),
                'amount_in_words' => $request->input('amount_in_words'),
                'mode_of_procurement' => $request->input('mode_of_procurement'),
            ]);

            $purchaseOrder->transaction->update([
                'reference_number' => $newReferenceNumber,
                'is_continuation' => $request->boolean('is_continuation'),
            ]);
        });

        return redirect()
            ->route('purchase-orders.show', $id)
            ->with('success', 'Purchase Order updated successfully');
    }

    public function destroy(int $id): RedirectResponse
    {
        $purchaseOrder = PurchaseOrder::with('transaction.procurement')->findOrFail($id);

        DB::transaction(function () use ($purchaseOrder) {
            $purchaseOrder->transaction->delete();
            $purchaseOrder->delete();
        });

        return redirect()
            ->route('procurements.show', $purchaseOrder->transaction->procurement_id)
            ->with('success', 'Purchase Order deleted successfully');
    }
}
