<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreVoucherRequest;
use App\Http\Requests\UpdateVoucherRequest;
use App\Models\Procurement;
use App\Models\Transaction;
use App\Models\Voucher;
use App\Services\ProcurementBusinessRules;
use App\Services\ReferenceNumberService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class VoucherController extends Controller
{
    public function __construct(
        private readonly ReferenceNumberService $refNumberService,
        private readonly ProcurementBusinessRules $businessRules
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

        return Inertia::render('Vouchers/Create', [
            'procurement' => $procurement,
            'purchaseRequest' => $procurement->purchaseRequest, // Includes transaction + fundType
            'purchaseOrder' => $procurement->purchaseOrder, // Includes transaction + supplier + contract_price
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
            'transaction.createdBy:id,name',
        ])->findOrFail($id);

        // Load related PR and PO
        $purchaseRequest = $voucher->transaction->procurement->purchaseRequest;
        if ($purchaseRequest) {
            $purchaseRequest->load('transaction:id,reference_number');
        }

        $purchaseOrder = $voucher->transaction->procurement->purchaseOrder;
        if ($purchaseOrder) {
            $purchaseOrder->load('transaction:id,reference_number');
        }

        $canEdit = auth()->user()->hasAnyRole(['Endorser', 'Administrator']);

        return Inertia::render('Vouchers/Show', [
            'voucher' => $voucher,
            'purchaseRequest' => $purchaseRequest,
            'purchaseOrder' => $purchaseOrder,
            'canEdit' => $canEdit,
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
