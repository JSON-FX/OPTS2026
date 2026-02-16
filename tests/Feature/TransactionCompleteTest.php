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
 * Feature tests for Transaction Complete.
 *
 * Story 3.6 - Complete Action Implementation
 */
class TransactionCompleteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RoleSeeder::class);
    }

    /**
     * Create a transaction at the final workflow step ready for completion.
     *
     * @return array{transaction: Transaction, office: Office, user: User, workflow: Workflow, finalStep: WorkflowStep, actionTaken: ActionTaken}
     */
    protected function createCompletableTransaction(): array
    {
        $office1 = Office::factory()->create(['name' => 'Budget Office', 'is_active' => true]);
        $office2 = Office::factory()->create(['name' => 'Releasing Office', 'is_active' => true]);

        $workflow = Workflow::factory()->create([
            'category' => 'PR',
            'is_active' => true,
        ]);

        WorkflowStep::factory()->create([
            'workflow_id' => $workflow->id,
            'office_id' => $office1->id,
            'step_order' => 1,
            'is_final_step' => false,
        ]);

        $finalStep = WorkflowStep::factory()->create([
            'workflow_id' => $workflow->id,
            'office_id' => $office2->id,
            'step_order' => 2,
            'is_final_step' => true,
        ]);

        $user = User::factory()->create(['office_id' => $office2->id]);
        $user->assignRole('Endorser');

        $procurement = Procurement::factory()->create(['status' => 'In Progress']);
        $transaction = Transaction::factory()->create([
            'procurement_id' => $procurement->id,
            'category' => 'PR',
            'status' => 'In Progress',
            'workflow_id' => $workflow->id,
            'current_office_id' => $office2->id,
            'current_step_id' => $finalStep->id,
            'received_at' => now(),
        ]);

        PurchaseRequest::factory()->create(['transaction_id' => $transaction->id]);

        $actionTaken = ActionTaken::factory()->create(['is_active' => true]);

        return [
            'transaction' => $transaction,
            'office' => $office2,
            'user' => $user,
            'workflow' => $workflow,
            'finalStep' => $finalStep,
            'actionTaken' => $actionTaken,
        ];
    }

    public function test_complete_at_final_step_succeeds(): void
    {
        $setup = $this->createCompletableTransaction();

        $response = $this->actingAs($setup['user'])
            ->post(route('transactions.complete.store', $setup['transaction']), [
                'action_taken_id' => $setup['actionTaken']->id,
                'notes' => 'Completed processing',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
    }

    public function test_complete_at_non_final_step_fails(): void
    {
        $office = Office::factory()->create(['is_active' => true]);
        $workflow = Workflow::factory()->create(['category' => 'PR', 'is_active' => true]);

        $step1 = WorkflowStep::factory()->create([
            'workflow_id' => $workflow->id,
            'office_id' => $office->id,
            'step_order' => 1,
            'is_final_step' => false,
        ]);

        WorkflowStep::factory()->create([
            'workflow_id' => $workflow->id,
            'office_id' => Office::factory()->create(['is_active' => true])->id,
            'step_order' => 2,
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
            'current_step_id' => $step1->id,
            'received_at' => now(),
        ]);

        PurchaseRequest::factory()->create(['transaction_id' => $transaction->id]);
        $actionTaken = ActionTaken::factory()->create(['is_active' => true]);

        $response = $this->actingAs($user)
            ->post(route('transactions.complete.store', $transaction), [
                'action_taken_id' => $actionTaken->id,
            ]);

        $response->assertForbidden();
    }

    public function test_complete_creates_action_with_correct_data(): void
    {
        $setup = $this->createCompletableTransaction();

        $this->actingAs($setup['user'])
            ->post(route('transactions.complete.store', $setup['transaction']), [
                'action_taken_id' => $setup['actionTaken']->id,
                'notes' => 'Final approval',
            ]);

        $this->assertDatabaseHas('transaction_actions', [
            'transaction_id' => $setup['transaction']->id,
            'action_type' => 'complete',
            'action_taken_id' => $setup['actionTaken']->id,
            'from_office_id' => $setup['office']->id,
            'to_office_id' => null,
            'from_user_id' => $setup['user']->id,
            'workflow_step_id' => $setup['finalStep']->id,
            'notes' => 'Final approval',
        ]);
    }

    public function test_transaction_status_changes_to_completed(): void
    {
        $setup = $this->createCompletableTransaction();

        $this->actingAs($setup['user'])
            ->post(route('transactions.complete.store', $setup['transaction']), [
                'action_taken_id' => $setup['actionTaken']->id,
            ]);

        $setup['transaction']->refresh();
        $this->assertEquals('Completed', $setup['transaction']->status);
    }

    public function test_status_history_record_created(): void
    {
        $setup = $this->createCompletableTransaction();

        $this->actingAs($setup['user'])
            ->post(route('transactions.complete.store', $setup['transaction']), [
                'action_taken_id' => $setup['actionTaken']->id,
            ]);

        $this->assertDatabaseHas('transaction_status_history', [
            'transaction_id' => $setup['transaction']->id,
            'old_status' => 'In Progress',
            'new_status' => 'Completed',
            'changed_by_user_id' => $setup['user']->id,
        ]);
    }

    public function test_procurement_status_updates_when_vch_completed(): void
    {
        $procurement = Procurement::factory()->create(['status' => 'In Progress']);

        // Create completed PR transaction
        $prWorkflow = Workflow::factory()->create(['category' => 'PR', 'is_active' => true]);
        Transaction::factory()->create([
            'procurement_id' => $procurement->id,
            'category' => 'PR',
            'status' => 'Completed',
            'workflow_id' => $prWorkflow->id,
        ]);

        // Create completed PO transaction
        $poWorkflow = Workflow::factory()->create(['category' => 'PO', 'is_active' => true]);
        Transaction::factory()->create([
            'procurement_id' => $procurement->id,
            'category' => 'PO',
            'status' => 'Completed',
            'workflow_id' => $poWorkflow->id,
        ]);

        // Create VCH transaction at final step
        $office = Office::factory()->create(['is_active' => true]);
        $vchWorkflow = Workflow::factory()->create(['category' => 'VCH', 'is_active' => true]);
        $finalStep = WorkflowStep::factory()->create([
            'workflow_id' => $vchWorkflow->id,
            'office_id' => $office->id,
            'step_order' => 1,
            'is_final_step' => true,
        ]);

        $vchTransaction = Transaction::factory()->create([
            'procurement_id' => $procurement->id,
            'category' => 'VCH',
            'status' => 'In Progress',
            'workflow_id' => $vchWorkflow->id,
            'current_office_id' => $office->id,
            'current_step_id' => $finalStep->id,
            'received_at' => now(),
        ]);

        $user = User::factory()->create(['office_id' => $office->id]);
        $user->assignRole('Endorser');
        $actionTaken = ActionTaken::factory()->create(['is_active' => true]);

        $this->actingAs($user)
            ->post(route('transactions.complete.store', $vchTransaction), [
                'action_taken_id' => $actionTaken->id,
            ]);

        $procurement->refresh();
        $this->assertEquals('Completed', $procurement->status);

        $this->assertDatabaseHas('procurement_status_history', [
            'procurement_id' => $procurement->id,
            'old_status' => 'In Progress',
            'new_status' => 'Completed',
        ]);
    }

    public function test_procurement_status_unchanged_when_pr_completed(): void
    {
        $setup = $this->createCompletableTransaction();

        $this->actingAs($setup['user'])
            ->post(route('transactions.complete.store', $setup['transaction']), [
                'action_taken_id' => $setup['actionTaken']->id,
            ]);

        // PR completion should not change procurement status
        $procurement = $setup['transaction']->fresh()->procurement;
        $this->assertNotEquals('Completed', $procurement->status);
    }

    public function test_cannot_complete_already_completed_transaction(): void
    {
        $setup = $this->createCompletableTransaction();
        $setup['transaction']->update(['status' => 'Completed']);

        $response = $this->actingAs($setup['user'])
            ->post(route('transactions.complete.store', $setup['transaction']), [
                'action_taken_id' => $setup['actionTaken']->id,
            ]);

        $response->assertForbidden();
    }

    public function test_cannot_complete_unreceived_transaction(): void
    {
        $setup = $this->createCompletableTransaction();
        $setup['transaction']->update(['received_at' => null]);

        $response = $this->actingAs($setup['user'])
            ->post(route('transactions.complete.store', $setup['transaction']), [
                'action_taken_id' => $setup['actionTaken']->id,
            ]);

        $response->assertForbidden();
    }

    public function test_viewer_cannot_complete_transaction(): void
    {
        $setup = $this->createCompletableTransaction();

        $viewer = User::factory()->create(['office_id' => $setup['office']->id]);
        $viewer->assignRole('Viewer');

        $response = $this->actingAs($viewer)
            ->post(route('transactions.complete.store', $setup['transaction']), [
                'action_taken_id' => $setup['actionTaken']->id,
            ]);

        $response->assertForbidden();
    }

    public function test_administrator_can_complete_transaction(): void
    {
        $setup = $this->createCompletableTransaction();

        $admin = User::factory()->create(['office_id' => $setup['office']->id]);
        $admin->assignRole('Administrator');

        $response = $this->actingAs($admin)
            ->post(route('transactions.complete.store', $setup['transaction']), [
                'action_taken_id' => $setup['actionTaken']->id,
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
    }

    public function test_wrong_office_user_cannot_complete(): void
    {
        $setup = $this->createCompletableTransaction();

        $otherOffice = Office::factory()->create(['is_active' => true]);
        $otherUser = User::factory()->create(['office_id' => $otherOffice->id]);
        $otherUser->assignRole('Endorser');

        $response = $this->actingAs($otherUser)
            ->post(route('transactions.complete.store', $setup['transaction']), [
                'action_taken_id' => $setup['actionTaken']->id,
            ]);

        $response->assertForbidden();
    }

    public function test_validation_requires_action_taken_id(): void
    {
        $setup = $this->createCompletableTransaction();

        $response = $this->actingAs($setup['user'])
            ->post(route('transactions.complete.store', $setup['transaction']), [
                'action_taken_id' => '',
            ]);

        $response->assertSessionHasErrors(['action_taken_id']);
    }

    public function test_complete_without_notes_succeeds(): void
    {
        $setup = $this->createCompletableTransaction();

        $response = $this->actingAs($setup['user'])
            ->post(route('transactions.complete.store', $setup['transaction']), [
                'action_taken_id' => $setup['actionTaken']->id,
            ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('transaction_actions', [
            'transaction_id' => $setup['transaction']->id,
            'action_type' => 'complete',
            'notes' => null,
        ]);
    }

    public function test_ip_address_logged_in_complete_action(): void
    {
        $setup = $this->createCompletableTransaction();

        $this->actingAs($setup['user'])
            ->post(route('transactions.complete.store', $setup['transaction']), [
                'action_taken_id' => $setup['actionTaken']->id,
            ]);

        $action = TransactionAction::where('transaction_id', $setup['transaction']->id)
            ->where('action_type', 'complete')
            ->first();

        $this->assertNotNull($action->ip_address);
    }

    public function test_show_page_displays_can_complete_status(): void
    {
        $setup = $this->createCompletableTransaction();

        $response = $this->actingAs($setup['user'])
            ->get(route('purchase-requests.show', $setup['transaction']->purchaseRequest->id));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('PurchaseRequests/Show')
            ->has('canComplete')
            ->has('cannotCompleteReason')
            ->has('actionTakenOptions')
        );
    }

    public function test_procurement_completed_message_in_session(): void
    {
        $procurement = Procurement::factory()->create(['status' => 'In Progress']);

        // Create completed PR and PO
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

        // Create VCH at final step
        $office = Office::factory()->create(['is_active' => true]);
        $vchWorkflow = Workflow::factory()->create(['category' => 'VCH', 'is_active' => true]);
        $finalStep = WorkflowStep::factory()->create([
            'workflow_id' => $vchWorkflow->id,
            'office_id' => $office->id,
            'step_order' => 1,
            'is_final_step' => true,
        ]);

        $vchTransaction = Transaction::factory()->create([
            'procurement_id' => $procurement->id,
            'category' => 'VCH',
            'status' => 'In Progress',
            'workflow_id' => $vchWorkflow->id,
            'current_office_id' => $office->id,
            'current_step_id' => $finalStep->id,
            'received_at' => now(),
        ]);

        $user = User::factory()->create(['office_id' => $office->id]);
        $user->assignRole('Endorser');
        $actionTaken = ActionTaken::factory()->create(['is_active' => true]);

        $response = $this->actingAs($user)
            ->post(route('transactions.complete.store', $vchTransaction), [
                'action_taken_id' => $actionTaken->id,
            ]);

        $response->assertSessionHas('success', 'Transaction completed successfully. Procurement fully completed!');
    }
}
