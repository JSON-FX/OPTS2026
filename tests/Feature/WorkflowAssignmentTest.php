<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\FundType;
use App\Models\Office;
use App\Models\Procurement;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\Supplier;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowStep;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature tests for workflow assignment on transaction creation.
 *
 * Story 3.11 - Workflow Assignment on Transaction Creation
 */
class WorkflowAssignmentTest extends TestCase
{
    use RefreshDatabase;

    protected User $endorser;

    protected Procurement $procurement;

    protected FundType $fundType;

    protected Supplier $supplier;

    protected Office $office;

    protected Workflow $prWorkflow;

    protected Workflow $poWorkflow;

    protected Workflow $vchWorkflow;

    protected WorkflowStep $prStep1;

    protected WorkflowStep $poStep1;

    protected WorkflowStep $vchStep1;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        $this->office = Office::factory()->create(['is_active' => true]);
        $office2 = Office::factory()->create(['is_active' => true]);

        $this->endorser = User::factory()->create(['office_id' => $this->office->id]);
        $this->endorser->assignRole('Endorser');

        $this->fundType = FundType::factory()->create(['abbreviation' => 'GAA']);
        $this->supplier = Supplier::factory()->create([
            'name' => 'Test Supplier',
            'address' => '123 Main Street',
        ]);

        $this->procurement = Procurement::factory()->create(['status' => 'Created']);

        // Create active workflows for each category
        $this->prWorkflow = Workflow::factory()->create(['category' => 'PR', 'is_active' => true]);
        $this->prStep1 = WorkflowStep::factory()->create([
            'workflow_id' => $this->prWorkflow->id,
            'office_id' => $this->office->id,
            'step_order' => 1,
            'expected_days' => 3,
            'is_final_step' => false,
        ]);
        WorkflowStep::factory()->create([
            'workflow_id' => $this->prWorkflow->id,
            'office_id' => $office2->id,
            'step_order' => 2,
            'expected_days' => 5,
            'is_final_step' => true,
        ]);

        $this->poWorkflow = Workflow::factory()->create(['category' => 'PO', 'is_active' => true]);
        $this->poStep1 = WorkflowStep::factory()->create([
            'workflow_id' => $this->poWorkflow->id,
            'office_id' => $this->office->id,
            'step_order' => 1,
            'expected_days' => 2,
            'is_final_step' => true,
        ]);

