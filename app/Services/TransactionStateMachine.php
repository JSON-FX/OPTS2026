<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\InvalidStateTransitionException;
use App\Models\Transaction;
use App\Models\TransactionStatusHistory;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * State machine governing transaction status transitions.
 *
 * Story 3.7 - Transaction State Machine
 *
 * Valid transitions:
 *   Created → In Progress (first endorsement)
 *   In Progress → Completed (complete action at final step)
 *   In Progress → On Hold (admin action with reason)
 *   In Progress → Cancelled (admin action with reason)
 *   On Hold → In Progress (admin resume)
 *   On Hold → Cancelled (admin action with reason)
 */
class TransactionStateMachine
{
    /**
     * @var array<string, list<string>>
     */
    protected array $transitions = [
        'Created' => ['In Progress'],
        'In Progress' => ['Completed', 'On Hold', 'Cancelled'],
        'On Hold' => ['In Progress', 'Cancelled'],
        'Completed' => [],
        'Cancelled' => [],
    ];

    public function __construct(
        protected Transaction $transaction
    ) {}

    /**
     * Check if a transition to the given status is valid.
     */
    public function canTransitionTo(string $newStatus): bool
    {
        $currentStatus = $this->transaction->status;

        return in_array($newStatus, $this->transitions[$currentStatus] ?? [], true);
    }

    /**
     * Perform a status transition with logging.
     *
     * @throws InvalidStateTransitionException
     */
    public function transitionTo(string $newStatus, ?string $reason = null, ?User $user = null): void
    {
        if (! $this->canTransitionTo($newStatus)) {
            throw new InvalidStateTransitionException(
                "Cannot transition from \"{$this->transaction->status}\" to \"{$newStatus}\""
            );
        }

        $oldStatus = $this->transaction->status;
        $user = $user ?? auth()->user();

        DB::transaction(function () use ($newStatus, $oldStatus, $reason, $user) {
            $this->transaction->update(['status' => $newStatus]);

            TransactionStatusHistory::create([
                'transaction_id' => $this->transaction->id,
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
                'reason' => $reason,
                'changed_by_user_id' => $user->id,
            ]);
        });
    }

    /**
     * Get the list of valid next states from the current status.
     *
     * @return list<string>
     */
    public function getAvailableTransitions(): array
    {
        return $this->transitions[$this->transaction->status] ?? [];
    }

    /**
     * Check if the current status is terminal (no further transitions possible).
     */
    public function isTerminal(): bool
    {
        return empty($this->getAvailableTransitions());
    }
}
