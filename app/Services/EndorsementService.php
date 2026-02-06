<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Office;
use App\Models\Transaction;
use App\Models\TransactionAction;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Service for handling transaction endorsement operations.
 *
 * Story 3.4 - Endorse Action Implementation
 */
class EndorsementService
{
    /**
     * Endorse a transaction to move it to the next office.
     *
     * @param  Transaction  $transaction  The transaction to endorse
     * @param  User  $user  The user performing the endorsement
     * @param  int  $actionTakenId  The action taken repository ID
     * @param  int  $toOfficeId  The target office ID
     * @param  string|null  $notes  Optional notes for the endorsement
     * @return TransactionAction The created endorsement action
     */
    public function endorse(
        Transaction $transaction,
        User $user,
        int $actionTakenId,
        int $toOfficeId,
        ?string $notes = null
    ): TransactionAction {
        return DB::transaction(function () use ($transaction, $user, $actionTakenId, $toOfficeId, $notes) {
            // Determine if out-of-workflow
            $expectedNextOffice = $this->getExpectedNextOffice($transaction);
            $isOutOfWorkflow = $expectedNextOffice?->id !== $toOfficeId;

            // Create action record
            $action = TransactionAction::create([
                'transaction_id' => $transaction->id,
                'action_type' => TransactionAction::TYPE_ENDORSE,
                'action_taken_id' => $actionTakenId,
                'from_office_id' => $user->office_id,
                'to_office_id' => $toOfficeId,
                'from_user_id' => $user->id,
                'workflow_step_id' => $transaction->current_step_id,
                'is_out_of_workflow' => $isOutOfWorkflow,
                'notes' => $notes,
                'ip_address' => request()->ip(),
            ]);

            // Update transaction
            $transaction->update([
                'current_office_id' => $toOfficeId,
                'current_user_id' => null,
                'endorsed_at' => now(),
                'received_at' => null,
            ]);

            // Load relationships for return
            $action->load('toOffice');

            return $action;
        });
    }

    /**
     * Check if a user can endorse a transaction.
     *
     * @param  Transaction  $transaction  The transaction to check
     * @param  User  $user  The user attempting to endorse
     * @return bool True if the user can endorse
     */
    public function canEndorse(Transaction $transaction, User $user): bool
    {
        // User must have Endorser or Administrator role
        if (! $user->hasAnyRole(['Endorser', 'Administrator'])) {
            return false;
        }

        // User's office must match transaction's current office
        if ($user->office_id !== $transaction->current_office_id) {
            return false;
        }

        // Transaction must have been received
        if ($transaction->received_at === null) {
            return false;
        }

        // Transaction status must be "In Progress"
        if ($transaction->status !== 'In Progress') {
            return false;
        }

        // Transaction must not be at the final workflow step
        $transaction->load('currentStep');
        if ($transaction->currentStep?->is_final_step === true) {
            return false;
        }

        return true;
    }

    /**
     * Get the reason why a user cannot endorse a transaction.
     *
     * @param  Transaction  $transaction  The transaction to check
     * @param  User  $user  The user attempting to endorse
     * @return string|null Reason why endorsement is not allowed, or null if allowed
     */
    public function getCannotEndorseReason(Transaction $transaction, User $user): ?string
    {
        if (! $user->hasAnyRole(['Endorser', 'Administrator'])) {
            return 'You do not have permission to endorse transactions.';
        }

        if ($user->office_id !== $transaction->current_office_id) {
            return 'This transaction is not currently at your office.';
        }

        if ($transaction->received_at === null) {
            return 'Transaction must be received before endorsing.';
        }

        if ($transaction->status !== 'In Progress') {
            return 'Transaction status must be "In Progress" to endorse.';
        }

        $transaction->load('currentStep');
        if ($transaction->currentStep?->is_final_step === true) {
            return 'Transaction is at the final workflow step.';
        }

        return null;
    }

    /**
     * Get the expected next office in the workflow.
     *
     * @param  Transaction  $transaction  The transaction
     * @return Office|null The expected next office, or null if not determinable
     */
    public function getExpectedNextOffice(Transaction $transaction): ?Office
    {
        $transaction->load('currentStep');

        $nextStep = $transaction->currentStep?->getNextStep();

        return $nextStep?->office;
    }
}
