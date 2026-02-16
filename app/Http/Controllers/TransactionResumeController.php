<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Services\EndorsementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Controller for transaction resume operations.
 *
 * Story 3.7 - Transaction State Machine
 */
class TransactionResumeController extends Controller
{
    public function __construct(
        private readonly EndorsementService $endorsementService
    ) {}

    /**
     * Resume a transaction from On Hold.
     */
    public function store(Request $request, Transaction $transaction): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $this->endorsementService->resume(
            $transaction,
            $request->user(),
            $validated['reason'] ?? null
        );

        return redirect()
            ->back()
            ->with('success', 'Transaction resumed.');
    }
}
