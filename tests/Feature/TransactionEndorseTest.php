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
 * Feature tests for Transaction Endorsement.
 *
 * Story 3.4 - Endorse Action Implementation
 */
class TransactionEndorseTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RoleSeeder::class);
    }

    /**
     * Create a transaction setup with workflow and offices.
     *
     * @return array{transaction: Transaction, office1: Office, office2: Office, user: User, workflow: Workflow}
     */
    protected function createEndorsableTransaction(): array
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

        WorkflowStep::factory()->create([
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

        PurchaseRequest::factory()->create(['transaction_id' => $transaction->id]);

        return [
            'transaction' => $transaction,
            'office1' => $office1,
            'office2' => $office2,
            'user' => $user,
            'workflow' => $workflow,
        ];
    }

    public function test_endorse_form_loads_for_authorized_user(): void
    {
        $setup = $this->createEndorsableTransaction();
        ActionTaken::factory()->count(3)->create(['is_active' => true]);

        $response = $this->actingAs($setup['user'])
            ->get(route('transactions.endorse.create', $setup['transaction']));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Transactions/Endorse')
            ->has('transaction')
            ->has('actionTakenOptions', 3)
            ->has('officeOptions')
            ->has('expectedNextOffice')
        );
    }

    public function test_endorse_form_forbidden_for_wrong_office(): void
    {
        $setup = $this->createEndorsableTransaction();

        // Create user at a different office
        $otherOffice = Office::factory()->create(['is_active' => true]);
        $otherUser = User::factory()->create(['office_id' => $otherOffice->id]);
        $otherUser->assignRole('Endorser');

        $response = $this->actingAs($otherUser)
            ->get(route('transactions.endorse.create', $setup['transaction']));

        $response->assertForbidden();
    }

    public function test_endorse_form_forbidden_for_viewer_role(): void
    {
        $setup = $this->createEndorsableTransaction();

        // Change user role to Viewer
        $viewer = User::factory()->create(['office_id' => $setup['office1']->id]);
        $viewer->assignRole('Viewer');

        $response = $this->actingAs($viewer)
            ->get(route('transactions.endorse.create', $setup['transaction']));

        $response->assertForbidden();
    }

    public function test_successful_endorsement_creates_action(): void
    {
        $setup = $this->createEndorsableTransaction();
        $actionTaken = ActionTaken::factory()->create(['is_active' => true]);

        $response = $this->actingAs($setup['user'])
            ->post(route('transactions.endorse.store', $setup['transaction']), [
                'action_taken_id' => $actionTaken->id,
                'to_office_id' => $setup['office2']->id,
                'notes' => 'Approved for processing',
            ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('transaction_actions', [
            'transaction_id' => $setup['transaction']->id,
            'action_type' => 'endorse',
            'action_taken_id' => $actionTaken->id,
            'from_office_id' => $setup['office1']->id,
            'to_office_id' => $setup['office2']->id,
            'from_user_id' => $setup['user']->id,
            'is_out_of_workflow' => false,
            'notes' => 'Approved for processing',
        ]);
    }

    public function test_transaction_updated_after_endorsement(): void
    {
        $setup = $this->createEndorsableTransaction();
        $actionTaken = ActionTaken::factory()->create(['is_active' => true]);

        $this->actingAs($setup['user'])
            ->post(route('transactions.endorse.store', $setup['transaction']), [
                'action_taken_id' => $actionTaken->id,
                'to_office_id' => $setup['office2']->id,
            ]);

        $setup['transaction']->refresh();

        $this->assertEquals($setup['office2']->id, $setup['transaction']->current_office_id);
        $this->assertNull($setup['transaction']->current_user_id);
        $this->assertNotNull($setup['transaction']->endorsed_at);
        $this->assertNull($setup['transaction']->received_at);
    }

    public function test_out_of_workflow_flag_set_correctly(): void
    {
        $setup = $this->createEndorsableTransaction();
        $actionTaken = ActionTaken::factory()->create(['is_active' => true]);

        // Create a non-workflow office
        $nonWorkflowOffice = Office::factory()->create(['is_active' => true]);

        $this->actingAs($setup['user'])
            ->post(route('transactions.endorse.store', $setup['transaction']), [
                'action_taken_id' => $actionTaken->id,
                'to_office_id' => $nonWorkflowOffice->id,
            ]);

        $action = TransactionAction::where('transaction_id', $setup['transaction']->id)
            ->where('action_type', 'endorse')
            ->first();

        $this->assertTrue($action->is_out_of_workflow);
    }

    public function test_validation_errors_returned_for_invalid_data(): void
    {
        $setup = $this->createEndorsableTransaction();

        $response = $this->actingAs($setup['user'])
            ->post(route('transactions.endorse.store', $setup['transaction']), [
                'action_taken_id' => '',
                'to_office_id' => '',
                'notes' => str_repeat('a', 1001), // Exceeds 1000 char limit
            ]);

        $response->assertSessionHasErrors(['action_taken_id', 'to_office_id', 'notes']);
    }

    public function test_cannot_endorse_unreceived_transaction(): void
    {
        $setup = $this->createEndorsableTransaction();
        $actionTaken = ActionTaken::factory()->create(['is_active' => true]);

        // Set transaction as not received
        $setup['transaction']->update(['received_at' => null]);

        $response = $this->actingAs($setup['user'])
            ->post(route('transactions.endorse.store', $setup['transaction']), [
                'action_taken_id' => $actionTaken->id,
                'to_office_id' => $setup['office2']->id,
            ]);

        $response->assertForbidden();
    }

    public function test_cannot_endorse_at_final_step(): void
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

        PurchaseRequest::factory()->create(['transaction_id' => $transaction->id]);

        $actionTaken = ActionTaken::factory()->create(['is_active' => true]);

        $response = $this->actingAs($user)
            ->post(route('transactions.endorse.store', $transaction), [
                'action_taken_id' => $actionTaken->id,
                'to_office_id' => $office->id,
            ]);

        $response->assertForbidden();
    }

    public function test_cannot_endorse_transaction_with_wrong_status(): void
    {
        $setup = $this->createEndorsableTransaction();
        $actionTaken = ActionTaken::factory()->create(['is_active' => true]);

        // Change status to something other than "In Progress"
        $setup['transaction']->update(['status' => 'On Hold']);

        $response = $this->actingAs($setup['user'])
            ->post(route('transactions.endorse.store', $setup['transaction']), [
                'action_taken_id' => $actionTaken->id,
                'to_office_id' => $setup['office2']->id,
            ]);

        $response->assertForbidden();
    }

    public function test_ip_address_logged_in_action_record(): void
    {
        $setup = $this->createEndorsableTransaction();
        $actionTaken = ActionTaken::factory()->create(['is_active' => true]);

        $this->actingAs($setup['user'])
            ->post(route('transactions.endorse.store', $setup['transaction']), [
                'action_taken_id' => $actionTaken->id,
                'to_office_id' => $setup['office2']->id,
            ]);

        $action = TransactionAction::where('transaction_id', $setup['transaction']->id)
            ->where('action_type', 'endorse')
            ->first();

        $this->assertNotNull($action->ip_address);
    }

    public function test_administrator_can_endorse(): void
    {
        $setup = $this->createEndorsableTransaction();
        $actionTaken = ActionTaken::factory()->create(['is_active' => true]);

        // Create admin user at same office
        $admin = User::factory()->create(['office_id' => $setup['office1']->id]);
        $admin->assignRole('Administrator');

        $response = $this->actingAs($admin)
            ->post(route('transactions.endorse.store', $setup['transaction']), [
                'action_taken_id' => $actionTaken->id,
                'to_office_id' => $setup['office2']->id,
            ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('transaction_actions', [
            'transaction_id' => $setup['transaction']->id,
            'action_type' => 'endorse',
            'from_user_id' => $admin->id,
        ]);
    }

    public function test_endorsement_without_notes_succeeds(): void
    {
        $setup = $this->createEndorsableTransaction();
        $actionTaken = ActionTaken::factory()->create(['is_active' => true]);

        $response = $this->actingAs($setup['user'])
            ->post(route('transactions.endorse.store', $setup['transaction']), [
                'action_taken_id' => $actionTaken->id,
                'to_office_id' => $setup['office2']->id,
                // No notes provided
            ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('transaction_actions', [
            'transaction_id' => $setup['transaction']->id,
            'action_type' => 'endorse',
            'notes' => null,
        ]);
    }

    public function test_show_page_displays_can_endorse_status(): void
    {
        $setup = $this->createEndorsableTransaction();

        $response = $this->actingAs($setup['user'])
            ->get(route('purchase-requests.show', $setup['transaction']->purchaseRequest->id));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('PurchaseRequests/Show')
            ->has('canEndorse')
            ->has('cannotEndorseReason')
        );
    }

    public function test_redirect_after_endorsement(): void
    {
        $setup = $this->createEndorsableTransaction();
        $actionTaken = ActionTaken::factory()->create(['is_active' => true]);

        $response = $this->actingAs($setup['user'])
            ->post(route('transactions.endorse.store', $setup['transaction']), [
                'action_taken_id' => $actionTaken->id,
                'to_office_id' => $setup['office2']->id,
            ]);

        $response->assertRedirect(route('purchase-requests.show', $setup['transaction']->id));
        $response->assertSessionHas('success');
    }
}
