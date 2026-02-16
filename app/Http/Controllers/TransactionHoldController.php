<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\HoldTransactionRequest;
use App\Models\Transaction;
use App\Services\EndorsementService;
use Illuminate\Http\RedirectResponse;

/**
 * Controller for transaction hold operations.
 *
 * Story 3.7 - Transaction State Machine
 */
class TransactionHoldController extends Controller
{
    public function __construct(
        private readonly EndorsementService $endorsementService
    ) {}

    /**
     * Place a transaction on hold.
     */
    public function store(HoldTransactionRequest $request, Transaction $transaction): RedirectResponse
    {
        $this->endorsementService->hold(
            $transaction,
            $request->user(),
            $request->validated('reason')
        );

        return redirect()
            ->back()
            ->with('success', 'Transaction placed on hold.');
    }
}
