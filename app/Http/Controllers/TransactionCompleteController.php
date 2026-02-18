<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\CompleteTransactionRequest;
use App\Models\Transaction;
use App\Services\EndorsementService;
use Illuminate\Http\RedirectResponse;

/**
 * Controller for transaction complete operations.
 *
 * Story 3.6 - Complete Action Implementation
 */
class TransactionCompleteController extends Controller
{
    public function __construct(
        private readonly EndorsementService $endorsementService
    ) {}

    /**
     * Process the transaction completion.
     */
    public function store(CompleteTransactionRequest $request, Transaction $transaction): RedirectResponse
    {
        $this->authorize('complete', $transaction);

        $this->endorsementService->complete(
            $transaction,
            $request->user(),
            (int) $request->validated('action_taken_id'),
            $request->validated('notes')
        );

        $message = 'Transaction completed successfully';

        // Check if procurement also completed
        $procurement = $transaction->fresh()->procurement;
        if ($procurement->status === 'Completed') {
            $message .= '. Procurement fully completed!';
        }

        // Determine redirect based on transaction category
        $redirectRoute = match ($transaction->category) {
            'PO' => 'purchase-orders.show',
            'VCH' => 'vouchers.show',
            default => 'purchase-requests.show',
        };

        $entityId = match ($transaction->category) {
            'PO' => $transaction->purchaseOrder?->id ?? $transaction->id,
            'VCH' => $transaction->voucher?->id ?? $transaction->id,
            default => $transaction->purchaseRequest?->id ?? $transaction->id,
        };

        return redirect()
            ->route($redirectRoute, $entityId)
            ->with('success', $message);
    }
}
