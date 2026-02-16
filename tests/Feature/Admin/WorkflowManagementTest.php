<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Office;
use App\Models\Transaction;
use App\Models\TransactionAction;
use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowStep;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class WorkflowManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $viewer;

    protected User $endorser;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed roles
        Role::create(['name' => 'Viewer', 'guard_name' => 'web']);
        Role::create(['name' => 'Endorser', 'guard_name' => 'web']);
        Role::create(['name' => 'Administrator', 'guard_name' => 'web']);

        // Create users with roles
        $this->admin = User::factory()->create();
        $this->admin->assignRole('Administrator');

        $this->viewer = User::factory()->create();
        $this->viewer->assignRole('Viewer');

        $this->endorser = User::factory()->create();
        $this->endorser->assignRole('Endorser');
    }

    // ==================== RBAC Tests ====================

    public function test_administrator_can_access_workflow_management(): void
    {
        $response = $this->actingAs($this->admin)->get('/admin/workflows');

        $response->assertStatus(200);
    }

    public function test_viewer_cannot_access_workflow_management(): void
    {
        $response = $this->actingAs($this->viewer)->get('/admin/workflows');

        $response->assertStatus(403);
    }

    public function test_endorser_cannot_access_workflow_management(): void
    {
        $response = $this->actingAs($this->endorser)->get('/admin/workflows');

        $response->assertStatus(403);
    }

    // ==================== Index Page Tests ====================

    public function test_index_page_loads_with_workflows(): void
    {
        $workflow = Workflow::factory()->pr()->create(['name' => 'Test Workflow']);
        $offices = Office::factory()->count(2)->create();

        WorkflowStep::factory()
            ->forWorkflow($workflow)
            ->forOffice($offices[0])
            ->order(1)
            ->create();
        WorkflowStep::factory()
            ->forWorkflow($workflow)
            ->forOffice($offices[1])
            ->order(2)
            ->final()
            ->create();

        $response = $this->actingAs($this->admin)->get('/admin/workflows');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Admin/Workflows/Index')
            ->has('workflows.data', 1)
            ->where('workflows.data.0.name', 'Test Workflow')
        );
    }

    public function test_index_filtering_by_category(): void
    {
        Workflow::factory()->pr()->create(['name' => 'PR Workflow']);
        Workflow::factory()->po()->create(['name' => 'PO Workflow']);
        Workflow::factory()->vch()->create(['name' => 'VCH Workflow']);

        $response = $this->actingAs($this->admin)->get('/admin/workflows?category=PR');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->has('workflows.data', 1)
            ->where('workflows.data.0.category', 'PR')
        );
    }

    public function test_index_filtering_by_status(): void
    {
        Workflow::factory()->create(['name' => 'Active Workflow', 'is_active' => true]);
        Workflow::factory()->inactive()->create(['name' => 'Inactive Workflow']);

        $response = $this->actingAs($this->admin)->get('/admin/workflows?status=active');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->has('workflows.data', 1)
            ->where('workflows.data.0.is_active', true)
        );
    }

    public function test_index_search_by_name(): void
    {
        Workflow::factory()->create(['name' => 'Standard Purchase Request']);
        Workflow::factory()->create(['name' => 'Emergency Purchase Order']);

        $response = $this->actingAs($this->admin)->get('/admin/workflows?search=Standard');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->has('workflows.data', 1)
            ->where('workflows.data.0.name', 'Standard Purchase Request')
        );
    }

    // ==================== Create Workflow Tests ====================

    public function test_create_page_loads_with_offices(): void
    {
        Office::factory()->count(3)->create(['is_active' => true]);

        $response = $this->actingAs($this->admin)->get('/admin/workflows/create');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Admin/Workflows/Create')
            ->has('offices', 3)
        );
    }

    public function test_can_create_workflow_with_steps(): void
    {
        $offices = Office::factory()->count(3)->create(['is_active' => true]);

        $response = $this->actingAs($this->admin)->post('/admin/workflows', [
            'name' => 'New Workflow',
            'category' => 'PR',
            'description' => 'Test description',
            'is_active' => true,
            'steps' => [
                ['office_id' => $offices[0]->id, 'expected_days' => 2],
                ['office_id' => $offices[1]->id, 'expected_days' => 3],
                ['office_id' => $offices[2]->id, 'expected_days' => 1],
            ],
        ]);

        $response->assertRedirect('/admin/workflows');

        $this->assertDatabaseHas('workflows', [
            'name' => 'New Workflow',
            'category' => 'PR',
            'description' => 'Test description',
            'is_active' => true,
        ]);

        $workflow = Workflow::where('name', 'New Workflow')->first();
        $this->assertEquals(3, $workflow->steps()->count());

        // Verify final step is set correctly
        $finalStep = $workflow->steps()->where('is_final_step', true)->first();
        $this->assertEquals(3, $finalStep->step_order);
        $this->assertEquals($offices[2]->id, $finalStep->office_id);
    }

    public function test_validation_requires_name(): void
    {
        $offices = Office::factory()->count(2)->create();

        $response = $this->actingAs($this->admin)->post('/admin/workflows', [
            'name' => '',
            'category' => 'PR',
            'steps' => [
                ['office_id' => $offices[0]->id, 'expected_days' => 2],
                ['office_id' => $offices[1]->id, 'expected_days' => 1],
            ],
        ]);

        $response->assertSessionHasErrors('name');
    }

    public function test_validation_requires_minimum_two_steps(): void
    {
        $office = Office::factory()->create();

        $response = $this->actingAs($this->admin)->post('/admin/workflows', [
            'name' => 'Test Workflow',
            'category' => 'PR',
            'steps' => [
                ['office_id' => $office->id, 'expected_days' => 2],
            ],
        ]);

        $response->assertSessionHasErrors('steps');
    }

    public function test_validation_prevents_duplicate_offices_in_steps(): void
    {
        $office = Office::factory()->create();

        $response = $this->actingAs($this->admin)->post('/admin/workflows', [
            'name' => 'Test Workflow',
            'category' => 'PR',
            'steps' => [
                ['office_id' => $office->id, 'expected_days' => 2],
                ['office_id' => $office->id, 'expected_days' => 1],
            ],
        ]);

        $response->assertSessionHasErrors('steps');
    }

    public function test_validation_requires_expected_days_minimum_one(): void
    {
        $offices = Office::factory()->count(2)->create();

        $response = $this->actingAs($this->admin)->post('/admin/workflows', [
            'name' => 'Test Workflow',
            'category' => 'PR',
            'steps' => [
                ['office_id' => $offices[0]->id, 'expected_days' => 0],
                ['office_id' => $offices[1]->id, 'expected_days' => 1],
            ],
        ]);

        $response->assertSessionHasErrors('steps.0.expected_days');
    }

    // ==================== Edit Workflow Tests ====================

    public function test_edit_page_loads_with_workflow_data(): void
    {
        $workflow = Workflow::factory()->pr()->create();
        $offices = Office::factory()->count(2)->create();

        WorkflowStep::factory()
            ->forWorkflow($workflow)
            ->forOffice($offices[0])
            ->order(1)
            ->create();
        WorkflowStep::factory()
            ->forWorkflow($workflow)
            ->forOffice($offices[1])
            ->order(2)
            ->final()
            ->create();

        $response = $this->actingAs($this->admin)->get("/admin/workflows/{$workflow->id}/edit");

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Admin/Workflows/Edit')
            ->where('workflow.id', $workflow->id)
            ->has('workflow.steps', 2)
            ->has('offices')
        );
    }

    public function test_can_update_workflow(): void
    {
        $workflow = Workflow::factory()->pr()->create(['name' => 'Original Name']);
        $offices = Office::factory()->count(3)->create();

        WorkflowStep::factory()
            ->forWorkflow($workflow)
            ->forOffice($offices[0])
            ->order(1)
            ->create();
        WorkflowStep::factory()
            ->forWorkflow($workflow)
            ->forOffice($offices[1])
            ->order(2)
            ->final()
            ->create();

        $response = $this->actingAs($this->admin)->put("/admin/workflows/{$workflow->id}", [
            'name' => 'Updated Name',
            'category' => 'PR',
            'description' => 'Updated description',
            'is_active' => false,
            'steps' => [
                ['office_id' => $offices[0]->id, 'expected_days' => 5],
                ['office_id' => $offices[2]->id, 'expected_days' => 3],
            ],
        ]);

        $response->assertRedirect('/admin/workflows');

        $this->assertDatabaseHas('workflows', [
            'id' => $workflow->id,
            'name' => 'Updated Name',
            'description' => 'Updated description',
            'is_active' => false,
        ]);

        $workflow->refresh();
        $this->assertEquals(2, $workflow->steps()->count());
    }

    public function test_category_change_prevented_when_transactions_exist(): void
    {
        $workflow = Workflow::factory()->pr()->create();
        $offices = Office::factory()->count(2)->create();

        WorkflowStep::factory()
            ->forWorkflow($workflow)
            ->forOffice($offices[0])
            ->order(1)
            ->create();
        WorkflowStep::factory()
            ->forWorkflow($workflow)
            ->forOffice($offices[1])
            ->order(2)
            ->final()
            ->create();

        // Create a transaction using this workflow
        Transaction::factory()->create(['workflow_id' => $workflow->id]);

        $response = $this->actingAs($this->admin)->put("/admin/workflows/{$workflow->id}", [
            'name' => $workflow->name,
            'category' => 'PO', // Trying to change from PR to PO
            'is_active' => true,
            'steps' => [
                ['office_id' => $offices[0]->id, 'expected_days' => 2],
                ['office_id' => $offices[1]->id, 'expected_days' => 1],
            ],
        ]);

        $response->assertSessionHasErrors('category');
    }

    // ==================== Show Page Tests ====================

    public function test_show_page_displays_workflow_details(): void
    {
        $workflow = Workflow::factory()->pr()->create(['description' => 'Test description']);
        $offices = Office::factory()->count(2)->create();

        WorkflowStep::factory()
            ->forWorkflow($workflow)
            ->forOffice($offices[0])
            ->order(1)
            ->expectedDays(3)
            ->create();
        WorkflowStep::factory()
            ->forWorkflow($workflow)
            ->forOffice($offices[1])
            ->order(2)
            ->expectedDays(2)
            ->final()
            ->create();

        $response = $this->actingAs($this->admin)->get("/admin/workflows/{$workflow->id}");

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Admin/Workflows/Show')
            ->where('workflow.id', $workflow->id)
            ->has('workflow.steps', 2)
            ->where('totalExpectedDays', 5)
        );
    }

    // ==================== Delete/Deactivate Tests ====================

    public function test_can_delete_workflow_without_transactions(): void
    {
        $workflow = Workflow::factory()->pr()->create();
        $office1 = Office::factory()->create();
        $office2 = Office::factory()->create();

        WorkflowStep::factory()
            ->forWorkflow($workflow)
            ->forOffice($office1)
            ->order(1)
            ->create();
        WorkflowStep::factory()
            ->forWorkflow($workflow)
            ->forOffice($office2)
            ->order(2)
            ->final()
            ->create();

        // Need another active workflow for the same category
        Workflow::factory()->pr()->create();

        $response = $this->actingAs($this->admin)->delete("/admin/workflows/{$workflow->id}");

        $response->assertRedirect('/admin/workflows');

        $this->assertDatabaseMissing('workflows', ['id' => $workflow->id]);
        $this->assertDatabaseMissing('workflow_steps', ['workflow_id' => $workflow->id]);
    }

    public function test_cannot_delete_workflow_with_transactions(): void
    {
        $workflow = Workflow::factory()->pr()->create();
        $office1 = Office::factory()->create();
        $office2 = Office::factory()->create();

        WorkflowStep::factory()
            ->forWorkflow($workflow)
            ->forOffice($office1)
            ->order(1)
            ->create();
        WorkflowStep::factory()
            ->forWorkflow($workflow)
            ->forOffice($office2)
            ->order(2)
            ->final()
            ->create();

        // Create a transaction using this workflow
        Transaction::factory()->create(['workflow_id' => $workflow->id]);

        // Another active workflow exists for the category
        Workflow::factory()->pr()->create();

        $response = $this->actingAs($this->admin)->delete("/admin/workflows/{$workflow->id}");

        $response->assertRedirect('/admin/workflows');
        $response->assertSessionHas('error');

        // Workflow should still exist
        $this->assertDatabaseHas('workflows', ['id' => $workflow->id]);
    }

    public function test_cannot_deactivate_only_active_workflow_for_category(): void
    {
        // Create only one active workflow for PR category
        $workflow = Workflow::factory()->pr()->create(['is_active' => true]);
        $office1 = Office::factory()->create();
        $office2 = Office::factory()->create();

        WorkflowStep::factory()
            ->forWorkflow($workflow)
            ->forOffice($office1)
            ->order(1)
            ->create();
        WorkflowStep::factory()
            ->forWorkflow($workflow)
            ->forOffice($office2)
            ->order(2)
            ->final()
            ->create();

        $response = $this->actingAs($this->admin)->delete("/admin/workflows/{$workflow->id}");

        $response->assertRedirect('/admin/workflows');
        $response->assertSessionHas('error');

        // Workflow should still be active
        $this->assertDatabaseHas('workflows', [
            'id' => $workflow->id,
            'is_active' => true,
        ]);
    }

    // ==================== Pagination Tests ====================

    public function test_pagination_works_when_records_exceed_20(): void
    {
        // Create 25 workflows
        Workflow::factory()->count(25)->create();

        $response = $this->actingAs($this->admin)->get('/admin/workflows');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->has('workflows.data', 20)
            ->where('workflows.total', 25)
            ->where('workflows.per_page', 20)
        );
    }

    // ==================== Smart Sync Update Tests ====================

    public function test_update_preserves_transaction_current_step_id(): void
    {
        $workflow = Workflow::factory()->pr()->create();
        $offices = Office::factory()->count(3)->create();

        $step1 = WorkflowStep::factory()
            ->forWorkflow($workflow)
            ->forOffice($offices[0])
            ->order(1)
            ->create(['expected_days' => 2]);
        $step2 = WorkflowStep::factory()
            ->forWorkflow($workflow)
            ->forOffice($offices[1])
            ->order(2)
            ->final()
            ->create(['expected_days' => 3]);

        // Create a transaction at step2
        $transaction = Transaction::factory()->create([
            'workflow_id' => $workflow->id,
            'current_step_id' => $step2->id,
            'status' => 'In Progress',
        ]);

        // Update: reorder steps and change expected_days (but keep same offices)
        $response = $this->actingAs($this->admin)->put("/admin/workflows/{$workflow->id}", [
            'name' => $workflow->name,
            'category' => 'PR',
            'is_active' => true,
            'steps' => [
                ['office_id' => $offices[1]->id, 'expected_days' => 5],  // was step 2, now step 1
                ['office_id' => $offices[0]->id, 'expected_days' => 7],  // was step 1, now step 2
            ],
        ]);

        $response->assertRedirect('/admin/workflows');

        // Transaction's current_step_id should still point to the same step record
        $transaction->refresh();
        $this->assertEquals($step2->id, $transaction->current_step_id);

        // But step2 should now have updated order and expected_days
        $step2->refresh();
        $this->assertEquals(1, $step2->step_order);
        $this->assertEquals(5, $step2->expected_days);
    }

    public function test_update_can_add_new_steps_to_workflow(): void
    {
        $workflow = Workflow::factory()->pr()->create();
        $offices = Office::factory()->count(4)->create();

        WorkflowStep::factory()
            ->forWorkflow($workflow)
            ->forOffice($offices[0])
            ->order(1)
            ->create();
        WorkflowStep::factory()
            ->forWorkflow($workflow)
            ->forOffice($offices[1])
            ->order(2)
            ->final()
            ->create();

        // Add two new offices to the workflow
        $response = $this->actingAs($this->admin)->put("/admin/workflows/{$workflow->id}", [
            'name' => $workflow->name,
            'category' => 'PR',
            'is_active' => true,
            'steps' => [
                ['office_id' => $offices[0]->id, 'expected_days' => 2],
                ['office_id' => $offices[2]->id, 'expected_days' => 3],  // new
                ['office_id' => $offices[1]->id, 'expected_days' => 1],
                ['office_id' => $offices[3]->id, 'expected_days' => 4],  // new
            ],
        ]);

        $response->assertRedirect('/admin/workflows');

        $workflow->refresh();
        $this->assertEquals(4, $workflow->steps()->count());

        // Verify final step is the last one
        $finalStep = $workflow->steps()->where('is_final_step', true)->first();
        $this->assertEquals(4, $finalStep->step_order);
        $this->assertEquals($offices[3]->id, $finalStep->office_id);
    }

    public function test_cannot_remove_step_with_active_transactions(): void
    {
        $workflow = Workflow::factory()->pr()->create();
        $offices = Office::factory()->count(3)->create();

        $step1 = WorkflowStep::factory()
            ->forWorkflow($workflow)
            ->forOffice($offices[0])
            ->order(1)
            ->create();
        $step2 = WorkflowStep::factory()
            ->forWorkflow($workflow)
            ->forOffice($offices[1])
            ->order(2)
            ->create();
        WorkflowStep::factory()
            ->forWorkflow($workflow)
            ->forOffice($offices[2])
            ->order(3)
            ->final()
            ->create();

        // Create an active transaction at step2
        Transaction::factory()->create([
            'workflow_id' => $workflow->id,
            'current_step_id' => $step2->id,
            'status' => 'In Progress',
        ]);

        // Try to remove step2 (offices[1]) from the workflow
        $response = $this->actingAs($this->admin)->put("/admin/workflows/{$workflow->id}", [
            'name' => $workflow->name,
            'category' => 'PR',
            'is_active' => true,
            'steps' => [
                ['office_id' => $offices[0]->id, 'expected_days' => 2],
                ['office_id' => $offices[2]->id, 'expected_days' => 1],
            ],
        ]);

        $response->assertSessionHasErrors('steps');

        // Step should still exist
        $this->assertDatabaseHas('workflow_steps', ['id' => $step2->id]);
    }

    public function test_can_remove_step_with_only_completed_transactions(): void
    {
        $workflow = Workflow::factory()->pr()->create();
        $offices = Office::factory()->count(3)->create();

        WorkflowStep::factory()
            ->forWorkflow($workflow)
            ->forOffice($offices[0])
            ->order(1)
            ->create();
        $step2 = WorkflowStep::factory()
            ->forWorkflow($workflow)
            ->forOffice($offices[1])
            ->order(2)
            ->create();
        WorkflowStep::factory()
            ->forWorkflow($workflow)
            ->forOffice($offices[2])
            ->order(3)
            ->final()
            ->create();

        // Create a completed transaction at step2
        Transaction::factory()->create([
            'workflow_id' => $workflow->id,
            'current_step_id' => $step2->id,
            'status' => 'Completed',
        ]);

        // Remove step2 from the workflow
        $response = $this->actingAs($this->admin)->put("/admin/workflows/{$workflow->id}", [
            'name' => $workflow->name,
            'category' => 'PR',
            'is_active' => true,
            'steps' => [
                ['office_id' => $offices[0]->id, 'expected_days' => 2],
                ['office_id' => $offices[2]->id, 'expected_days' => 1],
            ],
        ]);

        $response->assertRedirect('/admin/workflows');

        // Step should be deleted
        $this->assertDatabaseMissing('workflow_steps', ['id' => $step2->id]);

        // Transaction's current_step_id should be nullified
        $this->assertDatabaseHas('transactions', [
            'workflow_id' => $workflow->id,
            'current_step_id' => null,
        ]);
    }

    public function test_transaction_action_workflow_step_id_nullified_for_removed_steps(): void
    {
        $workflow = Workflow::factory()->pr()->create();
        $offices = Office::factory()->count(3)->create();

        $step1 = WorkflowStep::factory()
            ->forWorkflow($workflow)
            ->forOffice($offices[0])
            ->order(1)
            ->create();
        $step2 = WorkflowStep::factory()
            ->forWorkflow($workflow)
            ->forOffice($offices[1])
            ->order(2)
            ->create();
        WorkflowStep::factory()
            ->forWorkflow($workflow)
            ->forOffice($offices[2])
            ->order(3)
            ->final()
            ->create();

        // Create a transaction and a historical action referencing step2
        $transaction = Transaction::factory()->create([
            'workflow_id' => $workflow->id,
            'current_step_id' => $step1->id,
            'status' => 'In Progress',
        ]);
        $action = TransactionAction::factory()->endorse()->create([
            'transaction_id' => $transaction->id,
            'workflow_step_id' => $step2->id,
            'from_office_id' => $offices[0]->id,
            'to_office_id' => $offices[1]->id,
        ]);

        // Remove step2 from the workflow (no active transactions at step2)
        $response = $this->actingAs($this->admin)->put("/admin/workflows/{$workflow->id}", [
            'name' => $workflow->name,
            'category' => 'PR',
            'is_active' => true,
            'steps' => [
                ['office_id' => $offices[0]->id, 'expected_days' => 2],
                ['office_id' => $offices[2]->id, 'expected_days' => 1],
            ],
        ]);

        $response->assertRedirect('/admin/workflows');

        // The action's workflow_step_id should be nullified
        $action->refresh();
        $this->assertNull($action->workflow_step_id);

        // But the transaction's current_step_id (at step1) should be preserved
        $transaction->refresh();
        $this->assertEquals($step1->id, $transaction->current_step_id);
    }
}
