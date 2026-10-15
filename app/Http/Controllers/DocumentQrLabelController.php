<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Services\TransactionHandoffPresenter;
use Inertia\Inertia;
use Inertia\Response;

class DocumentQrLabelController extends Controller
{
    public function __invoke(Transaction $transaction, TransactionHandoffPresenter $presenter): Response
    {
        $this->authorize('view', $transaction);

        return Inertia::render('Transactions/QrLabel', [
            'document' => [
                'category' => $transaction->category,
                'reference' => $transaction->reference_number,
                'procurementId' => $transaction->procurement_id,
            ],
            'entityShowRoute' => $presenter->entityShowRoute($transaction),
        ]);
    }
}
