<?php

declare(strict_types=1);

namespace Tests\Feature\Models;

use App\Models\Office;
use App\Models\Workflow;
use App\Models\WorkflowStep;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkflowStepTest extends TestCase
{
    use RefreshDatabase;

    public function test_workflow_step_can_be_created_with_fillable_attributes(): void
    {
        $workflow = Workflow::factory()->create();
        $office = Office::factory()->create();

        $step = WorkflowStep::create([
            'workflow_id' => $workflow->id,
            'office_id' => $office->id,
            'step_order' => 1,
            'expected_days' => 3,
            'is_final_step' => false,
        ]);

        $this->assertDatabaseHas('workflow_steps', [
            'id' => $step->id,
            'workflow_id' => $workflow->id,
            'office_id' => $office->id,
            'step_order' => 1,
            'expected_days' => 3,
            'is_final_step' => false,
        ]);
    }

    public function test_workflow_step_belongs_to_workflow(): void
    {
        $workflow = Workflow::factory()->create();
        $step = WorkflowStep::factory()->forWorkflow($workflow)->create();

        $this->assertInstanceOf(Workflow::class, $step->workflow);
        $this->assertEquals($workflow->id, $step->workflow->id);
    }

    public function test_workflow_step_belongs_to_office(): void
    {
        $office = Office::factory()->create();
        $step = WorkflowStep::factory()->forOffice($office)->create();

        $this->assertInstanceOf(Office::class, $step->office);
        $this->assertEquals($office->id, $step->office->id);
    }

    public function test_scope_ordered_returns_steps_by_step_order(): void
    {
        $workflow = Workflow::factory()->create();
        $offices = Office::factory()->count(3)->create();

        // Create in reverse order
        WorkflowStep::factory()->forWorkflow($workflow)->forOffice($offices[2])->order(3)->create();
        WorkflowStep::factory()->forWorkflow($workflow)->forOffice($offices[0])->order(1)->create();
        WorkflowStep::factory()->forWorkflow($workflow)->forOffice($offices[1])->order(2)->create();

        $steps = WorkflowStep::where('workflow_id', $workflow->id)->ordered()->get();

        $this->assertEquals(1, $steps[0]->step_order);
        $this->assertEquals(2, $steps[1]->step_order);
        $this->assertEquals(3, $steps[2]->step_order);
    }

    public function test_is_first_step_attribute_returns_true_for_step_order_one(): void
    {
        $step = WorkflowStep::factory()->order(1)->create();

        $this->assertTrue($step->is_first_step);
    }

    public function test_is_first_step_attribute_returns_false_for_other_orders(): void
    {
        $step = WorkflowStep::factory()->order(2)->create();

        $this->assertFalse($step->is_first_step);
    }

    public function test_get_next_step_returns_subsequent_step(): void
    {
        $workflow = Workflow::factory()->create();
        $offices = Office::factory()->count(3)->create();

        $step1 = WorkflowStep::factory()->forWorkflow($workflow)->forOffice($offices[0])->order(1)->create();
        $step2 = WorkflowStep::factory()->forWorkflow($workflow)->forOffice($offices[1])->order(2)->create();
        WorkflowStep::factory()->forWorkflow($workflow)->forOffice($offices[2])->order(3)->create();

        $result = $step1->getNextStep();

        $this->assertInstanceOf(WorkflowStep::class, $result);
        $this->assertEquals($step2->id, $result->id);
    }

    public function test_get_next_step_returns_null_at_end(): void
    {
        $workflow = Workflow::factory()->create();
        $office = Office::factory()->create();

        $finalStep = WorkflowStep::factory()->forWorkflow($workflow)->forOffice($office)->order(1)->final()->create();

        $result = $finalStep->getNextStep();

        $this->assertNull($result);
    }

    public function test_get_previous_step_returns_prior_step(): void
    {
        $workflow = Workflow::factory()->create();
        $offices = Office::factory()->count(3)->create();

        $step1 = WorkflowStep::factory()->forWorkflow($workflow)->forOffice($offices[0])->order(1)->create();
        $step2 = WorkflowStep::factory()->forWorkflow($workflow)->forOffice($offices[1])->order(2)->create();
        WorkflowStep::factory()->forWorkflow($workflow)->forOffice($offices[2])->order(3)->create();

        $result = $step2->getPreviousStep();

        $this->assertInstanceOf(WorkflowStep::class, $result);
        $this->assertEquals($step1->id, $result->id);
    }

    public function test_get_previous_step_returns_null_for_first_step(): void
    {
        $step = WorkflowStep::factory()->order(1)->create();

        $result = $step->getPreviousStep();

        $this->assertNull($result);
    }

    public function test_unique_constraint_on_workflow_and_step_order(): void
    {
        $workflow = Workflow::factory()->create();
        $offices = Office::factory()->count(2)->create();

        WorkflowStep::factory()->forWorkflow($workflow)->forOffice($offices[0])->order(1)->create();

        $this->expectException(QueryException::class);

        WorkflowStep::factory()->forWorkflow($workflow)->forOffice($offices[1])->order(1)->create();
    }

    public function test_same_office_can_appear_multiple_times_in_workflow(): void
    {
        $workflow = Workflow::factory()->create();
        $office = Office::factory()->create();

        $step1 = WorkflowStep::factory()->forWorkflow($workflow)->forOffice($office)->order(1)->create();
        $step2 = WorkflowStep::factory()->forWorkflow($workflow)->forOffice($office)->order(2)->create();

        $this->assertDatabaseCount('workflow_steps', 2);
        $this->assertEquals($office->id, $step1->office_id);
        $this->assertEquals($office->id, $step2->office_id);
        $this->assertEquals($workflow->id, $step1->workflow_id);
        $this->assertEquals($workflow->id, $step2->workflow_id);
    }

    public function test_workflow_step_factory_generates_valid_data(): void
    {
        $step = WorkflowStep::factory()->create();

        $this->assertNotNull($step->id);
        $this->assertNotNull($step->workflow_id);
        $this->assertNotNull($step->office_id);
        $this->assertGreaterThanOrEqual(1, $step->step_order);
        $this->assertGreaterThanOrEqual(1, $step->expected_days);
        $this->assertLessThanOrEqual(5, $step->expected_days);
        $this->assertIsBool($step->is_final_step);
    }

    public function test_casts_are_applied_correctly(): void
    {
        $step = WorkflowStep::factory()->create([
            'step_order' => '2',
            'expected_days' => '3',
            'is_final_step' => '1',
        ]);

        $this->assertIsInt($step->step_order);
        $this->assertIsInt($step->expected_days);
        $this->assertIsBool($step->is_final_step);
    }
}
