<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Exceptions\NoActiveWorkflowException;
use App\Models\Office;
use App\Models\Procurement;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowStep;
use App\Services\WorkflowAssignmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Unit tests for WorkflowAssignmentService.
 *
 * Story 3.11 - Workflow Assignment on Transaction Creation
 */
class WorkflowAssignmentServiceTest extends TestCase
{
    use RefreshDatabase;

    private WorkflowAssignmentService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RoleSeeder::class);
        $this->service = app(WorkflowAssignmentService::class);
    }

    /**
     * Create a standard test setup with a workflow, steps, and user.
     *
     * @return array{workflow: Workflow, step1: WorkflowStep, step2: WorkflowStep, office1: Office, office2: Office, user: User, transaction: Transaction}
     */
    protected function createTestSetup(string $category = 'PR'): array
    {
        $office1 = Office::factory()->create(['is_active' => true]);
        $office2 = Office::factory()->create(['is_active' => true]);

        $workflow = Workflow::factory()->create([
            'category' => $category,
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
            'expected_days' => 5,
            'is_final_step' => true,
        ]);

        $user = User::factory()->create(['office_id' => $office1->id]);
        $user->assignRole('Endorser');

        $procurement = Procurement::factory()->create();
        $transaction = Transaction::factory()->create([
            'procurement_id' => $procurement->id,
            'category' => $category,
            'status' => 'Created',
        ]);

        return [
            'workflow' => $workflow,
            'step1' => $step1,
            'step2' => $step2,
            'office1' => $office1,
            'office2' => $office2,
            'user' => $user,
            'transaction' => $transaction,
        ];
    }

    // getActiveWorkflow Tests

    public function test_get_active_workflow_returns_correct_workflow(): void
    {
        $setup = $this->createTestSetup();

        $result = $this->service->getActiveWorkflow('PR');

        $this->assertNotNull($result);
        $this->assertEquals($setup['workflow']->id, $result->id);
        $this->assertEquals('PR', $result->category);
        $this->assertTrue($result->is_active);
    }

    public function test_get_active_workflow_returns_newest_when_multiple_active(): void
    {
        $office = Office::factory()->create(['is_active' => true]);

        $olderWorkflow = Workflow::factory()->create([
            'category' => 'PR',
            'is_active' => true,
            'created_at' => now()->subDay(),
        ]);
        WorkflowStep::factory()->create([
            'workflow_id' => $olderWorkflow->id,
            'office_id' => $office->id,
            'step_order' => 1,
            'is_final_step' => true,
        ]);

        $newerWorkflow = Workflow::factory()->create([
            'category' => 'PR',
            'is_active' => true,
            'created_at' => now(),
        ]);
        WorkflowStep::factory()->create([
            'workflow_id' => $newerWorkflow->id,
            'office_id' => $office->id,
            'step_order' => 1,
            'is_final_step' => true,
        ]);

        $result = $this->service->getActiveWorkflow('PR');

        $this->assertNotNull($result);
        $this->assertEquals($newerWorkflow->id, $result->id);
    }

    public function test_get_active_workflow_returns_null_when_none_active(): void
    {
        // Create an inactive workflow
        Workflow::factory()->create([
            'category' => 'PR',
            'is_active' => false,
        ]);

        $result = $this->service->getActiveWorkflow('PR');

        $this->assertNull($result);
    }

    public function test_get_active_workflow_returns_null_for_different_category(): void
    {
        $this->createTestSetup('PR');

        $result = $this->service->getActiveWorkflow('PO');

        $this->assertNull($result);
    }

    // hasActiveWorkflow Tests

    public function test_has_active_workflow_returns_true_when_exists(): void
    {
        $this->createTestSetup('PR');

        $result = $this->service->hasActiveWorkflow('PR');

        $this->assertTrue($result);
    }

    public function test_has_active_workflow_returns_false_when_none(): void
    {
        $result = $this->service->hasActiveWorkflow('PR');

        $this->assertFalse($result);
    }

    public function test_has_active_workflow_returns_false_for_inactive(): void
    {
        Workflow::factory()->create([
            'category' => 'PR',
            'is_active' => false,
        ]);

        $result = $this->service->hasActiveWorkflow('PR');

        $this->assertFalse($result);
    }

    // assignWorkflow Tests

    public function test_assign_workflow_sets_all_fields(): void
    {
        $setup = $this->createTestSetup();

        $this->service->assignWorkflow($setup['transaction'], $setup['user']);

        $setup['transaction']->refresh();

        $this->assertEquals($setup['workflow']->id, $setup['transaction']->workflow_id);
        $this->assertEquals($setup['step1']->id, $setup['transaction']->current_step_id);
        $this->assertEquals($setup['user']->office_id, $setup['transaction']->current_office_id);
        $this->assertEquals($setup['user']->id, $setup['transaction']->current_user_id);
        $this->assertNotNull($setup['transaction']->received_at);
    }

    public function test_assign_workflow_throws_no_active_workflow_exception(): void
    {
        $procurement = Procurement::factory()->create();
        $transaction = Transaction::factory()->create([
            'procurement_id' => $procurement->id,
            'category' => 'PR',
            'status' => 'Created',
        ]);

        $user = User::factory()->create();
        $user->assignRole('Endorser');

        $this->expectException(NoActiveWorkflowException::class);
        $this->expectExceptionMessage('No active workflow found for category: PR');

        $this->service->assignWorkflow($transaction, $user);
    }

    public function test_assign_workflow_sets_first_step_as_current(): void
    {
        $setup = $this->createTestSetup();

        $this->service->assignWorkflow($setup['transaction'], $setup['user']);

        $setup['transaction']->refresh();

        // Should be step 1 (step_order = 1), not step 2
        $this->assertEquals($setup['step1']->id, $setup['transaction']->current_step_id);
        $this->assertNotEquals($setup['step2']->id, $setup['transaction']->current_step_id);
    }

    // getWorkflowPreview Tests

    public function test_get_workflow_preview_returns_correct_structure(): void
    {
        $setup = $this->createTestSetup();

        $result = $this->service->getWorkflowPreview('PR');

        $this->assertNotNull($result);
        $this->assertArrayHasKey('workflow_id', $result);
        $this->assertArrayHasKey('workflow_name', $result);
        $this->assertArrayHasKey('total_steps', $result);
        $this->assertArrayHasKey('total_expected_days', $result);
        $this->assertArrayHasKey('steps', $result);

        $this->assertEquals($setup['workflow']->id, $result['workflow_id']);
        $this->assertEquals($setup['workflow']->name, $result['workflow_name']);
        $this->assertEquals(2, $result['total_steps']);
        $this->assertEquals(8, $result['total_expected_days']); // 3 + 5
        $this->assertCount(2, $result['steps']);
    }

    public function test_get_workflow_preview_returns_null_when_no_active_workflow(): void
    {
        $result = $this->service->getWorkflowPreview('PR');

        $this->assertNull($result);
    }

    public function test_get_workflow_preview_step_details_are_correct(): void
    {
        $setup = $this->createTestSetup();

        $result = $this->service->getWorkflowPreview('PR');

        $firstStep = $result['steps'][0];
        $this->assertEquals(1, $firstStep['step_order']);
        $this->assertEquals($setup['office1']->name, $firstStep['office_name']);
        $this->assertEquals($setup['office1']->abbreviation, $firstStep['office_abbreviation']);
        $this->assertEquals(3, $firstStep['expected_days']);
        $this->assertFalse($firstStep['is_final_step']);

        $lastStep = $result['steps'][1];
        $this->assertEquals(2, $lastStep['step_order']);
        $this->assertEquals($setup['office2']->name, $lastStep['office_name']);
        $this->assertEquals($setup['office2']->abbreviation, $lastStep['office_abbreviation']);
        $this->assertEquals(5, $lastStep['expected_days']);
        $this->assertTrue($lastStep['is_final_step']);
    }
}
