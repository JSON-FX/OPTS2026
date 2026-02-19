<?php

declare(strict_types=1);

namespace App\Services;

use App\Events\OutOfWorkflowEndorsement;
use App\Events\TransactionCompleted;
use App\Events\TransactionReceived;
use App\Exceptions\InvalidStateTransitionException;
use App\Models\Office;
use App\Models\ProcurementStatusHistory;
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
            // Auto-transition Created → In Progress on first endorsement (Story 3.7)
            if ($transaction->status === 'Created') {
                $stateMachine = new TransactionStateMachine($transaction);
                $stateMachine->transitionTo('In Progress', 'First endorsement', $user);
            }

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

            // Fire out-of-workflow event if applicable (Story 3.8)
            if ($isOutOfWorkflow) {
                $action->load(['transaction.currentStep', 'toOffice', 'fromUser']);
                event(new OutOfWorkflowEndorsement($action));
            } else {
                // Load relationships for return
                $action->load('toOffice');
            }

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

        // Transaction must have been received (unless Created status for first endorsement)
        if ($transaction->received_at === null && $transaction->status !== 'Created') {
            return false;
        }

        // Transaction status must be "In Progress" or "Created" (for first endorsement)
        if (! in_array($transaction->status, ['In Progress', 'Created'], true)) {
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

        if ($transaction->received_at === null && $transaction->status !== 'Created') {
            return 'Transaction must be received before endorsing.';
        }

        if (! in_array($transaction->status, ['In Progress', 'Created'], true)) {
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

    /**
     * Receive a transaction that has been endorsed to the user's office.
     *
     * Story 3.5 - Receive Action Implementation
     *
     * @param  Transaction  $transaction  The transaction to receive
     * @param  User  $user  The user performing the receive
     * @param  string|null  $notes  Optional notes for the receipt
     * @return TransactionAction The created receive action
     *
     * @throws \InvalidArgumentException If the user cannot receive the transaction
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException If no endorsement found
     */
    public function receive(
        Transaction $transaction,
        User $user,
        ?string $notes = null
    ): TransactionAction {
        $action = DB::transaction(function () use ($transaction, $user, $notes) {
            $this->verifyCanReceive($transaction, $user);

            // Get the last endorsement to this office
            $lastEndorsement = $transaction->actions()
                ->where('action_type', TransactionAction::TYPE_ENDORSE)
                ->where('to_office_id', $user->office_id)
                ->latest()
                ->firstOrFail();

            // Create receive action
            $action = TransactionAction::create([
                'transaction_id' => $transaction->id,
                'action_type' => TransactionAction::TYPE_RECEIVE,
                'from_office_id' => $lastEndorsement->from_office_id,
                'to_office_id' => $user->office_id,
                'from_user_id' => $lastEndorsement->from_user_id,
                'to_user_id' => $user->id,
                'workflow_step_id' => $transaction->current_step_id,
                'notes' => $notes,
                'ip_address' => request()->ip(),
            ]);

            // Update transaction
            $transaction->update([
                'current_user_id' => $user->id,
                'received_at' => now(),
            ]);

            // Advance workflow step if in-workflow
            if (! $lastEndorsement->is_out_of_workflow) {
                $this->advanceWorkflowStep($transaction);
            }

            return $action;
        });

        // Fire received event for notification (Story 4.2.1)
        $action->load(['transaction', 'fromOffice', 'toOffice']);
        event(new TransactionReceived($action));

        return $action;
    }

    /**
     * Receive multiple transactions in bulk.
     *
     * Story 3.5 - Receive Action Implementation
     *
     * @param  array<int>  $transactionIds  The IDs of transactions to receive
     * @param  User  $user  The user performing the receive
     * @param  string|null  $notes  Optional notes for the receipts
     * @return array{success: array<int>, failed: array<int, string>}
     */
    public function receiveBulk(array $transactionIds, User $user, ?string $notes = null): array
    {
        $results = ['success' => [], 'failed' => []];

        foreach ($transactionIds as $id) {
            try {
                $transaction = Transaction::findOrFail($id);
                $this->receive($transaction, $user, $notes);
                $results['success'][] = $id;
            } catch (\Exception $e) {
                $results['failed'][$id] = $e->getMessage();
            }
        }

        return $results;
    }

    /**
     * Check if a user can receive a transaction.
     *
     * Story 3.5 - Receive Action Implementation
     *
     * @param  Transaction  $transaction  The transaction to check
     * @param  User  $user  The user attempting to receive
     * @return bool True if the user can receive
     */
    public function canReceive(Transaction $transaction, User $user): bool
    {
        // User must have Endorser or Administrator role
        if (! $user->hasAnyRole(['Endorser', 'Administrator'])) {
            return false;
        }

        // User's office must match transaction's current office
        if ($user->office_id !== $transaction->current_office_id) {
            return false;
        }

        // Transaction must NOT have been received
        if ($transaction->received_at !== null) {
            return false;
        }

        // Transaction status must be "In Progress"
        if ($transaction->status !== 'In Progress') {
            return false;
        }

        return true;
    }

    /**
     * Get the reason why a user cannot receive a transaction.
     *
     * Story 3.5 - Receive Action Implementation
     *
     * @param  Transaction  $transaction  The transaction to check
     * @param  User  $user  The user attempting to receive
     * @return string|null Reason why receive is not allowed, or null if allowed
     */
    public function getCannotReceiveReason(Transaction $transaction, User $user): ?string
    {
        if (! $user->hasAnyRole(['Endorser', 'Administrator'])) {
            return 'You do not have permission to receive transactions.';
        }

        if ($user->office_id !== $transaction->current_office_id) {
            return 'This transaction is not currently at your office.';
        }

        if ($transaction->received_at !== null) {
            return 'Transaction has already been received.';
        }

        if ($transaction->status !== 'In Progress') {
            return 'Transaction status must be "In Progress" to receive.';
        }

        return null;
    }

    /**
     * Verify that a user can receive a transaction, throwing an exception if not.
     *
     * @param  Transaction  $transaction  The transaction to verify
     * @param  User  $user  The user to verify
     *
     * @throws \InvalidArgumentException If the user cannot receive
     */
    protected function verifyCanReceive(Transaction $transaction, User $user): void
    {
        $reason = $this->getCannotReceiveReason($transaction, $user);
        if ($reason !== null) {
            throw new \InvalidArgumentException($reason);
        }
    }

    /**
     * Advance the transaction to the next workflow step.
     *
     * Story 3.5 - Step advancement happens on RECEIVE, not on ENDORSE.
     *
     * @param  Transaction  $transaction  The transaction to advance
     */
    protected function advanceWorkflowStep(Transaction $transaction): void
    {
        $nextStep = $transaction->currentStep?->getNextStep();
        if ($nextStep) {
            $transaction->update(['current_step_id' => $nextStep->id]);
        }
    }

    /**
     * Complete a transaction at the final workflow step.
     *
     * Story 3.6 - Complete Action Implementation
     *
     * @param  Transaction  $transaction  The transaction to complete
     * @param  User  $user  The user performing the complete
     * @param  int  $actionTakenId  The action taken repository ID
     * @param  string|null  $notes  Optional notes
     * @return TransactionAction The created complete action
     *
     * @throws \InvalidArgumentException If the user cannot complete the transaction
     */
    public function complete(
        Transaction $transaction,
        User $user,
        int $actionTakenId,
        ?string $notes = null
    ): TransactionAction {
        $action = DB::transaction(function () use ($transaction, $user, $actionTakenId, $notes) {
            $this->verifyCanComplete($transaction, $user);

            // Create complete action
            $action = TransactionAction::create([
                'transaction_id' => $transaction->id,
                'action_type' => TransactionAction::TYPE_COMPLETE,
                'action_taken_id' => $actionTakenId,
                'from_office_id' => $user->office_id,
                'to_office_id' => null,
                'from_user_id' => $user->id,
                'workflow_step_id' => $transaction->current_step_id,
                'notes' => $notes,
                'ip_address' => request()->ip(),
            ]);

            // Transition status via state machine (Story 3.7)
            $stateMachine = new TransactionStateMachine($transaction);
            $stateMachine->transitionTo('Completed', null, $user);

            // Check procurement completion
            $this->checkAndUpdateProcurementStatus($transaction, $user);

            return $action;
        });

        // Fire completed event for notification (Story 4.2.1)
        event(new TransactionCompleted($transaction->fresh(), $user));

        return $action;
    }

    /**
     * Check if a user can complete a transaction.
     *
     * Story 3.6 - Complete Action Implementation
     *
     * @param  Transaction  $transaction  The transaction to check
     * @param  User  $user  The user attempting to complete
     * @return bool True if the user can complete
     */
    public function canComplete(Transaction $transaction, User $user): bool
    {
        if (! $user->hasAnyRole(['Endorser', 'Administrator'])) {
            return false;
        }

        if ($user->office_id !== $transaction->current_office_id) {
            return false;
        }

        if ($transaction->received_at === null) {
            return false;
        }

        if ($transaction->status !== 'In Progress') {
            return false;
        }

        $transaction->load('currentStep');
        if ($transaction->currentStep?->is_final_step !== true) {
            return false;
        }

        return true;
    }

    /**
     * Get the reason why a user cannot complete a transaction.
     *
     * Story 3.6 - Complete Action Implementation
     *
     * @param  Transaction  $transaction  The transaction to check
     * @param  User  $user  The user attempting to complete
     * @return string|null Reason why complete is not allowed, or null if allowed
     */
    public function getCannotCompleteReason(Transaction $transaction, User $user): ?string
    {
        if (! $user->hasAnyRole(['Endorser', 'Administrator'])) {
            return 'You do not have permission to complete transactions.';
        }

        if ($user->office_id !== $transaction->current_office_id) {
            return 'This transaction is not currently at your office.';
        }

        if ($transaction->received_at === null) {
            return 'Transaction must be received before completing.';
        }

        if ($transaction->status !== 'In Progress') {
            return 'Transaction status must be "In Progress" to complete.';
        }

        $transaction->load('currentStep');
        if ($transaction->currentStep?->is_final_step !== true) {
            return 'Transaction is not at the final workflow step.';
        }

        return null;
    }

    /**
     * Verify that a user can complete a transaction, throwing an exception if not.
     *
     * @param  Transaction  $transaction  The transaction to verify
     * @param  User  $user  The user to verify
     *
     * @throws \InvalidArgumentException If the user cannot complete
     */
    protected function verifyCanComplete(Transaction $transaction, User $user): void
    {
        $reason = $this->getCannotCompleteReason($transaction, $user);
        if ($reason !== null) {
            throw new \InvalidArgumentException($reason);
        }
    }

    /**
     * Check if procurement status should be updated after transaction completion.
     *
     * Story 3.6 FR8 - Only VCH completion can complete Procurement.
     * Procurement is completed when PR, PO, and VCH are all Completed.
     *
     * @param  Transaction  $transaction  The just-completed transaction
     * @param  User  $user  The user who completed the transaction
     */
    public function checkAndUpdateProcurementStatus(Transaction $transaction, User $user): void
    {
        if ($transaction->category !== 'VCH') {
            return;
        }

        $procurement = $transaction->procurement;

        // Check if all transactions for this procurement are completed
        $allCompleted = $procurement->transactions()
            ->whereIn('category', ['PR', 'PO', 'VCH'])
            ->where('status', '!=', 'Completed')
            ->doesntExist();

        if ($allCompleted) {
            $oldStatus = $procurement->status;
            $procurement->update(['status' => 'Completed']);

            ProcurementStatusHistory::create([
                'procurement_id' => $procurement->id,
                'old_status' => $oldStatus,
                'new_status' => 'Completed',
                'reason' => 'All transactions (PR, PO, VCH) completed',
                'changed_by_user_id' => $user->id,
            ]);
        }
    }

    /**
     * Place a transaction on hold.
     *
     * Story 3.7 - Transaction State Machine
     *
     * @param  Transaction  $transaction  The transaction to hold
     * @param  User  $user  The admin performing the hold
     * @param  string  $reason  Required reason for holding
     * @return TransactionAction The created hold action
     *
     * @throws \InvalidArgumentException If the user cannot hold
     * @throws InvalidStateTransitionException If transition is invalid
     */
    public function hold(Transaction $transaction, User $user, string $reason): TransactionAction
    {
        return DB::transaction(function () use ($transaction, $user, $reason) {
            $this->verifyCanHold($transaction, $user);

            $stateMachine = new TransactionStateMachine($transaction);
            $stateMachine->transitionTo('On Hold', $reason, $user);

            return TransactionAction::create([
                'transaction_id' => $transaction->id,
                'action_type' => TransactionAction::TYPE_HOLD,
                'from_office_id' => $user->office_id,
                'from_user_id' => $user->id,
                'workflow_step_id' => $transaction->current_step_id,
                'reason' => $reason,
                'ip_address' => request()->ip(),
            ]);
        });
    }

    /**
     * Check if a user can hold a transaction.
     *
     * Story 3.7 - Transaction State Machine
     */
    public function canHold(Transaction $transaction, User $user): bool
    {
        if (! $user->hasRole('Administrator')) {
            return false;
        }

        if ($transaction->status !== 'In Progress') {
            return false;
        }

        return true;
    }

    /**
     * Get the reason why a user cannot hold a transaction.
     *
     * Story 3.7 - Transaction State Machine
     */
    public function getCannotHoldReason(Transaction $transaction, User $user): ?string
    {
        if (! $user->hasRole('Administrator')) {
            return 'Only administrators can hold transactions.';
        }

        if ($transaction->status !== 'In Progress') {
            return 'Transaction must be "In Progress" to place on hold.';
        }

        return null;
    }

    /**
     * Verify that a user can hold a transaction.
     *
     * @throws \InvalidArgumentException
     */
    protected function verifyCanHold(Transaction $transaction, User $user): void
    {
        $reason = $this->getCannotHoldReason($transaction, $user);
        if ($reason !== null) {
            throw new \InvalidArgumentException($reason);
        }
    }

    /**
     * Cancel a transaction.
     *
     * Story 3.7 - Transaction State Machine
     *
     * @param  Transaction  $transaction  The transaction to cancel
     * @param  User  $user  The admin performing the cancellation
     * @param  string  $reason  Required reason for cancellation
     * @return TransactionAction The created cancel action
     *
     * @throws \InvalidArgumentException If the user cannot cancel
     * @throws InvalidStateTransitionException If transition is invalid
     */
    public function cancel(Transaction $transaction, User $user, string $reason): TransactionAction
    {
        return DB::transaction(function () use ($transaction, $user, $reason) {
            $this->verifyCanCancel($transaction, $user);

            $stateMachine = new TransactionStateMachine($transaction);
            $stateMachine->transitionTo('Cancelled', $reason, $user);

            return TransactionAction::create([
                'transaction_id' => $transaction->id,
                'action_type' => TransactionAction::TYPE_CANCEL,
                'from_office_id' => $user->office_id,
                'from_user_id' => $user->id,
                'workflow_step_id' => $transaction->current_step_id,
                'reason' => $reason,
                'ip_address' => request()->ip(),
            ]);
        });
    }

    /**
     * Check if a user can cancel a transaction.
     *
     * Story 3.7 - Transaction State Machine
     */
    public function canCancel(Transaction $transaction, User $user): bool
    {
        if (! $user->hasRole('Administrator')) {
            return false;
        }

        if (! in_array($transaction->status, ['In Progress', 'On Hold'], true)) {
            return false;
        }

        return true;
    }

    /**
     * Get the reason why a user cannot cancel a transaction.
     *
     * Story 3.7 - Transaction State Machine
     */
    public function getCannotCancelReason(Transaction $transaction, User $user): ?string
    {
        if (! $user->hasRole('Administrator')) {
            return 'Only administrators can cancel transactions.';
        }

        if (! in_array($transaction->status, ['In Progress', 'On Hold'], true)) {
            return 'Transaction must be "In Progress" or "On Hold" to cancel.';
        }

        return null;
    }

    /**
     * Verify that a user can cancel a transaction.
     *
     * @throws \InvalidArgumentException
     */
    protected function verifyCanCancel(Transaction $transaction, User $user): void
    {
        $reason = $this->getCannotCancelReason($transaction, $user);
        if ($reason !== null) {
            throw new \InvalidArgumentException($reason);
        }
    }

    /**
     * Resume a transaction from On Hold back to In Progress.
     *
     * Story 3.7 - Transaction State Machine
     *
     * @param  Transaction  $transaction  The transaction to resume
     * @param  User  $user  The admin performing the resume
     * @param  string|null  $reason  Optional reason for resuming
     *
     * @throws \InvalidArgumentException If the user cannot resume
     * @throws InvalidStateTransitionException If transition is invalid
     */
    public function resume(Transaction $transaction, User $user, ?string $reason = null): void
    {
        $this->verifyCanResume($transaction, $user);

        $stateMachine = new TransactionStateMachine($transaction);
        $stateMachine->transitionTo('In Progress', $reason ?? 'Resumed by administrator', $user);
    }

    /**
     * Check if a user can resume a transaction.
     *
     * Story 3.7 - Transaction State Machine
     */
    public function canResume(Transaction $transaction, User $user): bool
    {
        if (! $user->hasRole('Administrator')) {
            return false;
        }

        if ($transaction->status !== 'On Hold') {
            return false;
        }

        return true;
    }

    /**
     * Get the reason why a user cannot resume a transaction.
     *
     * Story 3.7 - Transaction State Machine
     */
    public function getCannotResumeReason(Transaction $transaction, User $user): ?string
    {
        if (! $user->hasRole('Administrator')) {
            return 'Only administrators can resume transactions.';
        }

        if ($transaction->status !== 'On Hold') {
            return 'Transaction must be "On Hold" to resume.';
        }

        return null;
    }

    /**
     * Verify that a user can resume a transaction.
     *
     * @throws \InvalidArgumentException
     */
    protected function verifyCanResume(Transaction $transaction, User $user): void
    {
        $reason = $this->getCannotResumeReason($transaction, $user);
        if ($reason !== null) {
            throw new \InvalidArgumentException($reason);
        }
    }
}
