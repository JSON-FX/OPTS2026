<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Exceptions\InvalidStateTransitionException;
use App\Models\Office;
use App\Models\Procurement;
use App\Models\Transaction;
use App\Models\TransactionStatusHistory;
use App\Models\User;
use App\Models\Workflow;
use App\Services\TransactionStateMachine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Unit tests for TransactionStateMachine.
 *
 * Story 3.7 - Transaction State Machine
 */
class TransactionStateMachineTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RoleSeeder::class);
    }

    protected function createTransaction(string $status = 'Created'): Transaction
    {
        $procurement = Procurement::factory()->create();

        return Transaction::factory()->create([
            'procurement_id' => $procurement->id,
            'category' => 'PR',
            'status' => $status,
        ]);
    }

    // --- canTransitionTo tests ---

    public function test_created_can_transition_to_in_progress(): void
    {
        $transaction = $this->createTransaction('Created');
        $sm = new TransactionStateMachine($transaction);

        $this->assertTrue($sm->canTransitionTo('In Progress'));
    }

    public function test_created_cannot_transition_to_completed(): void
    {
        $transaction = $this->createTransaction('Created');
        $sm = new TransactionStateMachine($transaction);

        $this->assertFalse($sm->canTransitionTo('Completed'));
    }

    public function test_created_cannot_transition_to_on_hold(): void
    {
        $transaction = $this->createTransaction('Created');
        $sm = new TransactionStateMachine($transaction);

        $this->assertFalse($sm->canTransitionTo('On Hold'));
    }

    public function test_created_cannot_transition_to_cancelled(): void
    {
        $transaction = $this->createTransaction('Created');
        $sm = new TransactionStateMachine($transaction);

        $this->assertFalse($sm->canTransitionTo('Cancelled'));
    }

    public function test_in_progress_can_transition_to_completed(): void
    {
        $transaction = $this->createTransaction('In Progress');
        $sm = new TransactionStateMachine($transaction);

        $this->assertTrue($sm->canTransitionTo('Completed'));
    }

    public function test_in_progress_can_transition_to_on_hold(): void
    {
        $transaction = $this->createTransaction('In Progress');
        $sm = new TransactionStateMachine($transaction);

        $this->assertTrue($sm->canTransitionTo('On Hold'));
    }

    public function test_in_progress_can_transition_to_cancelled(): void
    {
        $transaction = $this->createTransaction('In Progress');
        $sm = new TransactionStateMachine($transaction);

        $this->assertTrue($sm->canTransitionTo('Cancelled'));
    }

    public function test_in_progress_cannot_transition_to_created(): void
    {
        $transaction = $this->createTransaction('In Progress');
        $sm = new TransactionStateMachine($transaction);

        $this->assertFalse($sm->canTransitionTo('Created'));
    }

    public function test_on_hold_can_transition_to_in_progress(): void
    {
        $transaction = $this->createTransaction('On Hold');
        $sm = new TransactionStateMachine($transaction);

        $this->assertTrue($sm->canTransitionTo('In Progress'));
    }

    public function test_on_hold_can_transition_to_cancelled(): void
    {
        $transaction = $this->createTransaction('On Hold');
        $sm = new TransactionStateMachine($transaction);

        $this->assertTrue($sm->canTransitionTo('Cancelled'));
    }

    public function test_on_hold_cannot_transition_to_completed(): void
    {
        $transaction = $this->createTransaction('On Hold');
        $sm = new TransactionStateMachine($transaction);

        $this->assertFalse($sm->canTransitionTo('Completed'));
    }

    public function test_completed_cannot_transition_to_any_state(): void
    {
        $transaction = $this->createTransaction('Completed');
        $sm = new TransactionStateMachine($transaction);

        $this->assertFalse($sm->canTransitionTo('Created'));
        $this->assertFalse($sm->canTransitionTo('In Progress'));
        $this->assertFalse($sm->canTransitionTo('On Hold'));
        $this->assertFalse($sm->canTransitionTo('Cancelled'));
    }

    public function test_cancelled_cannot_transition_to_any_state(): void
    {
        $transaction = $this->createTransaction('Cancelled');
        $sm = new TransactionStateMachine($transaction);

        $this->assertFalse($sm->canTransitionTo('Created'));
        $this->assertFalse($sm->canTransitionTo('In Progress'));
        $this->assertFalse($sm->canTransitionTo('On Hold'));
        $this->assertFalse($sm->canTransitionTo('Completed'));
    }

    // --- transitionTo tests ---

    public function test_transition_to_updates_status(): void
    {
        $transaction = $this->createTransaction('Created');
        $user = User::factory()->create();
        $user->assignRole('Administrator');
        $sm = new TransactionStateMachine($transaction);

        $sm->transitionTo('In Progress', 'First endorsement', $user);

        $transaction->refresh();
        $this->assertEquals('In Progress', $transaction->status);
    }

    public function test_transition_to_creates_status_history(): void
    {
        $transaction = $this->createTransaction('In Progress');
        $user = User::factory()->create();
        $user->assignRole('Administrator');
        $sm = new TransactionStateMachine($transaction);

        $sm->transitionTo('On Hold', 'Budget review needed', $user);

        $this->assertDatabaseHas('transaction_status_history', [
            'transaction_id' => $transaction->id,
            'old_status' => 'In Progress',
            'new_status' => 'On Hold',
            'reason' => 'Budget review needed',
            'changed_by_user_id' => $user->id,
        ]);
    }

    public function test_transition_to_throws_on_invalid_transition(): void
    {
        $transaction = $this->createTransaction('Completed');
        $user = User::factory()->create();
        $sm = new TransactionStateMachine($transaction);

        $this->expectException(InvalidStateTransitionException::class);
        $this->expectExceptionMessage('Cannot transition from "Completed" to "In Progress"');

        $sm->transitionTo('In Progress', null, $user);
    }

    public function test_transition_to_with_null_reason(): void
    {
        $transaction = $this->createTransaction('In Progress');
        $user = User::factory()->create();
        $user->assignRole('Administrator');
        $sm = new TransactionStateMachine($transaction);

        $sm->transitionTo('Completed', null, $user);

        $this->assertDatabaseHas('transaction_status_history', [
            'transaction_id' => $transaction->id,
            'old_status' => 'In Progress',
            'new_status' => 'Completed',
            'reason' => null,
        ]);
    }

    // --- getAvailableTransitions tests ---

    public function test_get_available_transitions_for_created(): void
    {
        $transaction = $this->createTransaction('Created');
        $sm = new TransactionStateMachine($transaction);

        $this->assertEquals(['In Progress'], $sm->getAvailableTransitions());
    }

    public function test_get_available_transitions_for_in_progress(): void
    {
        $transaction = $this->createTransaction('In Progress');
        $sm = new TransactionStateMachine($transaction);

        $this->assertEquals(['Completed', 'On Hold', 'Cancelled'], $sm->getAvailableTransitions());
    }

    public function test_get_available_transitions_for_on_hold(): void
    {
        $transaction = $this->createTransaction('On Hold');
        $sm = new TransactionStateMachine($transaction);

        $this->assertEquals(['In Progress', 'Cancelled'], $sm->getAvailableTransitions());
    }

    public function test_get_available_transitions_for_completed(): void
    {
        $transaction = $this->createTransaction('Completed');
        $sm = new TransactionStateMachine($transaction);

        $this->assertEquals([], $sm->getAvailableTransitions());
    }

    public function test_get_available_transitions_for_cancelled(): void
    {
        $transaction = $this->createTransaction('Cancelled');
        $sm = new TransactionStateMachine($transaction);

        $this->assertEquals([], $sm->getAvailableTransitions());
    }

    // --- isTerminal tests ---

    public function test_completed_is_terminal(): void
    {
        $transaction = $this->createTransaction('Completed');
        $sm = new TransactionStateMachine($transaction);

        $this->assertTrue($sm->isTerminal());
    }

    public function test_cancelled_is_terminal(): void
    {
        $transaction = $this->createTransaction('Cancelled');
        $sm = new TransactionStateMachine($transaction);

        $this->assertTrue($sm->isTerminal());
    }

    public function test_created_is_not_terminal(): void
    {
        $transaction = $this->createTransaction('Created');
        $sm = new TransactionStateMachine($transaction);

        $this->assertFalse($sm->isTerminal());
    }

    public function test_in_progress_is_not_terminal(): void
    {
        $transaction = $this->createTransaction('In Progress');
        $sm = new TransactionStateMachine($transaction);

        $this->assertFalse($sm->isTerminal());
    }

    public function test_on_hold_is_not_terminal(): void
    {
        $transaction = $this->createTransaction('On Hold');
        $sm = new TransactionStateMachine($transaction);

        $this->assertFalse($sm->isTerminal());
    }
}
