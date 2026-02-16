<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\CancelTransactionRequest;
use App\Models\Transaction;
use App\Services\EndorsementService;
use Illuminate\Http\RedirectResponse;

/**
 * Controller for transaction cancel operations.
 *
 * Story 3.7 - Transaction State Machine
 */
class TransactionCancelController extends Controller
{
    public function __construct(
        private readonly EndorsementService $endorsementService
    ) {}

    /**
     * Cancel a transaction.
     */
    public function store(CancelTransactionRequest $request, Transaction $transaction): RedirectResponse
    {
        $this->endorsementService->cancel(
            $transaction,
            $request->user(),
            $request->validated('reason')
        );

        return redirect()
            ->back()
            ->with('success', 'Transaction cancelled.');
    }
}