        $this->vchWorkflow = Workflow::factory()->create(['category' => 'VCH', 'is_active' => true]);
        $this->vchStep1 = WorkflowStep::factory()->create([
            'workflow_id' => $this->vchWorkflow->id,
            'office_id' => $this->office->id,
            'step_order' => 1,
            'expected_days' => 4,
            'is_final_step' => true,
        ]);
    }

    // PR Creation Tests

    public function test_pr_creation_assigns_workflow_fields(): void
    {
        $response = $this->actingAs($this->endorser)
            ->post(route('procurements.purchase-requests.store', $this->procurement), [
                'fund_type_id' => $this->fundType->id,
                'ref_year' => '2026',
                'ref_month' => '02',
                'ref_number' => '001',
                'is_continuation' => false,
            ]);

        $response->assertRedirect(route('procurements.show', $this->procurement));

        $transaction = Transaction::where('procurement_id', $this->procurement->id)
            ->where('category', 'PR')
            ->first();

        $this->assertNotNull($transaction);
        $this->assertEquals($this->prWorkflow->id, $transaction->workflow_id);
        $this->assertEquals($this->prStep1->id, $transaction->current_step_id);
        $this->assertEquals($this->endorser->office_id, $transaction->current_office_id);
        $this->assertEquals($this->endorser->id, $transaction->current_user_id);
        $this->assertNotNull($transaction->received_at);
    }

    // PO Creation Tests

    public function test_po_creation_assigns_workflow_fields(): void
    {
        // Create PR prerequisite
        $prTransaction = Transaction::factory()->create([
            'procurement_id' => $this->procurement->id,
            'category' => 'PR',
            'reference_number' => 'PR-GAA-2026-02-001',
        ]);
        PurchaseRequest::factory()->create([
            'transaction_id' => $prTransaction->id,
            'fund_type_id' => $this->fundType->id,
        ]);

        $response = $this->actingAs($this->endorser)
            ->post(route('procurements.purchase-orders.store', $this->procurement), [
                'supplier_id' => $this->supplier->id,
                'contract_price' => 100000.00,
                'ref_year' => '2026',
                'ref_month' => '02',
                'ref_number' => '001',
                'is_continuation' => false,
            ]);

        $response->assertRedirect(route('procurements.show', $this->procurement));

        $transaction = Transaction::where('procurement_id', $this->procurement->id)
            ->where('category', 'PO')
            ->first();

        $this->assertNotNull($transaction);
        $this->assertEquals($this->poWorkflow->id, $transaction->workflow_id);
        $this->assertEquals($this->poStep1->id, $transaction->current_step_id);
        $this->assertEquals($this->endorser->office_id, $transaction->current_office_id);
        $this->assertEquals($this->endorser->id, $transaction->current_user_id);
        $this->assertNotNull($transaction->received_at);
    }

    // VCH Creation Tests

    public function test_vch_creation_assigns_workflow_fields(): void
    {
        // Create PR + PO prerequisites
        $prTransaction = Transaction::factory()->create([
            'procurement_id' => $this->procurement->id,
            'category' => 'PR',
            'reference_number' => 'PR-GAA-2026-02-001',
        ]);
        PurchaseRequest::factory()->create([
            'transaction_id' => $prTransaction->id,
            'fund_type_id' => $this->fundType->id,
        ]);

        $poTransaction = Transaction::factory()->create([
            'procurement_id' => $this->procurement->id,
            'category' => 'PO',
            'reference_number' => 'PO-2026-02-001',
        ]);
        PurchaseOrder::factory()->create([
            'transaction_id' => $poTransaction->id,
            'supplier_id' => $this->supplier->id,
            'supplier_address' => '123 Main Street',
            'contract_price' => 100000.00,
        ]);

        $response = $this->actingAs($this->endorser)
            ->post(route('procurements.vouchers.store', $this->procurement), [
                'reference_number' => 'VCH-2026-000001',
                'payee' => 'Test Payee',
            ]);

        $response->assertRedirect(route('procurements.show', $this->procurement));

        $transaction = Transaction::where('procurement_id', $this->procurement->id)
            ->where('category', 'VCH')
            ->first();

        $this->assertNotNull($transaction);
        $this->assertEquals($this->vchWorkflow->id, $transaction->workflow_id);
        $this->assertEquals($this->vchStep1->id, $transaction->current_step_id);
        $this->assertEquals($this->endorser->office_id, $transaction->current_office_id);
        $this->assertEquals($this->endorser->id, $transaction->current_user_id);
        $this->assertNotNull($transaction->received_at);
    }

    // Cross-cutting Tests

    public function test_current_step_id_is_first_step(): void
    {
        $response = $this->actingAs($this->endorser)
            ->post(route('procurements.purchase-requests.store', $this->procurement), [
                'fund_type_id' => $this->fundType->id,
                'ref_year' => '2026',
                'ref_month' => '02',
                'ref_number' => '002',
                'is_continuation' => false,
            ]);

        $response->assertRedirect();

        $transaction = Transaction::where('procurement_id', $this->procurement->id)
            ->where('category', 'PR')
            ->first();

        // current_step_id should be step with step_order = 1
        $this->assertEquals($this->prStep1->id, $transaction->current_step_id);
        $this->assertEquals(1, $this->prStep1->step_order);
    }

    public function test_current_office_id_is_users_office(): void
    {
        $response = $this->actingAs($this->endorser)
            ->post(route('procurements.purchase-requests.store', $this->procurement), [
                'fund_type_id' => $this->fundType->id,
                'ref_year' => '2026',
                'ref_month' => '02',
                'ref_number' => '003',
                'is_continuation' => false,
            ]);

        $response->assertRedirect();

        $transaction = Transaction::where('procurement_id', $this->procurement->id)
            ->where('category', 'PR')
            ->first();

        $this->assertEquals($this->endorser->office_id, $transaction->current_office_id);
        $this->assertEquals($this->office->id, $transaction->current_office_id);
    }

    public function test_received_at_is_set(): void
    {
        $response = $this->actingAs($this->endorser)
            ->post(route('procurements.purchase-requests.store', $this->procurement), [
                'fund_type_id' => $this->fundType->id,
                'ref_year' => '2026',
                'ref_month' => '02',
                'ref_number' => '004',
                'is_continuation' => false,
            ]);

        $response->assertRedirect();

        $transaction = Transaction::where('procurement_id', $this->procurement->id)
            ->where('category', 'PR')
            ->first();

        $this->assertNotNull($transaction->received_at);
    }

    public function test_creation_succeeds_without_active_workflow(): void
    {
        // Deactivate all workflows
        Workflow::query()->update(['is_active' => false]);

        $response = $this->actingAs($this->endorser)
            ->post(route('procurements.purchase-requests.store', $this->procurement), [
                'fund_type_id' => $this->fundType->id,
                'ref_year' => '2026',
                'ref_month' => '02',
                'ref_number' => '005',
                'is_continuation' => false,
            ]);

        $response->assertRedirect(route('procurements.show', $this->procurement));

        $transaction = Transaction::where('procurement_id', $this->procurement->id)
            ->where('category', 'PR')
            ->first();

        $this->assertNotNull($transaction);
        $this->assertEquals('Created', $transaction->status);
        // Workflow fields should be null since no active workflow
        $this->assertNull($transaction->current_step_id);
        $this->assertNull($transaction->current_office_id);
        $this->assertNull($transaction->current_user_id);
        $this->assertNull($transaction->received_at);
    }
}
