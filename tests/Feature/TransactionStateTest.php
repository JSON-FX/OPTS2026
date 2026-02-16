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
 * Feature tests for Transaction State Machine (Hold/Cancel/Resume).
 *
 * Story 3.7 - Transaction State Machine
 */
class TransactionStateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RoleSeeder::class);
    }

    /**
     * Create a transaction in the given status with an admin user.
     *
     * @return array{transaction: Transaction, admin: User, office: Office}
     */
    protected function createTransactionWithAdmin(string $status = 'In Progress'): array
    {
        $office = Office::factory()->create(['is_active' => true]);
        $workflow = Workflow::factory()->create(['category' => 'PR', 'is_active' => true]);
        $step = WorkflowStep::factory()->create([
            'workflow_id' => $workflow->id,
            'office_id' => $office->id,
            'step_order' => 1,
            'is_final_step' => false,
        ]);

        $admin = User::factory()->create(['office_id' => $office->id]);
        $admin->assignRole('Administrator');

        $procurement = Procurement::factory()->create(['status' => 'In Progress']);
        $transaction = Transaction::factory()->create([
            'procurement_id' => $procurement->id,
            'category' => 'PR',
            'status' => $status,
            'workflow_id' => $workflow->id,
            'current_office_id' => $office->id,
            'current_step_id' => $step->id,
            'received_at' => now(),
        ]);

        PurchaseRequest::factory()->create(['transaction_id' => $transaction->id]);

        return [
            'transaction' => $transaction,
            'admin' => $admin,
            'office' => $office,
        ];
    }

    // --- Hold Tests ---

    public function test_admin_can_hold_in_progress_transaction(): void
    {
        $setup = $this->createTransactionWithAdmin('In Progress');

        $response = $this->actingAs($setup['admin'])
            ->post(route('transactions.hold.store', $setup['transaction']), [
                'reason' => 'Budget review required',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $setup['transaction']->refresh();
        $this->assertEquals('On Hold', $setup['transaction']->status);
    }

    public function test_hold_creates_action_record(): void
    {
        $setup = $this->createTransactionWithAdmin('In Progress');

        $this->actingAs($setup['admin'])
            ->post(route('transactions.hold.store', $setup['transaction']), [
                'reason' => 'Budget review',
            ]);

        $this->assertDatabaseHas('transaction_actions', [
            'transaction_id' => $setup['transaction']->id,
            'action_type' => 'hold',
            'from_office_id' => $setup['office']->id,
            'from_user_id' => $setup['admin']->id,
            'reason' => 'Budget review',
        ]);
    }

    public function test_hold_creates_status_history(): void
    {
        $setup = $this->createTransactionWithAdmin('In Progress');

        $this->actingAs($setup['admin'])
            ->post(route('transactions.hold.store', $setup['transaction']), [
                'reason' => 'Pending documents',
            ]);

        $this->assertDatabaseHas('transaction_status_history', [
            'transaction_id' => $setup['transaction']->id,
            'old_status' => 'In Progress',
            'new_status' => 'On Hold',
            'reason' => 'Pending documents',
            'changed_by_user_id' => $setup['admin']->id,
        ]);
    }

    public function test_hold_requires_reason(): void
    {
        $setup = $this->createTransactionWithAdmin('In Progress');

        $response = $this->actingAs($setup['admin'])
            ->post(route('transactions.hold.store', $setup['transaction']), [
                'reason' => '',
            ]);

        $response->assertSessionHasErrors(['reason']);
    }

    public function test_cannot_hold_completed_transaction(): void
    {
        $setup = $this->createTransactionWithAdmin('Completed');

        $response = $this->actingAs($setup['admin'])
            ->post(route('transactions.hold.store', $setup['transaction']), [
                'reason' => 'Should not work',
            ]);

        // The service will throw an exception because the status check fails
        $response->assertStatus(500);
    }

    public function test_cannot_hold_cancelled_transaction(): void
    {
        $setup = $this->createTransactionWithAdmin('Cancelled');

        $response = $this->actingAs($setup['admin'])
            ->post(route('transactions.hold.store', $setup['transaction']), [
                'reason' => 'Should not work',
            ]);

        $response->assertStatus(500);
    }

    public function test_endorser_cannot_hold_transaction(): void
    {
        $setup = $this->createTransactionWithAdmin('In Progress');

        $endorser = User::factory()->create(['office_id' => $setup['office']->id]);
        $endorser->assignRole('Endorser');

        $response = $this->actingAs($endorser)
            ->post(route('transactions.hold.store', $setup['transaction']), [
                'reason' => 'Should not work',
            ]);

        $response->assertForbidden();
    }

    public function test_viewer_cannot_hold_transaction(): void
    {
        $setup = $this->createTransactionWithAdmin('In Progress');

        $viewer = User::factory()->create(['office_id' => $setup['office']->id]);
        $viewer->assignRole('Viewer');

        $response = $this->actingAs($viewer)
            ->post(route('transactions.hold.store', $setup['transaction']), [
                'reason' => 'Should not work',
            ]);

        $response->assertForbidden();
    }

    // --- Cancel Tests ---

    public function test_admin_can_cancel_in_progress_transaction(): void
    {
        $setup = $this->createTransactionWithAdmin('In Progress');

        $response = $this->actingAs($setup['admin'])
            ->post(route('transactions.cancel.store', $setup['transaction']), [
                'reason' => 'Duplicate transaction',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $setup['transaction']->refresh();
        $this->assertEquals('Cancelled', $setup['transaction']->status);
    }

    public function test_admin_can_cancel_on_hold_transaction(): void
    {
        $setup = $this->createTransactionWithAdmin('On Hold');

        $response = $this->actingAs($setup['admin'])
            ->post(route('transactions.cancel.store', $setup['transaction']), [
                'reason' => 'No longer needed',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $setup['transaction']->refresh();
        $this->assertEquals('Cancelled', $setup['transaction']->status);
    }

    public function test_cancel_creates_action_record(): void
    {
        $setup = $this->createTransactionWithAdmin('In Progress');

        $this->actingAs($setup['admin'])
            ->post(route('transactions.cancel.store', $setup['transaction']), [
                'reason' => 'Duplicate',
            ]);

        $this->assertDatabaseHas('transaction_actions', [
            'transaction_id' => $setup['transaction']->id,
            'action_type' => 'cancel',
            'from_user_id' => $setup['admin']->id,
            'reason' => 'Duplicate',
        ]);
    }

    public function test_cancel_creates_status_history(): void
    {
        $setup = $this->createTransactionWithAdmin('In Progress');

        $this->actingAs($setup['admin'])
            ->post(route('transactions.cancel.store', $setup['transaction']), [
                'reason' => 'Error in data',
            ]);

        $this->assertDatabaseHas('transaction_status_history', [
            'transaction_id' => $setup['transaction']->id,
            'old_status' => 'In Progress',
            'new_status' => 'Cancelled',
            'reason' => 'Error in data',
        ]);
    }

    public function test_cancel_requires_reason(): void
    {
        $setup = $this->createTransactionWithAdmin('In Progress');

        $response = $this->actingAs($setup['admin'])
            ->post(route('transactions.cancel.store', $setup['transaction']), [
                'reason' => '',
            ]);

        $response->assertSessionHasErrors(['reason']);
    }

    public function test_cannot_cancel_completed_transaction(): void
    {
        $setup = $this->createTransactionWithAdmin('Completed');

        $response = $this->actingAs($setup['admin'])
            ->post(route('transactions.cancel.store', $setup['transaction']), [
                'reason' => 'Should not work',
            ]);

        $response->assertStatus(500);
    }

    public function test_cannot_cancel_already_cancelled_transaction(): void
    {
        $setup = $this->createTransactionWithAdmin('Cancelled');

        $response = $this->actingAs($setup['admin'])
            ->post(route('transactions.cancel.store', $setup['transaction']), [
                'reason' => 'Should not work',
            ]);

        $response->assertStatus(500);
    }

    public function test_endorser_cannot_cancel_transaction(): void
    {
        $setup = $this->createTransactionWithAdmin('In Progress');

        $endorser = User::factory()->create(['office_id' => $setup['office']->id]);
        $endorser->assignRole('Endorser');

        $response = $this->actingAs($endorser)
            ->post(route('transactions.cancel.store', $setup['transaction']), [
                'reason' => 'Should not work',
            ]);

        $response->assertForbidden();
    }

    // --- Resume Tests ---

    public function test_admin_can_resume_on_hold_transaction(): void
    {
        $setup = $this->createTransactionWithAdmin('On Hold');

        $response = $this->actingAs($setup['admin'])
            ->post(route('transactions.resume.store', $setup['transaction']), [
                'reason' => 'Documents received',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $setup['transaction']->refresh();
        $this->assertEquals('In Progress', $setup['transaction']->status);
    }

    public function test_resume_creates_status_history(): void
    {
        $setup = $this->createTransactionWithAdmin('On Hold');

        $this->actingAs($setup['admin'])
            ->post(route('transactions.resume.store', $setup['transaction']), [
                'reason' => 'Issue resolved',
            ]);

        $this->assertDatabaseHas('transaction_status_history', [
            'transaction_id' => $setup['transaction']->id,
            'old_status' => 'On Hold',
            'new_status' => 'In Progress',
            'reason' => 'Issue resolved',
        ]);
    }

    public function test_resume_without_reason_uses_default(): void
    {
        $setup = $this->createTransactionWithAdmin('On Hold');

        $this->actingAs($setup['admin'])
            ->post(route('transactions.resume.store', $setup['transaction']), []);

        $this->assertDatabaseHas('transaction_status_history', [
            'transaction_id' => $setup['transaction']->id,
            'old_status' => 'On Hold',
            'new_status' => 'In Progress',
            'reason' => 'Resumed by administrator',
        ]);
    }

    public function test_cannot_resume_in_progress_transaction(): void
    {
        $setup = $this->createTransactionWithAdmin('In Progress');

        $response = $this->actingAs($setup['admin'])
            ->post(route('transactions.resume.store', $setup['transaction']), []);

        $response->assertStatus(500);
    }

    public function test_cannot_resume_completed_transaction(): void
    {
        $setup = $this->createTransactionWithAdmin('Completed');

        $response = $this->actingAs($setup['admin'])
            ->post(route('transactions.resume.store', $setup['transaction']), []);

        $response->assertStatus(500);
    }

    public function test_endorser_cannot_resume_transaction(): void
    {
        $setup = $this->createTransactionWithAdmin('On Hold');

        $endorser = User::factory()->create(['office_id' => $setup['office']->id]);
        $endorser->assignRole('Endorser');

        $response = $this->actingAs($endorser)
            ->post(route('transactions.resume.store', $setup['transaction']), []);

        $response->assertForbidden();
    }

    // --- Integration tests: held/cancelled blocks other actions ---

    public function test_cannot_endorse_held_transaction(): void
    {
        $setup = $this->createTransactionWithAdmin('On Hold');

        $endorser = User::factory()->create(['office_id' => $setup['office']->id]);
        $endorser->assignRole('Endorser');

        $actionTaken = ActionTaken::factory()->create(['is_active' => true]);
        $targetOffice = Office::factory()->create(['is_active' => true]);

        $response = $this->actingAs($endorser)
            ->post(route('transactions.endorse.store', $setup['transaction']), [
                'action_taken_id' => $actionTaken->id,
                'to_office_id' => $targetOffice->id,
            ]);

        $response->assertForbidden();
    }

    public function test_cannot_endorse_cancelled_transaction(): void
    {
        $setup = $this->createTransactionWithAdmin('Cancelled');

        $endorser = User::factory()->create(['office_id' => $setup['office']->id]);
        $endorser->assignRole('Endorser');

        $actionTaken = ActionTaken::factory()->create(['is_active' => true]);
        $targetOffice = Office::factory()->create(['is_active' => true]);

        $response = $this->actingAs($endorser)
            ->post(route('transactions.endorse.store', $setup['transaction']), [
                'action_taken_id' => $actionTaken->id,
                'to_office_id' => $targetOffice->id,
            ]);

        $response->assertForbidden();
    }

    public function test_cannot_receive_held_transaction(): void
    {
        $setup = $this->createTransactionWithAdmin('On Hold');
        $setup['transaction']->update(['received_at' => null]);

        $endorser = User::factory()->create(['office_id' => $setup['office']->id]);
        $endorser->assignRole('Endorser');

        $response = $this->actingAs($endorser)
            ->post(route('transactions.receive.store', $setup['transaction']), []);

        // canReceive checks status === 'In Progress', returns 403 via policy
        $response->assertForbidden();
    }

    public function test_hold_ip_address_logged(): void
    {
        $setup = $this->createTransactionWithAdmin('In Progress');

        $this->actingAs($setup['admin'])
            ->post(route('transactions.hold.store', $setup['transaction']), [
                'reason' => 'Test hold',
            ]);

        $action = TransactionAction::where('transaction_id', $setup['transaction']->id)
            ->where('action_type', 'hold')
            ->first();

        $this->assertNotNull($action);
        $this->assertNotNull($action->ip_address);
    }

    // --- Auto-transition test ---

    public function test_first_endorsement_transitions_created_to_in_progress(): void
    {
        $office1 = Office::factory()->create(['is_active' => true]);
        $office2 = Office::factory()->create(['is_active' => true]);
        $workflow = Workflow::factory()->create(['category' => 'PR', 'is_active' => true]);

        $step1 = WorkflowStep::factory()->create([
            'workflow_id' => $workflow->id,
            'office_id' => $office1->id,
            'step_order' => 1,
            'is_final_step' => false,
        ]);

        WorkflowStep::factory()->create([
            'workflow_id' => $workflow->id,
            'office_id' => $office2->id,
            'step_order' => 2,
            'is_final_step' => true,
        ]);

        $endorser = User::factory()->create(['office_id' => $office1->id]);
        $endorser->assignRole('Endorser');

        $procurement = Procurement::factory()->create();
        $transaction = Transaction::factory()->create([
            'procurement_id' => $procurement->id,
            'category' => 'PR',
            'status' => 'Created',
            'workflow_id' => $workflow->id,
            'current_office_id' => $office1->id,
            'current_step_id' => $step1->id,
        ]);

        PurchaseRequest::factory()->create(['transaction_id' => $transaction->id]);
        $actionTaken = ActionTaken::factory()->create(['is_active' => true]);

        $this->actingAs($endorser)
            ->post(route('transactions.endorse.store', $transaction), [
                'action_taken_id' => $actionTaken->id,
                'to_office_id' => $office2->id,
            ]);

        $transaction->refresh();
        $this->assertEquals('In Progress', $transaction->status);

        $this->assertDatabaseHas('transaction_status_history', [
            'transaction_id' => $transaction->id,
            'old_status' => 'Created',
            'new_status' => 'In Progress',
            'reason' => 'First endorsement',
        ]);
    }

    // --- Show page passes admin action props ---

    public function test_show_page_passes_hold_cancel_resume_props(): void
    {
        $setup = $this->createTransactionWithAdmin('In Progress');

        $response = $this->actingAs($setup['admin'])
            ->get(route('purchase-requests.show', $setup['transaction']->purchaseRequest->id));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('PurchaseRequests/Show')
            ->has('canHold')
            ->has('cannotHoldReason')
            ->has('canCancel')
            ->has('cannotCancelReason')
            ->has('canResume')
            ->has('cannotResumeReason')
        );
    }
}
