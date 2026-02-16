<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ReceiveBulkRequest;
use App\Http\Requests\ReceiveTransactionRequest;
use App\Models\Transaction;
use App\Services\EndorsementService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Controller for transaction receive operations.
 *
 * Story 3.5 - Receive Action Implementation
 */
class TransactionReceiveController extends Controller
{
    public function __construct(
        private readonly EndorsementService $endorsementService
    ) {}

    /**
     * Display pending transactions awaiting receipt.
     */
    public function pending(): Response
    {
        $user = auth()->user();

        $transactions = Transaction::query()
            ->select(
                'transactions.id',
                'transactions.reference_number',
                'transactions.category',
                'transactions.status',
                'transactions.endorsed_at',
                'transactions.current_office_id',
                'transactions.procurement_id',
            )
            ->with([
                'procurement:id,purpose,end_user_id',
                'procurement.endUser:id,name',
            ])
            ->where('current_office_id', $user->office_id)
            ->whereNull('received_at')
            ->where('status', 'In Progress')
            ->orderBy('endorsed_at', 'asc')
            ->paginate(20);

        // Attach the from_office info from the last endorsement action for each transaction
        $transactions->getCollection()->transform(function ($transaction) {
            $lastEndorsement = $transaction->actions()
                ->where('action_type', 'endorse')
                ->where('to_office_id', $transaction->current_office_id)
                ->with('fromOffice:id,name')
                ->latest()
                ->first();

            $transaction->from_office_name = $lastEndorsement?->fromOffice?->name ?? 'Unknown';

            return $transaction;
        });

        return Inertia::render('Transactions/Pending', [
            'transactions' => $transactions,
        ]);
    }

    /**
     * Process a single transaction receive.
     */
    public function store(ReceiveTransactionRequest $request, Transaction $transaction): RedirectResponse
    {
        $this->authorize('receive', $transaction);

        $this->endorsementService->receive(
            $transaction,
            $request->user(),
            $request->validated('notes')
        );

        return redirect()
            ->route('transactions.pending')
            ->with('success', 'Transaction received successfully.');
    }

    /**
     * Process bulk transaction receive.
     */
    public function storeBulk(ReceiveBulkRequest $request): RedirectResponse
    {
        $results = $this->endorsementService->receiveBulk(
            $request->validated('transaction_ids'),
            $request->user(),
            $request->validated('notes')
        );

        $successCount = count($results['success']);
        $failedCount = count($results['failed']);

        if ($failedCount === 0) {
            return redirect()
                ->route('transactions.pending')
                ->with('success', "{$successCount} transaction(s) received successfully.");
        }

        return redirect()
            ->route('transactions.pending')
            ->with('success', "{$successCount} transaction(s) received, {$failedCount} failed.");
    }
}
