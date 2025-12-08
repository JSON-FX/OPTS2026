<?php

declare(strict_types=1);

namespace Tests\Feature\Models;

use App\Models\Office;
use App\Models\Transaction;
use App\Models\TransactionAction;
use App\Models\User;
use App\Models\WorkflowStep;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Integration tests for Transaction-Action relationships.
 *
 * Story 3.3 - Transaction Actions Schema & Models
 */
class TransactionActionsIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_transaction_has_many_actions(): void
    {
        $transaction = Transaction::factory()->create();
        TransactionAction::factory()->forTransaction($transaction)->count(5)->create();

        $this->assertCount(5, $transaction->actions);
        $this->assertInstanceOf(TransactionAction::class, $transaction->actions->first());
    }

    public function test_actions_history_accessor_returns_actions_ordered_by_created_at_desc(): void
    {
        $transaction = Transaction::factory()->create();

        // Create actions with specific timestamps
        $action1 = TransactionAction::factory()->forTransaction($transaction)->create([
            'created_at' => now()->subHours(3),
        ]);
        $action2 = TransactionAction::factory()->forTransaction($transaction)->create([
            'created_at' => now()->subHours(1),
        ]);
        $action3 = TransactionAction::factory()->forTransaction($transaction)->create([
            'created_at' => now()->subHours(2),
        ]);

        $history = $transaction->actionsHistory;

        $this->assertCount(3, $history);
        // Most recent first
        $this->assertEquals($action2->id, $history[0]->id);
        $this->assertEquals($action3->id, $history[1]->id);
        $this->assertEquals($action1->id, $history[2]->id);
    }

    public function test_last_action_accessor_returns_most_recent_action(): void
    {
        $transaction = Transaction::factory()->create();

        TransactionAction::factory()->forTransaction($transaction)->create([
            'created_at' => now()->subHours(2),
        ]);
        $latestAction = TransactionAction::factory()->forTransaction($transaction)->create([
            'created_at' => now()->subMinutes(30),
        ]);
        TransactionAction::factory()->forTransaction($transaction)->create([
            'created_at' => now()->subHour(),
        ]);

        $lastAction = $transaction->lastAction;

        $this->assertInstanceOf(TransactionAction::class, $lastAction);
        $this->assertEquals($latestAction->id, $lastAction->id);
    }

    public function test_last_action_returns_null_when_no_actions(): void
    {
        $transaction = Transaction::factory()->create();

        $this->assertNull($transaction->lastAction);
    }

    public function test_current_holder_accessor_returns_office_and_user(): void
    {
        $office = Office::factory()->create();
        $user = User::factory()->create(['office_id' => $office->id]);
        $transaction = Transaction::factory()->create([
            'current_office_id' => $office->id,
            'current_user_id' => $user->id,
        ]);

        $holder = $transaction->currentHolder;

        $this->assertIsArray($holder);
        $this->assertArrayHasKey('office', $holder);
        $this->assertArrayHasKey('user', $holder);
        $this->assertEquals($office->id, $holder['office']->id);
        $this->assertEquals($user->id, $holder['user']->id);
    }

    public function test_current_holder_returns_null_when_no_holder(): void
    {
        $transaction = Transaction::factory()->create([
            'current_office_id' => null,
            'current_user_id' => null,
        ]);

        $this->assertNull($transaction->currentHolder);
    }

    public function test_current_holder_handles_partial_data(): void
    {
        $office = Office::factory()->create();
        $transaction = Transaction::factory()->create([
            'current_office_id' => $office->id,
            'current_user_id' => null,
        ]);

        $holder = $transaction->currentHolder;

        $this->assertIsArray($holder);
        $this->assertEquals($office->id, $holder['office']->id);
        $this->assertNull($holder['user']);
    }

    public function test_transaction_belongs_to_current_step(): void
    {
        $workflowStep = WorkflowStep::factory()->create();
        $transaction = Transaction::factory()->create([
            'current_step_id' => $workflowStep->id,
        ]);

        $this->assertInstanceOf(WorkflowStep::class, $transaction->currentStep);
        $this->assertEquals($workflowStep->id, $transaction->currentStep->id);
    }

    public function test_is_at_step_returns_true_for_matching_step_order(): void
    {
        $workflowStep = WorkflowStep::factory()->create(['step_order' => 2]);
        $transaction = Transaction::factory()->create([
            'current_step_id' => $workflowStep->id,
        ]);

        $this->assertTrue($transaction->isAtStep(2));
        $this->assertFalse($transaction->isAtStep(1));
        $this->assertFalse($transaction->isAtStep(3));
    }

    public function test_is_at_step_returns_false_when_no_current_step(): void
    {
        $transaction = Transaction::factory()->create([
            'current_step_id' => null,
        ]);

        $this->assertFalse($transaction->isAtStep(1));
    }

    public function test_has_been_received_by_current_office_returns_true_when_received_at_set(): void
    {
        $transaction = Transaction::factory()->create([
            'received_at' => now(),
        ]);

        $this->assertTrue($transaction->hasBeenReceivedByCurrentOffice());
    }

    public function test_has_been_received_by_current_office_returns_false_when_received_at_null(): void
    {
        $transaction = Transaction::factory()->create([
            'received_at' => null,
        ]);

        $this->assertFalse($transaction->hasBeenReceivedByCurrentOffice());
    }

    public function test_received_at_and_endorsed_at_are_cast_to_datetime(): void
    {
        $transaction = Transaction::factory()->create([
            'received_at' => now(),
            'endorsed_at' => now()->subHour(),
        ]);

        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $transaction->received_at);
        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $transaction->endorsed_at);
    }

    public function test_new_fields_are_fillable(): void
    {
        $workflowStep = WorkflowStep::factory()->create();
        $transaction = Transaction::factory()->create();

        $transaction->fill([
            'current_step_id' => $workflowStep->id,
            'received_at' => now(),
            'endorsed_at' => now()->subHour(),
        ]);
        $transaction->save();

        $this->assertEquals($workflowStep->id, $transaction->fresh()->current_step_id);
        $this->assertNotNull($transaction->fresh()->received_at);
        $this->assertNotNull($transaction->fresh()->endorsed_at);
    }

    public function test_complete_endorsement_chain_can_be_queried(): void
    {
        $transaction = Transaction::factory()->create();
        $office1 = Office::factory()->create();
        $office2 = Office::factory()->create();
        $user1 = User::factory()->create(['office_id' => $office1->id]);
        $user2 = User::factory()->create(['office_id' => $office2->id]);

        // Endorse from office1 to office2
        TransactionAction::factory()->forTransaction($transaction)->endorse()->create([
            'from_office_id' => $office1->id,
            'to_office_id' => $office2->id,
            'from_user_id' => $user1->id,
            'created_at' => now()->subHours(3),
        ]);

        // Receive at office2
        TransactionAction::factory()->forTransaction($transaction)->receive()->create([
            'from_office_id' => $office1->id,
            'to_office_id' => $office2->id,
            'from_user_id' => $user2->id,
            'to_user_id' => $user2->id,
            'created_at' => now()->subHours(2),
        ]);

        // Complete at office2
        TransactionAction::factory()->forTransaction($transaction)->complete()->create([
            'from_office_id' => $office2->id,
            'from_user_id' => $user2->id,
            'created_at' => now()->subHour(),
        ]);

        // Verify chain
        $actions = $transaction->actionsHistory;
        $this->assertCount(3, $actions);
        $this->assertEquals(TransactionAction::TYPE_COMPLETE, $actions[0]->action_type);
        $this->assertEquals(TransactionAction::TYPE_RECEIVE, $actions[1]->action_type);
        $this->assertEquals(TransactionAction::TYPE_ENDORSE, $actions[2]->action_type);
    }
}
