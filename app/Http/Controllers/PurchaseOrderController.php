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

    /**
     * Story 2.7 AC#6 - Display PO creation form with procurement summary, PR reference, and supplier list.
     */
    public function create(Procurement $procurement): Response|RedirectResponse
    {
        if (! $this->businessRules->canCreatePO($procurement)) {
            return redirect()
                ->route('procurements.show', $procurement)
                ->with('error', 'Purchase Request required before creating Purchase Order');
        }

        $procurement->load([
            'endUser:id,name,abbreviation',
            'particular:id,description',
            'purchaseRequest.fundType',
            'purchaseRequest.transaction',
        ])->makeVisible(['abc_amount']);

        $suppliers = \App\Models\Supplier::where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'address']);

        return Inertia::render('PurchaseOrders/Create', [
            'procurement' => $procurement,
            'purchaseRequest' => $procurement->purchaseRequest,
            'suppliers' => $suppliers,
        ]);
    }

    /**
     * Story 2.7 AC#8-9 - Create PO with manual reference number and supplier address snapshot.
     */
    public function store(StorePurchaseOrderRequest $request, Procurement $procurement): RedirectResponse
    {
        // AC#5 - Business rule validation
        if (! $this->businessRules->canCreatePO($procurement)) {
            return back()
                ->withInput()
                ->withErrors(['procurement' => 'Purchase Request required before creating Purchase Order']);
        }

        try {
            // AC#8 - Build reference number from manual inputs
            $referenceNumber = $this->refNumberService->buildPOReferenceNumber(
                $request->input('ref_year'),
                $request->input('ref_month'),
                $request->input('ref_number'),
                $request->boolean('is_continuation')
            );

            // AC#9-10 - Get supplier for address snapshot (immutable)
            $supplier = \App\Models\Supplier::findOrFail($request->input('supplier_id'));

            DB::transaction(function () use ($request, $procurement, $referenceNumber, $supplier) {
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
                    'supplier_id' => $supplier->id,
                    'supplier_address' => $supplier->address, // Snapshot
                    'contract_price' => $request->input('contract_price'),
                ]);

                // AC#11 - Update procurement status
                if ($procurement->status === 'Created') {
                    $procurement->update(['status' => 'In Progress']);
                }
            });

            // AC#12 - Redirect to procurement detail page
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

    /**
     * Story 2.7 AC#16 - Display PO details with supplier, contract price, related PR/procurement.
     */
    public function show(int $id): Response
    {
        $purchaseOrder = PurchaseOrder::with([
            'transaction.procurement.endUser:id,name,abbreviation',
            'transaction.procurement.particular:id,description',
            'transaction.createdBy:id,name',
            'supplier:id,name',
        ])->findOrFail($id);

        // Load related PR
        $purchaseRequest = $purchaseOrder->transaction->procurement->purchaseRequest;
        if ($purchaseRequest) {
            $purchaseRequest->load('transaction:id,reference_number');
        }

        $canEdit = auth()->user()->hasAnyRole(['Endorser', 'Administrator']);

        return Inertia::render('PurchaseOrders/Show', [
            'purchaseOrder' => $purchaseOrder,
            'purchaseRequest' => $purchaseRequest,
            'canEdit' => $canEdit,
        ]);
    }

    /**
     * Story 2.7 AC#13 - Display PO edit form with parsed reference number and supplier list.
     */
    public function edit(int $id): Response
    {
        $purchaseOrder = PurchaseOrder::with([
            'transaction.procurement.endUser:id,name',
            'transaction.procurement.particular:id,description',
            'supplier:id,name,address',
        ])->findOrFail($id);

        if (! auth()->user()->hasAnyRole(['Endorser', 'Administrator'])) {
            abort(403);
        }

        $suppliers = \App\Models\Supplier::where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'address']);

        return Inertia::render('PurchaseOrders/Edit', [
            'purchaseOrder' => $purchaseOrder,
            'suppliers' => $suppliers,
        ]);
    }

    /**
     * Story 2.7 AC#13-14 - Update PO with supplier change handling (address snapshot update).
     */
    public function update(UpdatePurchaseOrderRequest $request, int $id): RedirectResponse
    {
        $purchaseOrder = PurchaseOrder::with('transaction')->findOrFail($id);

        $newReferenceNumber = $this->refNumberService->buildPOReferenceNumber(
            $request->input('ref_year'),
            $request->input('ref_month'),
            $request->input('ref_number'),
            $request->boolean('is_continuation')
        );

        // AC#14 - If supplier changed, get new supplier's current address for snapshot update
        $supplierAddress = $purchaseOrder->supplier_address; // Keep existing by default
        if ($request->input('supplier_id') !== $purchaseOrder->supplier_id) {
            $newSupplier = \App\Models\Supplier::findOrFail($request->input('supplier_id'));
            $supplierAddress = $newSupplier->address; // Update to new supplier's address
        }

        DB::transaction(function () use ($request, $purchaseOrder, $newReferenceNumber, $supplierAddress) {
            $purchaseOrder->update([
                'supplier_id' => $request->input('supplier_id'),
                'supplier_address' => $supplierAddress,
                'contract_price' => $request->input('contract_price'),
            ]);

            $purchaseOrder->transaction->update([
                'reference_number' => $newReferenceNumber,
                'is_continuation' => $request->boolean('is_continuation'),
                'workflow_id' => $request->input('workflow_id'),
            ]);
        });

        return redirect()
            ->route('purchase-orders.show', $id)
            ->with('success', 'Purchase Order updated successfully');
    }

    /**
     * Story 2.7 AC#15 - Soft delete PO with business rule validation (cannot delete if VCH exists).
     */
    public function destroy(int $id): RedirectResponse
    {
        $purchaseOrder = PurchaseOrder::with('transaction.procurement')->findOrFail($id);
        $procurement = $purchaseOrder->transaction->procurement;

        // AC#15 - Business rule: cannot delete PO if Voucher exists
        if (! $this->businessRules->canDeletePO($procurement)) {
            $voucher = $procurement->voucher;
            $voucherRef = $voucher?->transaction?->reference_number ?? 'VCH-UNKNOWN';

            return back()
                ->with('error', "Cannot delete Purchase Order because Voucher {$voucherRef} exists. Delete the Voucher first.");
        }

        DB::transaction(function () use ($purchaseOrder) {
            $purchaseOrder->transaction->delete(); // Soft delete
            $purchaseOrder->delete(); // Soft delete
        });

        return redirect()
            ->route('procurements.show', $procurement->id)
            ->with('success', 'Purchase Order deleted successfully');
    }
}
