<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\EndorseTransactionRequest;
use App\Models\Transaction;
use App\Services\EndorsementService;
use App\Services\TransactionHandoffPresenter;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Controller for transaction endorsement operations.
 *
 * Story 3.4 - Endorse Action Implementation
 */
class TransactionEndorseController extends Controller
{
    public function __construct(
        private readonly EndorsementService $endorsementService,
        private readonly TransactionHandoffPresenter $presenter
    ) {}

    /**
     * Show the endorsement form.
     */
    public function create(Transaction $transaction): Response
    {
        $this->authorize('endorse', $transaction);

        return Inertia::render('Transactions/Endorse', $this->presenter->present($transaction, 'endorse'));
    }

    /**
     * Process the endorsement.
     */
    public function store(EndorseTransactionRequest $request, Transaction $transaction): RedirectResponse
    {
        $this->authorize('endorse', $transaction);

        $action = $this->endorsementService->endorse(
            $transaction,
            $request->user(),
            (int) $request->validated('action_taken_id'),
            (int) $request->validated('to_office_id'),
            $request->validated('scanned_reference'),
            $request->validated('notes')
        );

        $entityShowRoute = $this->presenter->entityShowRoute($transaction);

        return redirect()
            ->route($entityShowRoute['route'], $entityShowRoute['id'])
            ->with('success', "Transaction endorsed to {$action->toOffice->name}");
    }
}
