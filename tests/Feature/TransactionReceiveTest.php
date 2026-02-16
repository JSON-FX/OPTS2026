<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\ActionTaken;
use App\Models\Office;
use App\Models\Procurement;
use App\Models\PurchaseRequest;
use App\Models\Transaction;
use App\Models\TransactionAction;
use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowStep;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature tests for Transaction Receive Action.
 *
 * Story 3.5 - Receive Action Implementation
 */
class TransactionReceiveTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RoleSeeder::class);
    }

    /**
     * Create a transaction that has been endorsed and is ready to receive.
     *
     * @return array{transaction: Transaction, office1: Office, office2: Office, endorser: User, receiver: User, workflow: Workflow, step1: WorkflowStep, step2: WorkflowStep}
     */
    protected function createReceivableTransaction(): array
    {
        $office1 = Office::factory()->create(['name' => 'Budget Office', 'is_active' => true]);
        $office2 = Office::factory()->create(['name' => 'BAC Office', 'is_active' => true]);
        $office3 = Office::factory()->create(['name' => 'Mayors Office', 'is_active' => true]);

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
            'is_final_step' => false,
        ]);

        WorkflowStep::factory()->create([
            'workflow_id' => $workflow->id,
            'office_id' => $office3->id,
            'step_order' => 3,
            'is_final_step' => true,
        ]);

        $endorser = User::factory()->create(['office_id' => $office1->id]);
        $endorser->assignRole('Endorser');

        $receiver = User::factory()->create(['office_id' => $office2->id]);
        $receiver->assignRole('Endorser');

        $procurement = Procurement::factory()->create();
        $transaction = Transaction::factory()->create([
            'procurement_id' => $procurement->id,
            'category' => 'PR',
            'status' => 'In Progress',
            'workflow_id' => $workflow->id,
            'current_office_id' => $office2->id,
            'current_user_id' => null,
            'current_step_id' => $step1->id,
            'received_at' => null,
            'endorsed_at' => now(),
        ]);

        PurchaseRequest::factory()->create(['transaction_id' => $transaction->id]);

        // Create the endorsement action that moved it to office2
        TransactionAction::create([
            'transaction_id' => $transaction->id,
            'action_type' => TransactionAction::TYPE_ENDORSE,
            'action_taken_id' => ActionTaken::factory()->create(['is_active' => true])->id,
            'from_office_id' => $office1->id,
            'to_office_id' => $office2->id,
            'from_user_id' => $endorser->id,
            'workflow_step_id' => $step1->id,
            'is_out_of_workflow' => false,
            'ip_address' => '127.0.0.1',
        ]);

        return [
            'transaction' => $transaction,
            'office1' => $office1,
            'office2' => $office2,
            'endorser' => $endorser,
            'receiver' => $receiver,
            'workflow' => $workflow,
            'step1' => $step1,
            'step2' => $step2,
        ];
    }

    public function test_pending_page_shows_correct_transactions(): void
    {
        $setup = $this->createReceivableTransaction();

        $response = $this->actingAs($setup['receiver'])
            ->get(route('transactions.pending'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Transactions/Pending')
            ->has('transactions.data', 1)
        );
    }

    public function test_pending_page_excludes_already_received(): void
    {
        $setup = $this->createReceivableTransaction();

        // Mark the transaction as received
        $setup['transaction']->update(['received_at' => now()]);

        $response = $this->actingAs($setup['receiver'])
            ->get(route('transactions.pending'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Transactions/Pending')
            ->has('transactions.data', 0)
        );
    }

    public function test_pending_page_forbidden_for_viewer(): void
    {
        $setup = $this->createReceivableTransaction();

        $viewer = User::factory()->create(['office_id' => $setup['office2']->id]);
        $viewer->assignRole('Viewer');

        $response = $this->actingAs($viewer)
            ->get(route('transactions.pending'));

        $response->assertForbidden();
    }

    public function test_receive_creates_action_with_correct_data(): void
    {
        $setup = $this->createReceivableTransaction();

        $response = $this->actingAs($setup['receiver'])
            ->post(route('transactions.receive.store', $setup['transaction']), [
                'notes' => 'Received and acknowledged',
            ]);

        $response->assertRedirect(route('transactions.pending'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('transaction_actions', [
            'transaction_id' => $setup['transaction']->id,
            'action_type' => 'receive',
            'from_office_id' => $setup['office1']->id,
            'to_office_id' => $setup['office2']->id,
            'from_user_id' => $setup['endorser']->id,
            'to_user_id' => $setup['receiver']->id,
            'notes' => 'Received and acknowledged',
        ]);
    }

    public function test_transaction_updated_after_receive(): void
    {
        $setup = $this->createReceivableTransaction();

        $this->actingAs($setup['receiver'])
            ->post(route('transactions.receive.store', $setup['transaction']));

        $setup['transaction']->refresh();

        $this->assertEquals($setup['receiver']->id, $setup['transaction']->current_user_id);
        $this->assertNotNull($setup['transaction']->received_at);
    }

    public function test_workflow_step_advances_after_receive(): void
    {
        $setup = $this->createReceivableTransaction();

        $this->actingAs($setup['receiver'])
            ->post(route('transactions.receive.store', $setup['transaction']));

        $setup['transaction']->refresh();

        // Should advance from step1 to step2
        $this->assertEquals($setup['step2']->id, $setup['transaction']->current_step_id);
    }

    public function test_cannot_receive_already_received_transaction(): void
    {
        $setup = $this->createReceivableTransaction();

        // Mark as already received
        $setup['transaction']->update([
            'received_at' => now(),
            'current_user_id' => $setup['receiver']->id,
        ]);

        $response = $this->actingAs($setup['receiver'])
            ->post(route('transactions.receive.store', $setup['transaction']));

        $response->assertForbidden();
    }

    public function test_cannot_receive_from_wrong_office(): void
    {
        $setup = $this->createReceivableTransaction();

        // User from a different office
        $wrongOfficeUser = User::factory()->create(['office_id' => $setup['office1']->id]);
        $wrongOfficeUser->assignRole('Endorser');

        $response = $this->actingAs($wrongOfficeUser)
            ->post(route('transactions.receive.store', $setup['transaction']));

        $response->assertForbidden();
    }

    public function test_bulk_receive_processes_multiple_transactions(): void
    {
        $setup = $this->createReceivableTransaction();

        // Create a second receivable transaction
        $procurement2 = Procurement::factory()->create();
        $transaction2 = Transaction::factory()->create([
            'procurement_id' => $procurement2->id,
            'category' => 'PR',
            'status' => 'In Progress',
            'workflow_id' => $setup['workflow']->id,
            'current_office_id' => $setup['office2']->id,
            'current_step_id' => $setup['step1']->id,
            'received_at' => null,
            'endorsed_at' => now(),
        ]);

        TransactionAction::create([
            'transaction_id' => $transaction2->id,
            'action_type' => TransactionAction::TYPE_ENDORSE,
            'action_taken_id' => ActionTaken::factory()->create(['is_active' => true])->id,
            'from_office_id' => $setup['office1']->id,
            'to_office_id' => $setup['office2']->id,
            'from_user_id' => $setup['endorser']->id,
            'workflow_step_id' => $setup['step1']->id,
            'is_out_of_workflow' => false,
            'ip_address' => '127.0.0.1',
        ]);

        $response = $this->actingAs($setup['receiver'])
            ->post(route('transactions.receive.bulk'), [
                'transaction_ids' => [$setup['transaction']->id, $transaction2->id],
            ]);

        $response->assertRedirect(route('transactions.pending'));
        $response->assertSessionHas('success');

        // Both transactions should have receive actions
        $this->assertDatabaseHas('transaction_actions', [
            'transaction_id' => $setup['transaction']->id,
            'action_type' => 'receive',
        ]);
        $this->assertDatabaseHas('transaction_actions', [
            'transaction_id' => $transaction2->id,
            'action_type' => 'receive',
        ]);
    }

    public function test_bulk_receive_handles_partial_failures(): void
    {
        $setup = $this->createReceivableTransaction();

        // Create a transaction that is already received (will fail)
        $procurement2 = Procurement::factory()->create();
        $transaction2 = Transaction::factory()->create([
            'procurement_id' => $procurement2->id,
            'category' => 'PR',
            'status' => 'In Progress',
            'workflow_id' => $setup['workflow']->id,
            'current_office_id' => $setup['office2']->id,
            'current_step_id' => $setup['step1']->id,
            'received_at' => now(), // Already received
            'endorsed_at' => now(),
        ]);

        $response = $this->actingAs($setup['receiver'])
            ->post(route('transactions.receive.bulk'), [
                'transaction_ids' => [$setup['transaction']->id, $transaction2->id],
            ]);

        $response->assertRedirect(route('transactions.pending'));
        $response->assertSessionHas('success');

        // Only first transaction should have receive action
        $this->assertDatabaseHas('transaction_actions', [
            'transaction_id' => $setup['transaction']->id,
            'action_type' => 'receive',
        ]);
        $this->assertDatabaseMissing('transaction_actions', [
            'transaction_id' => $transaction2->id,
            'action_type' => 'receive',
        ]);
    }

    public function test_administrator_can_receive(): void
    {
        $setup = $this->createReceivableTransaction();

        $admin = User::factory()->create(['office_id' => $setup['office2']->id]);
        $admin->assignRole('Administrator');

        $response = $this->actingAs($admin)
            ->post(route('transactions.receive.store', $setup['transaction']));

        $response->assertRedirect(route('transactions.pending'));

        $this->assertDatabaseHas('transaction_actions', [
            'transaction_id' => $setup['transaction']->id,
            'action_type' => 'receive',
            'to_user_id' => $admin->id,
        ]);
    }

    public function test_receive_without_notes_succeeds(): void
    {
        $setup = $this->createReceivableTransaction();

        $response = $this->actingAs($setup['receiver'])
            ->post(route('transactions.receive.store', $setup['transaction']));

        $response->assertRedirect();

        $this->assertDatabaseHas('transaction_actions', [
            'transaction_id' => $setup['transaction']->id,
            'action_type' => 'receive',
            'notes' => null,
        ]);
    }

    public function test_ip_address_logged_in_receive_action(): void
    {
        $setup = $this->createReceivableTransaction();

        $this->actingAs($setup['receiver'])
            ->post(route('transactions.receive.store', $setup['transaction']));

        $action = TransactionAction::where('transaction_id', $setup['transaction']->id)
            ->where('action_type', 'receive')
            ->first();

        $this->assertNotNull($action->ip_address);
    }

    public function test_cannot_receive_transaction_with_wrong_status(): void
    {
        $setup = $this->createReceivableTransaction();

        $setup['transaction']->update(['status' => 'On Hold']);

        $response = $this->actingAs($setup['receiver'])
            ->post(route('transactions.receive.store', $setup['transaction']));

        $response->assertForbidden();
    }

    public function test_show_page_displays_can_receive_status(): void
    {
        $setup = $this->createReceivableTransaction();

        $response = $this->actingAs($setup['receiver'])
            ->get(route('purchase-requests.show', $setup['transaction']->purchaseRequest->id));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('PurchaseRequests/Show')
            ->has('canReceive')
            ->has('cannotReceiveReason')
        );
    }

    public function test_pending_receipts_count_shared_in_inertia(): void
    {
        $setup = $this->createReceivableTransaction();

        $response = $this->actingAs($setup['receiver'])
            ->get(route('dashboard'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('pendingReceiptsCount', 1)
        );
    }

    public function test_bulk_receive_validation_requires_transaction_ids(): void
    {
        $setup = $this->createReceivableTransaction();

        $response = $this->actingAs($setup['receiver'])
            ->post(route('transactions.receive.bulk'), [
                'transaction_ids' => [],
            ]);

        $response->assertSessionHasErrors(['transaction_ids']);
    }

    public function test_workflow_step_does_not_advance_for_out_of_workflow(): void
    {
        $setup = $this->createReceivableTransaction();

        // Update the endorsement action to be out of workflow
        TransactionAction::where('transaction_id', $setup['transaction']->id)
            ->where('action_type', TransactionAction::TYPE_ENDORSE)
            ->update(['is_out_of_workflow' => true]);

        $this->actingAs($setup['receiver'])
            ->post(route('transactions.receive.store', $setup['transaction']));

        $setup['transaction']->refresh();

        // Step should NOT advance for out-of-workflow
        $this->assertEquals($setup['step1']->id, $setup['transaction']->current_step_id);
    }
}
