<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Office;
use App\Models\Procurement;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\Supplier;
use App\Models\Transaction;
use App\Models\TransactionAction;
use App\Models\User;
use App\Models\Voucher;
use App\Models\Workflow;
use App\Models\WorkflowStep;
use App\Services\TimelineService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature tests for Transaction Timeline Visualization.
 *
 * Story 3.10 - Timeline Visualization
 */
class TransactionTimelineTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RoleSeeder::class);
    }

    /**
     * Create a full workflow scenario with transaction at step 2 of 3.
     */
    protected function createTimelineScenario(): array
    {
        $office1 = Office::factory()->create(['name' => 'Budget Office', 'abbreviation' => 'BO']);
        $office2 = Office::factory()->create(['name' => 'BAC Office', 'abbreviation' => 'BAC']);
        $office3 = Office::factory()->create(['name' => 'Mayors Office', 'abbreviation' => 'MO']);

        $workflow = Workflow::factory()->create([
            'category' => 'PR',
            'is_active' => true,
        ]);

        $step1 = WorkflowStep::factory()->create([
            'workflow_id' => $workflow->id,
            'office_id' => $office1->id,
            'step_order' => 1,
            'expected_days' => 3,
            'is_final_step' => false,
        ]);

        $step2 = WorkflowStep::factory()->create([
            'workflow_id' => $workflow->id,
            'office_id' => $office2->id,
            'step_order' => 2,
            'expected_days' => 2,
            'is_final_step' => false,
        ]);

        $step3 = WorkflowStep::factory()->create([
            'workflow_id' => $workflow->id,
            'office_id' => $office3->id,
            'step_order' => 3,
            'expected_days' => 1,
            'is_final_step' => true,
        ]);

        $user = User::factory()->create(['office_id' => $office2->id]);
        $user->assignRole('Endorser');

        $procurement = Procurement::factory()->create();
        $transaction = Transaction::factory()->create([
            'procurement_id' => $procurement->id,
            'category' => 'PR',
            'status' => 'In Progress',
            'workflow_id' => $workflow->id,
            'current_office_id' => $office2->id,
            'current_user_id' => $user->id,
            'current_step_id' => $step2->id,
            'received_at' => now()->subDay(),
            'endorsed_at' => now()->subDays(2),
        ]);

        $pr = PurchaseRequest::factory()->create(['transaction_id' => $transaction->id]);

        // Create an endorse action for step 1 (completed)
        TransactionAction::create([
            'transaction_id' => $transaction->id,
            'action_type' => 'endorse',
            'from_office_id' => $office1->id,
            'to_office_id' => $office2->id,
            'from_user_id' => $user->id,
            'workflow_step_id' => $step1->id,
            'is_out_of_workflow' => false,
            'ip_address' => '127.0.0.1',
        ]);

        // Create a receive action for step 2 (current)
        TransactionAction::create([
            'transaction_id' => $transaction->id,
            'action_type' => 'receive',
            'from_office_id' => $office2->id,
            'from_user_id' => $user->id,
            'workflow_step_id' => $step2->id,
            'is_out_of_workflow' => false,
            'ip_address' => '127.0.0.1',
        ]);

        return [
            'transaction' => $transaction,
            'procurement' => $procurement,
            'purchaseRequest' => $pr,
            'workflow' => $workflow,
            'offices' => [$office1, $office2, $office3],
            'steps' => [$step1, $step2, $step3],
            'user' => $user,
        ];
    }

    public function test_timeline_service_returns_correct_structure(): void
    {
        $setup = $this->createTimelineScenario();
        $service = app(TimelineService::class);

        $timeline = $service->getTimeline($setup['transaction']);

        $this->assertArrayHasKey('steps', $timeline);
        $this->assertArrayHasKey('progress_percentage', $timeline);
        $this->assertArrayHasKey('total_steps', $timeline);
        $this->assertArrayHasKey('completed_steps', $timeline);
        $this->assertArrayHasKey('is_out_of_workflow', $timeline);

        $this->assertEquals(3, $timeline['total_steps']);
        $this->assertEquals(1, $timeline['completed_steps']);
        $this->assertEquals(33, $timeline['progress_percentage']);
        $this->assertFalse($timeline['is_out_of_workflow']);
    }

    public function test_timeline_completed_step_has_correct_status(): void
    {
        $setup = $this->createTimelineScenario();
        $service = app(TimelineService::class);

        $timeline = $service->getTimeline($setup['transaction']);

        $completedStep = $timeline['steps'][0];
        $this->assertEquals('completed', $completedStep['status']);
        $this->assertEquals(1, $completedStep['step_order']);
        $this->assertEquals('Budget Office', $completedStep['office']->name);
    }

    public function test_timeline_current_step_has_holder_and_eta(): void
    {
        $setup = $this->createTimelineScenario();
        $service = app(TimelineService::class);

        $timeline = $service->getTimeline($setup['transaction']);

        $currentStep = $timeline['steps'][1];
        $this->assertEquals('current', $currentStep['status']);
        $this->assertEquals(2, $currentStep['step_order']);
        $this->assertEquals('BAC Office', $currentStep['office']->name);
        $this->assertNotNull($currentStep['current_holder']);
        $this->assertArrayHasKey('days_at_step', $currentStep);
        $this->assertArrayHasKey('eta', $currentStep);
        $this->assertArrayHasKey('is_overdue', $currentStep);
    }

    public function test_timeline_upcoming_step_has_estimated_arrival(): void
    {
        $setup = $this->createTimelineScenario();
        $service = app(TimelineService::class);

        $timeline = $service->getTimeline($setup['transaction']);

        $upcomingStep = $timeline['steps'][2];
        $this->assertEquals('upcoming', $upcomingStep['status']);
        $this->assertEquals(3, $upcomingStep['step_order']);
        $this->assertEquals('Mayors Office', $upcomingStep['office']->name);
        $this->assertArrayHasKey('estimated_arrival', $upcomingStep);
        $this->assertTrue($upcomingStep['is_final_step']);
    }

    public function test_timeline_returns_empty_for_no_workflow(): void
    {
        $procurement = Procurement::factory()->create();
        $transaction = Transaction::factory()->create([
            'procurement_id' => $procurement->id,
            'category' => 'PR',
            'status' => 'Created',
            'workflow_id' => null,
        ]);

        $service = app(TimelineService::class);
        $timeline = $service->getTimeline($transaction);

        $this->assertEmpty($timeline['steps']);
        $this->assertEquals(0, $timeline['total_steps']);
        $this->assertEquals(0, $timeline['progress_percentage']);
    }

    public function test_timeline_detects_out_of_workflow_actions(): void
    {
        $setup = $this->createTimelineScenario();

        // Create an out-of-workflow action
        TransactionAction::create([
            'transaction_id' => $setup['transaction']->id,
            'action_type' => 'endorse',
            'from_office_id' => $setup['offices'][1]->id,
            'to_office_id' => Office::factory()->create()->id,
            'from_user_id' => $setup['user']->id,
            'workflow_step_id' => $setup['steps'][1]->id,
            'is_out_of_workflow' => true,
            'ip_address' => '127.0.0.1',
        ]);

        $service = app(TimelineService::class);
        $timeline = $service->getTimeline($setup['transaction']);

        $this->assertTrue($timeline['is_out_of_workflow']);
    }

    public function test_action_history_returns_chronological_actions(): void
    {
        $setup = $this->createTimelineScenario();
        $service = app(TimelineService::class);

        $history = $service->getActionHistory($setup['transaction']);

        $this->assertCount(2, $history);

        // Verify both action types are present
        $actionTypes = array_column($history, 'action_type');
        $this->assertContains('endorse', $actionTypes);
        $this->assertContains('receive', $actionTypes);

        // Each entry should have the expected structure
        $this->assertArrayHasKey('id', $history[0]);
        $this->assertArrayHasKey('from_user', $history[0]);
        $this->assertArrayHasKey('from_office', $history[0]);
        $this->assertArrayHasKey('is_out_of_workflow', $history[0]);
        $this->assertArrayHasKey('created_at', $history[0]);
        $this->assertArrayHasKey('workflow_step_order', $history[0]);
    }

    public function test_purchase_request_show_page_includes_timeline_data(): void
    {
        $this->withoutVite();
        $setup = $this->createTimelineScenario();

        $response = $this->actingAs($setup['user'])
            ->get(route('purchase-requests.show', $setup['purchaseRequest']->id));

        $response->assertStatus(200);
        $response->assertInertia(
            fn ($page) => $page
                ->component('PurchaseRequests/Show')
                ->has('timeline')
                ->has('timeline.steps', 3)
                ->has('timeline.progress_percentage')
                ->has('timeline.total_steps')
                ->has('timeline.completed_steps')
                ->has('actionHistory')
        );
    }

    public function test_purchase_order_show_page_includes_timeline_data(): void
    {
        $this->withoutVite();
        $setup = $this->createTimelineScenario();

        // Create a PO for the same procurement
        $poTransaction = Transaction::factory()->create([
            'procurement_id' => $setup['procurement']->id,
            'category' => 'PO',
            'status' => 'In Progress',
            'workflow_id' => $setup['workflow']->id,
            'current_step_id' => $setup['steps'][0]->id,
            'current_office_id' => $setup['offices'][0]->id,
        ]);

        $supplier = Supplier::factory()->create();
        $po = PurchaseOrder::factory()->create([
            'transaction_id' => $poTransaction->id,
            'supplier_id' => $supplier->id,
        ]);

        $response = $this->actingAs($setup['user'])
            ->get(route('purchase-orders.show', $po->id));

        $response->assertStatus(200);
        $response->assertInertia(
            fn ($page) => $page
                ->component('PurchaseOrders/Show')
                ->has('timeline')
                ->has('actionHistory')
        );
    }

    public function test_voucher_show_page_includes_timeline_data(): void
    {
        $this->withoutVite();
        $setup = $this->createTimelineScenario();

        // Create a VCH for the same procurement
        $vchTransaction = Transaction::factory()->create([
            'procurement_id' => $setup['procurement']->id,
            'category' => 'VCH',
            'status' => 'Created',
            'workflow_id' => null,
        ]);

        $voucher = Voucher::factory()->create([
            'transaction_id' => $vchTransaction->id,
        ]);

        $response = $this->actingAs($setup['user'])
            ->get(route('vouchers.show', $voucher->id));

        $response->assertStatus(200);
        $response->assertInertia(
            fn ($page) => $page
                ->component('Vouchers/Show')
                ->has('timeline')
                ->has('actionHistory')
        );
    }

    public function test_completed_transaction_shows_all_steps_completed(): void
    {
        $setup = $this->createTimelineScenario();

        // Mark transaction as completed
        $setup['transaction']->update([
            'status' => 'Completed',
            'current_step_id' => null,
            'current_office_id' => null,
        ]);

        $service = app(TimelineService::class);
        $timeline = $service->getTimeline($setup['transaction']->fresh());

        $this->assertEquals(3, $timeline['completed_steps']);
        $this->assertEquals(100, $timeline['progress_percentage']);

        foreach ($timeline['steps'] as $step) {
            $this->assertEquals('completed', $step['status']);
        }
    }

    public function test_timeline_progress_percentage_calculation(): void
    {
        $office = Office::factory()->create();
        $workflow = Workflow::factory()->create(['category' => 'PR', 'is_active' => true]);

        // Create 4 steps
        for ($i = 1; $i <= 4; $i++) {
            WorkflowStep::factory()->create([
                'workflow_id' => $workflow->id,
                'office_id' => Office::factory()->create()->id,
                'step_order' => $i,
                'expected_days' => 2,
                'is_final_step' => $i === 4,
            ]);
        }

        $step3 = WorkflowStep::where('workflow_id', $workflow->id)->where('step_order', 3)->first();

        $procurement = Procurement::factory()->create();
        $transaction = Transaction::factory()->create([
            'procurement_id' => $procurement->id,
            'category' => 'PR',
            'status' => 'In Progress',
            'workflow_id' => $workflow->id,
            'current_step_id' => $step3->id,
            'current_office_id' => $step3->office_id,
            'received_at' => now(),
        ]);

        $service = app(TimelineService::class);
        $timeline = $service->getTimeline($transaction);

        // 2 completed out of 4 = 50%
        $this->assertEquals(2, $timeline['completed_steps']);
        $this->assertEquals(4, $timeline['total_steps']);
        $this->assertEquals(50, $timeline['progress_percentage']);
    }
}
