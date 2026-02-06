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
}
