<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\ActionTaken;
use App\Models\Office;
use App\Models\Procurement;
use App\Models\Transaction;
use App\Models\TransactionAction;
use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowStep;
use App\Services\EndorsementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Unit tests for EndorsementService.
 *
 * Story 3.4 - Endorse Action Implementation
 */
class EndorsementServiceTest extends TestCase
{
    use RefreshDatabase;

    private EndorsementService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RoleSeeder::class);
        $this->service = app(EndorsementService::class);
    }

    /**
     * Create a standard test setup.
     *
     * @return array{transaction: Transaction, user: User, office1: Office, office2: Office, workflow: Workflow, step1: WorkflowStep}
     */
    protected function createTestSetup(): array
    {
        $office1 = Office::factory()->create(['is_active' => true]);
        $office2 = Office::factory()->create(['is_active' => true]);

        $workflow = Workflow::factory()->create([
            'category' => 'PR',
            'is_active' => true,
        ]);

        $step1 = WorkflowStep::factory()->create([
            'workflow_id' => $workflow->id,
            'office_id' => $office1->id,
            'step_order' => 1,
            'is_final_step' => false,
        ]);

        $step2 = WorkflowStep::factory()->create([
            'workflow_id' => $workflow->id,
            'office_id' => $office2->id,
            'step_order' => 2,
            'is_final_step' => true,
        ]);

        $user = User::factory()->create(['office_id' => $office1->id]);
        $user->assignRole('Endorser');

        $procurement = Procurement::factory()->create();
        $transaction = Transaction::factory()->create([
            'procurement_id' => $procurement->id,
            'category' => 'PR',
            'status' => 'In Progress',
            'workflow_id' => $workflow->id,
            'current_office_id' => $office1->id,
            'current_step_id' => $step1->id,
            'received_at' => now(),
        ]);

        return [
            'transaction' => $transaction,
            'user' => $user,
            'office1' => $office1,
            'office2' => $office2,
            'workflow' => $workflow,
            'step1' => $step1,
        ];
    }

    // canEndorse Tests

    public function test_can_endorse_returns_true_for_valid_conditions(): void
    {
        $setup = $this->createTestSetup();

        $result = $this->service->canEndorse($setup['transaction'], $setup['user']);

        $this->assertTrue($result);
    }

    public function test_can_endorse_returns_false_for_viewer_role(): void
    {
        $setup = $this->createTestSetup();

        $viewer = User::factory()->create(['office_id' => $setup['office1']->id]);
        $viewer->assignRole('Viewer');

        $result = $this->service->canEndorse($setup['transaction'], $viewer);

        $this->assertFalse($result);
    }

    public function test_can_endorse_returns_false_for_wrong_office(): void
    {
        $setup = $this->createTestSetup();

        $otherOffice = Office::factory()->create(['is_active' => true]);
        $otherUser = User::factory()->create(['office_id' => $otherOffice->id]);
        $otherUser->assignRole('Endorser');

        $result = $this->service->canEndorse($setup['transaction'], $otherUser);

        $this->assertFalse($result);
    }

    public function test_can_endorse_returns_false_for_unreceived_transaction(): void
    {
        $setup = $this->createTestSetup();

        $setup['transaction']->update(['received_at' => null]);

        $result = $this->service->canEndorse($setup['transaction'], $setup['user']);

        $this->assertFalse($result);
    }

    public function test_can_endorse_returns_false_for_wrong_status(): void
    {
        $setup = $this->createTestSetup();

        $setup['transaction']->update(['status' => 'On Hold']);

        $result = $this->service->canEndorse($setup['transaction'], $setup['user']);

        $this->assertFalse($result);
    }

    public function test_can_endorse_returns_false_at_final_step(): void
    {
        $office = Office::factory()->create(['is_active' => true]);
        $workflow = Workflow::factory()->create(['category' => 'PR', 'is_active' => true]);

        $finalStep = WorkflowStep::factory()->create([
            'workflow_id' => $workflow->id,
            'office_id' => $office->id,
            'step_order' => 1,
            'is_final_step' => true,
        ]);

        $user = User::factory()->create(['office_id' => $office->id]);
        $user->assignRole('Endorser');

        $procurement = Procurement::factory()->create();
        $transaction = Transaction::factory()->create([
            'procurement_id' => $procurement->id,
            'category' => 'PR',
            'status' => 'In Progress',
            'workflow_id' => $workflow->id,
            'current_office_id' => $office->id,
            'current_step_id' => $finalStep->id,
            'received_at' => now(),
        ]);

        $result = $this->service->canEndorse($transaction, $user);

        $this->assertFalse($result);
    }

    public function test_can_endorse_returns_true_for_administrator(): void
    {
        $setup = $this->createTestSetup();

        $admin = User::factory()->create(['office_id' => $setup['office1']->id]);
        $admin->assignRole('Administrator');

        $result = $this->service->canEndorse($setup['transaction'], $admin);

        $this->assertTrue($result);
    }

    // endorse Tests

    public function test_endorse_creates_action_with_correct_data(): void
    {
        $setup = $this->createTestSetup();
        $actionTaken = ActionTaken::factory()->create(['is_active' => true]);

        $action = $this->service->endorse(
            $setup['transaction'],
            $setup['user'],
            $actionTaken->id,
            $setup['office2']->id,
            'Test notes'
        );

        $this->assertInstanceOf(TransactionAction::class, $action);
        $this->assertEquals($setup['transaction']->id, $action->transaction_id);
        $this->assertEquals('endorse', $action->action_type);
        $this->assertEquals($actionTaken->id, $action->action_taken_id);
        $this->assertEquals($setup['office1']->id, $action->from_office_id);
        $this->assertEquals($setup['office2']->id, $action->to_office_id);
        $this->assertEquals($setup['user']->id, $action->from_user_id);
        $this->assertEquals('Test notes', $action->notes);
    }

    public function test_endorse_updates_transaction_fields(): void
    {
        $setup = $this->createTestSetup();
        $actionTaken = ActionTaken::factory()->create(['is_active' => true]);

        $originalReceivedAt = $setup['transaction']->received_at;

        $this->service->endorse(
            $setup['transaction'],
            $setup['user'],
            $actionTaken->id,
            $setup['office2']->id
        );

        $setup['transaction']->refresh();

        $this->assertEquals($setup['office2']->id, $setup['transaction']->current_office_id);
        $this->assertNull($setup['transaction']->current_user_id);
        $this->assertNotNull($setup['transaction']->endorsed_at);
        $this->assertNull($setup['transaction']->received_at);
    }

    public function test_endorse_sets_out_of_workflow_false_for_expected_next_office(): void
    {
        $setup = $this->createTestSetup();
        $actionTaken = ActionTaken::factory()->create(['is_active' => true]);

        $action = $this->service->endorse(
            $setup['transaction'],
            $setup['user'],
            $actionTaken->id,
            $setup['office2']->id // This is the expected next office
        );

        $this->assertFalse($action->is_out_of_workflow);
    }

    public function test_endorse_sets_out_of_workflow_true_for_unexpected_office(): void
    {
        $setup = $this->createTestSetup();
        $actionTaken = ActionTaken::factory()->create(['is_active' => true]);

        $unexpectedOffice = Office::factory()->create(['is_active' => true]);

        $action = $this->service->endorse(
            $setup['transaction'],
            $setup['user'],
            $actionTaken->id,
            $unexpectedOffice->id
        );

        $this->assertTrue($action->is_out_of_workflow);
    }

    public function test_endorse_stores_workflow_step_id(): void
    {
        $setup = $this->createTestSetup();
        $actionTaken = ActionTaken::factory()->create(['is_active' => true]);

        $action = $this->service->endorse(
            $setup['transaction'],
            $setup['user'],
            $actionTaken->id,
            $setup['office2']->id
        );

        $this->assertEquals($setup['step1']->id, $action->workflow_step_id);
    }

    // getExpectedNextOffice Tests

    public function test_get_expected_next_office_returns_correct_office(): void
    {
        $setup = $this->createTestSetup();

        $expectedOffice = $this->service->getExpectedNextOffice($setup['transaction']);

        $this->assertNotNull($expectedOffice);
        $this->assertEquals($setup['office2']->id, $expectedOffice->id);
    }

    public function test_get_expected_next_office_returns_null_at_final_step(): void
    {
        $office = Office::factory()->create(['is_active' => true]);
        $workflow = Workflow::factory()->create(['category' => 'PR', 'is_active' => true]);

        $finalStep = WorkflowStep::factory()->create([
            'workflow_id' => $workflow->id,
            'office_id' => $office->id,
            'step_order' => 1,
            'is_final_step' => true,
        ]);

        $procurement = Procurement::factory()->create();
        $transaction = Transaction::factory()->create([
            'procurement_id' => $procurement->id,
            'category' => 'PR',
            'status' => 'In Progress',
            'workflow_id' => $workflow->id,
            'current_office_id' => $office->id,
            'current_step_id' => $finalStep->id,
            'received_at' => now(),
        ]);

        $expectedOffice = $this->service->getExpectedNextOffice($transaction);

        $this->assertNull($expectedOffice);
    }

    // getCannotEndorseReason Tests

    public function test_get_cannot_endorse_reason_returns_null_when_can_endorse(): void
    {
        $setup = $this->createTestSetup();

        $reason = $this->service->getCannotEndorseReason($setup['transaction'], $setup['user']);

        $this->assertNull($reason);
    }

    public function test_get_cannot_endorse_reason_returns_role_message(): void
    {
        $setup = $this->createTestSetup();

        $viewer = User::factory()->create(['office_id' => $setup['office1']->id]);
        $viewer->assignRole('Viewer');

        $reason = $this->service->getCannotEndorseReason($setup['transaction'], $viewer);

        $this->assertStringContainsString('permission', $reason);
    }

    public function test_get_cannot_endorse_reason_returns_office_message(): void
    {
        $setup = $this->createTestSetup();

        $otherOffice = Office::factory()->create(['is_active' => true]);
        $otherUser = User::factory()->create(['office_id' => $otherOffice->id]);
        $otherUser->assignRole('Endorser');

        $reason = $this->service->getCannotEndorseReason($setup['transaction'], $otherUser);

        $this->assertStringContainsString('office', $reason);
    }

    public function test_get_cannot_endorse_reason_returns_received_message(): void
    {
        $setup = $this->createTestSetup();

        $setup['transaction']->update(['received_at' => null]);

        $reason = $this->service->getCannotEndorseReason($setup['transaction'], $setup['user']);

        $this->assertStringContainsString('received', $reason);
    }

    public function test_get_cannot_endorse_reason_returns_status_message(): void
    {
        $setup = $this->createTestSetup();

        $setup['transaction']->update(['status' => 'On Hold']);

        $reason = $this->service->getCannotEndorseReason($setup['transaction'], $setup['user']);

        $this->assertStringContainsString('In Progress', $reason);
    }

    // Story 3.5 - canReceive Tests

    public function test_can_receive_returns_true_for_valid_conditions(): void
    {
        $setup = $this->createTestSetup();

        // Set up transaction as endorsed to office2 (not yet received)
        $setup['transaction']->update([
            'current_office_id' => $setup['office2']->id,
            'received_at' => null,
            'endorsed_at' => now(),
        ]);

        $receiver = User::factory()->create(['office_id' => $setup['office2']->id]);
        $receiver->assignRole('Endorser');

        $result = $this->service->canReceive($setup['transaction'], $receiver);

        $this->assertTrue($result);
    }

    public function test_can_receive_returns_false_for_viewer_role(): void
    {
        $setup = $this->createTestSetup();

        $setup['transaction']->update([
            'current_office_id' => $setup['office2']->id,
            'received_at' => null,
        ]);

        $viewer = User::factory()->create(['office_id' => $setup['office2']->id]);
        $viewer->assignRole('Viewer');

        $result = $this->service->canReceive($setup['transaction'], $viewer);

        $this->assertFalse($result);
    }

    public function test_can_receive_returns_false_for_wrong_office(): void
    {
        $setup = $this->createTestSetup();

        $setup['transaction']->update([
            'current_office_id' => $setup['office2']->id,
            'received_at' => null,
        ]);

        // User at office1, transaction at office2
        $result = $this->service->canReceive($setup['transaction'], $setup['user']);

        $this->assertFalse($result);
    }

    public function test_can_receive_returns_false_for_already_received(): void
    {
        $setup = $this->createTestSetup();

        // Transaction is already received (received_at is set from createTestSetup)
        $result = $this->service->canReceive($setup['transaction'], $setup['user']);

        $this->assertFalse($result);
    }

    public function test_can_receive_returns_false_for_wrong_status(): void
    {
        $setup = $this->createTestSetup();

        $setup['transaction']->update([
            'current_office_id' => $setup['office2']->id,
            'received_at' => null,
            'status' => 'Completed',
        ]);

        $receiver = User::factory()->create(['office_id' => $setup['office2']->id]);
        $receiver->assignRole('Endorser');

        $result = $this->service->canReceive($setup['transaction'], $receiver);

        $this->assertFalse($result);
    }

    public function test_can_receive_returns_true_for_administrator(): void
    {
        $setup = $this->createTestSetup();

        $setup['transaction']->update([
            'current_office_id' => $setup['office2']->id,
            'received_at' => null,
        ]);

        $admin = User::factory()->create(['office_id' => $setup['office2']->id]);
        $admin->assignRole('Administrator');

        $result = $this->service->canReceive($setup['transaction'], $admin);

        $this->assertTrue($result);
    }

    // Story 3.5 - receive Tests

    public function test_receive_creates_action_with_from_user_from_endorsement(): void
    {
        $setup = $this->createTestSetup();

        // Set up endorsement action
        $setup['transaction']->update([
            'current_office_id' => $setup['office2']->id,
            'received_at' => null,
            'endorsed_at' => now(),
        ]);

        TransactionAction::create([
            'transaction_id' => $setup['transaction']->id,
            'action_type' => TransactionAction::TYPE_ENDORSE,
            'from_office_id' => $setup['office1']->id,
            'to_office_id' => $setup['office2']->id,
            'from_user_id' => $setup['user']->id,
            'workflow_step_id' => $setup['step1']->id,
            'is_out_of_workflow' => false,
            'ip_address' => '127.0.0.1',
        ]);

        $receiver = User::factory()->create(['office_id' => $setup['office2']->id]);
        $receiver->assignRole('Endorser');

        $action = $this->service->receive($setup['transaction'], $receiver, 'Test receive');

        $this->assertInstanceOf(TransactionAction::class, $action);
        $this->assertEquals(TransactionAction::TYPE_RECEIVE, $action->action_type);
        $this->assertEquals($setup['user']->id, $action->from_user_id);
        $this->assertEquals($receiver->id, $action->to_user_id);
        $this->assertEquals($setup['office1']->id, $action->from_office_id);
        $this->assertEquals($setup['office2']->id, $action->to_office_id);
        $this->assertEquals('Test receive', $action->notes);
    }

    public function test_advance_workflow_step_logic(): void
    {
        $setup = $this->createTestSetup();

        // Set up endorsement action (in-workflow)
        $setup['transaction']->update([
            'current_office_id' => $setup['office2']->id,
            'received_at' => null,
            'endorsed_at' => now(),
        ]);

        TransactionAction::create([
            'transaction_id' => $setup['transaction']->id,
            'action_type' => TransactionAction::TYPE_ENDORSE,
            'from_office_id' => $setup['office1']->id,
            'to_office_id' => $setup['office2']->id,
            'from_user_id' => $setup['user']->id,
            'workflow_step_id' => $setup['step1']->id,
            'is_out_of_workflow' => false,
            'ip_address' => '127.0.0.1',
        ]);

        $receiver = User::factory()->create(['office_id' => $setup['office2']->id]);
        $receiver->assignRole('Endorser');

        $this->service->receive($setup['transaction'], $receiver);

        $setup['transaction']->refresh();

        // Step 1 -> Step 2 (office2)
        $step2 = WorkflowStep::where('workflow_id', $setup['workflow']->id)
            ->where('step_order', 2)
            ->first();

        $this->assertEquals($step2->id, $setup['transaction']->current_step_id);
    }

    public function test_bulk_receive_returns_success_failed_arrays(): void
    {
        $setup = $this->createTestSetup();

        // Create a receivable transaction
        $setup['transaction']->update([
            'current_office_id' => $setup['office2']->id,
            'received_at' => null,
            'endorsed_at' => now(),
        ]);

        TransactionAction::create([
            'transaction_id' => $setup['transaction']->id,
            'action_type' => TransactionAction::TYPE_ENDORSE,
            'from_office_id' => $setup['office1']->id,
            'to_office_id' => $setup['office2']->id,
            'from_user_id' => $setup['user']->id,
            'workflow_step_id' => $setup['step1']->id,
            'is_out_of_workflow' => false,
            'ip_address' => '127.0.0.1',
        ]);

        $receiver = User::factory()->create(['office_id' => $setup['office2']->id]);
        $receiver->assignRole('Endorser');

        $results = $this->service->receiveBulk(
            [$setup['transaction']->id, 99999],
            $receiver
        );

        $this->assertCount(1, $results['success']);
        $this->assertCount(1, $results['failed']);
        $this->assertContains($setup['transaction']->id, $results['success']);
        $this->assertArrayHasKey(99999, $results['failed']);
    }

    // Story 3.5 - getCannotReceiveReason Tests

    public function test_get_cannot_receive_reason_returns_null_when_can_receive(): void
    {
        $setup = $this->createTestSetup();

        $setup['transaction']->update([
            'current_office_id' => $setup['office2']->id,
            'received_at' => null,
        ]);

        $receiver = User::factory()->create(['office_id' => $setup['office2']->id]);
        $receiver->assignRole('Endorser');

        $reason = $this->service->getCannotReceiveReason($setup['transaction'], $receiver);

        $this->assertNull($reason);
    }

    public function test_get_cannot_receive_reason_returns_role_message(): void
    {
        $setup = $this->createTestSetup();

        $viewer = User::factory()->create(['office_id' => $setup['office1']->id]);
        $viewer->assignRole('Viewer');

        $reason = $this->service->getCannotReceiveReason($setup['transaction'], $viewer);

        $this->assertStringContainsString('permission', $reason);
    }

    public function test_get_cannot_receive_reason_returns_office_message(): void
    {
        $setup = $this->createTestSetup();

        $setup['transaction']->update([
            'current_office_id' => $setup['office2']->id,
            'received_at' => null,
        ]);

        $reason = $this->service->getCannotReceiveReason($setup['transaction'], $setup['user']);

        $this->assertStringContainsString('office', $reason);
    }

    public function test_get_cannot_receive_reason_returns_already_received_message(): void
    {
        $setup = $this->createTestSetup();

        // Transaction has received_at set (from createTestSetup)
        $reason = $this->service->getCannotReceiveReason($setup['transaction'], $setup['user']);

        $this->assertStringContainsString('already been received', $reason);
    }

    // Story 3.6 - canComplete Tests

    public function test_can_complete_returns_true_at_final_step(): void
    {
        $setup = $this->createTestSetup();

        // Move transaction to final step (office2)
        $step2 = WorkflowStep::where('workflow_id', $setup['workflow']->id)
            ->where('step_order', 2)
            ->first();

        $setup['transaction']->update([
            'current_office_id' => $setup['office2']->id,
            'current_step_id' => $step2->id,
        ]);

        $user = User::factory()->create(['office_id' => $setup['office2']->id]);
        $user->assignRole('Endorser');

        $result = $this->service->canComplete($setup['transaction'], $user);

        $this->assertTrue($result);
    }

    public function test_can_complete_returns_false_at_non_final_step(): void
    {
        $setup = $this->createTestSetup();

        // Transaction is at step 1 (non-final) from createTestSetup
        $result = $this->service->canComplete($setup['transaction'], $setup['user']);

        $this->assertFalse($result);
    }

    public function test_can_complete_returns_false_for_viewer(): void
    {
        $setup = $this->createTestSetup();

        $step2 = WorkflowStep::where('workflow_id', $setup['workflow']->id)
            ->where('step_order', 2)
            ->first();

        $setup['transaction']->update([
            'current_office_id' => $setup['office2']->id,
            'current_step_id' => $step2->id,
        ]);

        $viewer = User::factory()->create(['office_id' => $setup['office2']->id]);
        $viewer->assignRole('Viewer');

        $result = $this->service->canComplete($setup['transaction'], $viewer);

        $this->assertFalse($result);
    }

    public function test_can_complete_returns_false_for_unreceived(): void
    {
        $setup = $this->createTestSetup();

        $step2 = WorkflowStep::where('workflow_id', $setup['workflow']->id)
            ->where('step_order', 2)
            ->first();

        $setup['transaction']->update([
            'current_office_id' => $setup['office2']->id,
            'current_step_id' => $step2->id,
            'received_at' => null,
        ]);

        $user = User::factory()->create(['office_id' => $setup['office2']->id]);
        $user->assignRole('Endorser');

        $result = $this->service->canComplete($setup['transaction'], $user);

        $this->assertFalse($result);
    }

    public function test_can_complete_returns_false_for_wrong_status(): void
    {
        $setup = $this->createTestSetup();

        $step2 = WorkflowStep::where('workflow_id', $setup['workflow']->id)
            ->where('step_order', 2)
            ->first();

        $setup['transaction']->update([
            'current_office_id' => $setup['office2']->id,
            'current_step_id' => $step2->id,
            'status' => 'Completed',
        ]);

        $user = User::factory()->create(['office_id' => $setup['office2']->id]);
        $user->assignRole('Endorser');

        $result = $this->service->canComplete($setup['transaction'], $user);

        $this->assertFalse($result);
    }

    // Story 3.6 - complete Tests

    public function test_complete_updates_transaction_status(): void
    {
        $setup = $this->createTestSetup();

        $step2 = WorkflowStep::where('workflow_id', $setup['workflow']->id)
            ->where('step_order', 2)
            ->first();

        $setup['transaction']->update([
            'current_office_id' => $setup['office2']->id,
            'current_step_id' => $step2->id,
        ]);

        $user = User::factory()->create(['office_id' => $setup['office2']->id]);
        $user->assignRole('Endorser');

        $actionTaken = ActionTaken::factory()->create(['is_active' => true]);

        $this->service->complete($setup['transaction'], $user, $actionTaken->id, 'Done');

        $setup['transaction']->refresh();
        $this->assertEquals('Completed', $setup['transaction']->status);
    }

    public function test_complete_creates_status_history(): void
    {
        $setup = $this->createTestSetup();

        $step2 = WorkflowStep::where('workflow_id', $setup['workflow']->id)
            ->where('step_order', 2)
            ->first();

        $setup['transaction']->update([
            'current_office_id' => $setup['office2']->id,
            'current_step_id' => $step2->id,
        ]);

        $user = User::factory()->create(['office_id' => $setup['office2']->id]);
        $user->assignRole('Endorser');

        $actionTaken = ActionTaken::factory()->create(['is_active' => true]);

        $this->service->complete($setup['transaction'], $user, $actionTaken->id);

        $this->assertDatabaseHas('transaction_status_history', [
            'transaction_id' => $setup['transaction']->id,
            'old_status' => 'In Progress',
            'new_status' => 'Completed',
            'changed_by_user_id' => $user->id,
        ]);
    }

    public function test_complete_returns_transaction_action(): void
    {
        $setup = $this->createTestSetup();

        $step2 = WorkflowStep::where('workflow_id', $setup['workflow']->id)
            ->where('step_order', 2)
            ->first();

        $setup['transaction']->update([
            'current_office_id' => $setup['office2']->id,
            'current_step_id' => $step2->id,
        ]);

        $user = User::factory()->create(['office_id' => $setup['office2']->id]);
        $user->assignRole('Endorser');

        $actionTaken = ActionTaken::factory()->create(['is_active' => true]);

        $action = $this->service->complete($setup['transaction'], $user, $actionTaken->id);

        $this->assertInstanceOf(TransactionAction::class, $action);
        $this->assertEquals(TransactionAction::TYPE_COMPLETE, $action->action_type);
        $this->assertEquals($actionTaken->id, $action->action_taken_id);
        $this->assertNull($action->to_office_id);
    }

    public function test_check_and_update_procurement_status_only_for_vch(): void
    {
        $procurement = Procurement::factory()->create(['status' => 'In Progress']);

        // All three completed
        Transaction::factory()->create([
            'procurement_id' => $procurement->id,
            'category' => 'PR',
            'status' => 'Completed',
        ]);
        Transaction::factory()->create([
            'procurement_id' => $procurement->id,
            'category' => 'PO',
            'status' => 'Completed',
        ]);

        // PR transaction - completing a PR should NOT trigger procurement completion
        $prTransaction = Transaction::factory()->create([
            'procurement_id' => $procurement->id,
            'category' => 'PR',
            'status' => 'Completed',
        ]);

        $user = User::factory()->create();
        $user->assignRole('Endorser');

        $this->service->checkAndUpdateProcurementStatus($prTransaction, $user);

        $procurement->refresh();
        $this->assertEquals('In Progress', $procurement->status);
    }

    public function test_check_and_update_procurement_status_completes_on_vch(): void
    {
        $procurement = Procurement::factory()->create(['status' => 'In Progress']);

        Transaction::factory()->create([
            'procurement_id' => $procurement->id,
            'category' => 'PR',
            'status' => 'Completed',
        ]);
        Transaction::factory()->create([
            'procurement_id' => $procurement->id,
            'category' => 'PO',
            'status' => 'Completed',
        ]);

        $vchTransaction = Transaction::factory()->create([
            'procurement_id' => $procurement->id,
            'category' => 'VCH',
            'status' => 'Completed',
        ]);

        $user = User::factory()->create();
        $user->assignRole('Endorser');

        $this->service->checkAndUpdateProcurementStatus($vchTransaction, $user);

        $procurement->refresh();
        $this->assertEquals('Completed', $procurement->status);
    }

    // Story 3.6 - getCannotCompleteReason Tests

    public function test_get_cannot_complete_reason_returns_null_when_can_complete(): void
    {
        $setup = $this->createTestSetup();

        $step2 = WorkflowStep::where('workflow_id', $setup['workflow']->id)
            ->where('step_order', 2)
            ->first();

        $setup['transaction']->update([
            'current_office_id' => $setup['office2']->id,
            'current_step_id' => $step2->id,
        ]);

        $user = User::factory()->create(['office_id' => $setup['office2']->id]);
        $user->assignRole('Endorser');

        $reason = $this->service->getCannotCompleteReason($setup['transaction'], $user);

        $this->assertNull($reason);
    }

    public function test_get_cannot_complete_reason_returns_final_step_message(): void
    {
        $setup = $this->createTestSetup();

        // Transaction at step 1 (not final)
        $reason = $this->service->getCannotCompleteReason($setup['transaction'], $setup['user']);

        $this->assertStringContainsString('final workflow step', $reason);
    }

    public function test_get_cannot_complete_reason_returns_role_message(): void
    {
        $setup = $this->createTestSetup();

        $viewer = User::factory()->create(['office_id' => $setup['office1']->id]);
        $viewer->assignRole('Viewer');

        $reason = $this->service->getCannotCompleteReason($setup['transaction'], $viewer);

        $this->assertStringContainsString('permission', $reason);
    }
}
