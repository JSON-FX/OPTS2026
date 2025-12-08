<?php

declare(strict_types=1);

namespace Tests\Feature\Models;

use App\Models\ActionTaken;
use App\Models\Office;
use App\Models\Transaction;
use App\Models\TransactionAction;
use App\Models\User;
use App\Models\WorkflowStep;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature tests for TransactionAction model.
 *
 * Story 3.3 - Transaction Actions Schema & Models
 */
class TransactionActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_transaction_action_can_be_created(): void
    {
        $action = TransactionAction::factory()->endorse()->create();

        $this->assertDatabaseHas('transaction_actions', [
            'id' => $action->id,
            'action_type' => TransactionAction::TYPE_ENDORSE,
        ]);
    }

    public function test_transaction_action_belongs_to_transaction(): void
    {
        $transaction = Transaction::factory()->create();
        $action = TransactionAction::factory()
            ->forTransaction($transaction)
            ->create();

        $this->assertInstanceOf(Transaction::class, $action->transaction);
        $this->assertEquals($transaction->id, $action->transaction->id);
    }

    public function test_transaction_action_belongs_to_from_office(): void
    {
        $office = Office::factory()->create();
        $action = TransactionAction::factory()->create([
            'from_office_id' => $office->id,
        ]);

        $this->assertInstanceOf(Office::class, $action->fromOffice);
        $this->assertEquals($office->id, $action->fromOffice->id);
    }

    public function test_transaction_action_belongs_to_to_office(): void
    {
        $office = Office::factory()->create();
        $action = TransactionAction::factory()->endorse()->create([
            'to_office_id' => $office->id,
        ]);

        $this->assertInstanceOf(Office::class, $action->toOffice);
        $this->assertEquals($office->id, $action->toOffice->id);
    }

    public function test_transaction_action_belongs_to_from_user(): void
    {
        $user = User::factory()->create();
        $action = TransactionAction::factory()->create([
            'from_user_id' => $user->id,
        ]);

        $this->assertInstanceOf(User::class, $action->fromUser);
        $this->assertEquals($user->id, $action->fromUser->id);
    }

    public function test_transaction_action_belongs_to_to_user(): void
    {
        $user = User::factory()->create();
        $action = TransactionAction::factory()->receive()->create([
            'to_user_id' => $user->id,
        ]);

        $this->assertInstanceOf(User::class, $action->toUser);
        $this->assertEquals($user->id, $action->toUser->id);
    }

    public function test_transaction_action_belongs_to_workflow_step(): void
    {
        $workflowStep = WorkflowStep::factory()->create();
        $action = TransactionAction::factory()->create([
            'workflow_step_id' => $workflowStep->id,
        ]);

        $this->assertInstanceOf(WorkflowStep::class, $action->workflowStep);
        $this->assertEquals($workflowStep->id, $action->workflowStep->id);
    }

    public function test_transaction_action_belongs_to_action_taken(): void
    {
        $actionTaken = ActionTaken::factory()->create();
        $action = TransactionAction::factory()->create([
            'action_taken_id' => $actionTaken->id,
        ]);

        $this->assertInstanceOf(ActionTaken::class, $action->actionTaken);
        $this->assertEquals($actionTaken->id, $action->actionTaken->id);
    }

    public function test_scope_of_type_filters_by_action_type(): void
    {
        $transaction = Transaction::factory()->create();
        TransactionAction::factory()->forTransaction($transaction)->endorse()->count(3)->create();
        TransactionAction::factory()->forTransaction($transaction)->receive()->count(2)->create();
        TransactionAction::factory()->forTransaction($transaction)->complete()->create();

        $endorseActions = TransactionAction::ofType(TransactionAction::TYPE_ENDORSE)->get();
        $receiveActions = TransactionAction::ofType(TransactionAction::TYPE_RECEIVE)->get();
        $completeActions = TransactionAction::ofType(TransactionAction::TYPE_COMPLETE)->get();

        $this->assertCount(3, $endorseActions);
        $this->assertCount(2, $receiveActions);
        $this->assertCount(1, $completeActions);
    }

    public function test_scope_out_of_workflow_filters_correctly(): void
    {
        TransactionAction::factory()->count(3)->create(['is_out_of_workflow' => false]);
        TransactionAction::factory()->outOfWorkflow()->count(2)->create();

        $outOfWorkflowActions = TransactionAction::outOfWorkflow()->get();

        $this->assertCount(2, $outOfWorkflowActions);
        $this->assertTrue($outOfWorkflowActions->every(fn ($a) => $a->is_out_of_workflow));
    }

    public function test_scope_for_transaction_filters_by_transaction_id(): void
    {
        $transaction1 = Transaction::factory()->create();
        $transaction2 = Transaction::factory()->create();
        TransactionAction::factory()->forTransaction($transaction1)->count(3)->create();
        TransactionAction::factory()->forTransaction($transaction2)->count(2)->create();

        $transaction1Actions = TransactionAction::forTransaction($transaction1->id)->get();
        $transaction2Actions = TransactionAction::forTransaction($transaction2->id)->get();

        $this->assertCount(3, $transaction1Actions);
        $this->assertCount(2, $transaction2Actions);
    }

    public function test_scope_by_office_filters_from_or_to_office(): void
    {
        $office = Office::factory()->create();
        $otherOffice = Office::factory()->create();

        // Actions where office is the sender
        TransactionAction::factory()->count(2)->create([
            'from_office_id' => $office->id,
            'to_office_id' => $otherOffice->id,
        ]);

        // Actions where office is the receiver
        TransactionAction::factory()->count(3)->create([
            'from_office_id' => $otherOffice->id,
            'to_office_id' => $office->id,
        ]);

        // Unrelated actions
        TransactionAction::factory()->count(2)->create([
            'from_office_id' => $otherOffice->id,
            'to_office_id' => $otherOffice->id,
        ]);

        $officeActions = TransactionAction::byOffice($office->id)->get();

        $this->assertCount(5, $officeActions);
    }

    public function test_is_out_of_workflow_is_cast_to_boolean(): void
    {
        $action = TransactionAction::factory()->create(['is_out_of_workflow' => true]);

        $this->assertTrue($action->is_out_of_workflow);
        $this->assertIsBool($action->is_out_of_workflow);
    }

    public function test_created_at_is_cast_to_datetime(): void
    {
        $action = TransactionAction::factory()->create();

        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $action->created_at);
    }

    public function test_action_type_constants_are_defined(): void
    {
        $this->assertEquals('endorse', TransactionAction::TYPE_ENDORSE);
        $this->assertEquals('receive', TransactionAction::TYPE_RECEIVE);
        $this->assertEquals('complete', TransactionAction::TYPE_COMPLETE);
        $this->assertEquals('hold', TransactionAction::TYPE_HOLD);
        $this->assertEquals('cancel', TransactionAction::TYPE_CANCEL);
        $this->assertEquals('bypass', TransactionAction::TYPE_BYPASS);
    }

    public function test_types_constant_contains_all_action_types(): void
    {
        $expectedTypes = ['endorse', 'receive', 'complete', 'hold', 'cancel', 'bypass'];

        $this->assertEquals($expectedTypes, TransactionAction::TYPES);
    }

    public function test_factory_endorse_state_creates_endorse_action(): void
    {
        $action = TransactionAction::factory()->endorse()->create();

        $this->assertEquals(TransactionAction::TYPE_ENDORSE, $action->action_type);
        $this->assertNotNull($action->to_office_id);
    }

    public function test_factory_receive_state_creates_receive_action(): void
    {
        $action = TransactionAction::factory()->receive()->create();

        $this->assertEquals(TransactionAction::TYPE_RECEIVE, $action->action_type);
        $this->assertNotNull($action->to_user_id);
    }

    public function test_factory_complete_state_creates_complete_action(): void
    {
        $action = TransactionAction::factory()->complete()->create();

        $this->assertEquals(TransactionAction::TYPE_COMPLETE, $action->action_type);
        $this->assertNull($action->to_office_id);
    }

    public function test_factory_hold_state_creates_hold_action_with_reason(): void
    {
        $action = TransactionAction::factory()->hold()->create();

        $this->assertEquals(TransactionAction::TYPE_HOLD, $action->action_type);
        $this->assertNotNull($action->reason);
        $this->assertNull($action->to_office_id);
    }

    public function test_factory_cancel_state_creates_cancel_action_with_reason(): void
    {
        $action = TransactionAction::factory()->cancel()->create();

        $this->assertEquals(TransactionAction::TYPE_CANCEL, $action->action_type);
        $this->assertNotNull($action->reason);
    }

    public function test_factory_bypass_state_creates_out_of_workflow_bypass(): void
    {
        $action = TransactionAction::factory()->bypass()->create();

        $this->assertEquals(TransactionAction::TYPE_BYPASS, $action->action_type);
        $this->assertTrue($action->is_out_of_workflow);
        $this->assertNotNull($action->reason);
    }
}
