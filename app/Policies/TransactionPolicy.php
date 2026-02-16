<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Transaction;
use App\Models\User;
use App\Services\EndorsementService;

/**
 * Policy for Transaction authorization.
 *
 * Story 3.4 - Endorse Action Implementation
 */
class TransactionPolicy
{
    public function __construct(
        private readonly EndorsementService $endorsementService
    ) {}

    /**
     * Determine if the user can endorse the transaction.
     */
    public function endorse(User $user, Transaction $transaction): bool
    {
        return $this->endorsementService->canEndorse($transaction, $user);
    }

    /**
     * Determine if the user can receive the transaction.
     *
     * Story 3.5 - Receive Action Implementation
     */
    public function receive(User $user, Transaction $transaction): bool
    {
        return $this->endorsementService->canReceive($transaction, $user);
    }

    /**
     * Determine if the user can complete the transaction.
     *
     * Story 3.6 - Complete Action Implementation
     */
    public function complete(User $user, Transaction $transaction): bool
    {
        return $this->endorsementService->canComplete($transaction, $user);
    }

    /**
     * Determine if the user can view the transaction.
     */
    public function view(User $user, Transaction $transaction): bool
    {
        // All authenticated users can view transactions
        return true;
    }
}
