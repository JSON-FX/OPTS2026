<?php

declare(strict_types=1);

namespace Tests\Feature\Models;

use App\Models\Office;
use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowStep;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_workflow_can_be_created_with_fillable_attributes(): void
    {
        $workflow = Workflow::create([
            'category' => 'PR',
            'name' => 'Test Workflow',
            'description' => 'A test workflow',
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('workflows', [
            'id' => $workflow->id,
            'category' => 'PR',
            'name' => 'Test Workflow',
            'description' => 'A test workflow',
            'is_active' => true,
        ]);
    }

    public function test_workflow_has_many_steps_relationship(): void
    {
        $workflow = Workflow::factory()->create();
        $offices = Office::factory()->count(3)->create();

        foreach ($offices as $index => $office) {
            WorkflowStep::factory()
                ->forWorkflow($workflow)
                ->forOffice($office)
                ->order($index + 1)
                ->create();
        }

        $this->assertCount(3, $workflow->steps);
        $this->assertInstanceOf(WorkflowStep::class, $workflow->steps->first());
    }

    public function test_workflow_steps_are_ordered_by_step_order(): void
    {
        $workflow = Workflow::factory()->create();
        $offices = Office::factory()->count(3)->create();

        // Create steps in reverse order
        WorkflowStep::factory()->forWorkflow($workflow)->forOffice($offices[2])->order(3)->create();
        WorkflowStep::factory()->forWorkflow($workflow)->forOffice($offices[0])->order(1)->create();
        WorkflowStep::factory()->forWorkflow($workflow)->forOffice($offices[1])->order(2)->create();

        $steps = $workflow->steps;

        $this->assertEquals(1, $steps[0]->step_order);
        $this->assertEquals(2, $steps[1]->step_order);
        $this->assertEquals(3, $steps[2]->step_order);
    }

    public function test_workflow_belongs_to_created_by_user(): void
    {
        $user = User::factory()->create();
        $workflow = Workflow::factory()->createdBy($user)->create();

        $this->assertInstanceOf(User::class, $workflow->createdBy);
        $this->assertEquals($user->id, $workflow->createdBy->id);
    }

    public function test_scope_active_filters_active_workflows(): void
    {
        Workflow::factory()->count(3)->create(['is_active' => true]);
        Workflow::factory()->count(2)->inactive()->create();

        $activeWorkflows = Workflow::active()->get();

        $this->assertCount(3, $activeWorkflows);
    }

    public function test_scope_for_category_filters_by_category(): void
    {
        Workflow::factory()->pr()->create();
        Workflow::factory()->pr()->create();
        Workflow::factory()->po()->create();
        Workflow::factory()->vch()->create();

        $prWorkflows = Workflow::forCategory('PR')->get();

        $this->assertCount(2, $prWorkflows);
        $prWorkflows->each(fn ($wf) => $this->assertEquals('PR', $wf->category));
    }

    public function test_get_steps_count_attribute(): void
    {
        $workflow = Workflow::factory()->create();
        $offices = Office::factory()->count(5)->create();

        foreach ($offices as $index => $office) {
            WorkflowStep::factory()
                ->forWorkflow($workflow)
                ->forOffice($office)
                ->order($index + 1)
                ->create();
        }

        $this->assertEquals(5, $workflow->steps_count);
    }

    public function test_get_first_step_returns_step_with_order_one(): void
    {
        $workflow = Workflow::factory()->create();
        $offices = Office::factory()->count(3)->create();

        $firstStep = WorkflowStep::factory()->forWorkflow($workflow)->forOffice($offices[0])->order(1)->create();
        WorkflowStep::factory()->forWorkflow($workflow)->forOffice($offices[1])->order(2)->create();
        WorkflowStep::factory()->forWorkflow($workflow)->forOffice($offices[2])->order(3)->create();

        $result = $workflow->getFirstStep();

        $this->assertInstanceOf(WorkflowStep::class, $result);
        $this->assertEquals($firstStep->id, $result->id);
        $this->assertEquals(1, $result->step_order);
    }

    public function test_get_last_step_returns_final_step(): void
    {
        $workflow = Workflow::factory()->create();
        $offices = Office::factory()->count(3)->create();

        WorkflowStep::factory()->forWorkflow($workflow)->forOffice($offices[0])->order(1)->create();
        WorkflowStep::factory()->forWorkflow($workflow)->forOffice($offices[1])->order(2)->create();
        $lastStep = WorkflowStep::factory()->forWorkflow($workflow)->forOffice($offices[2])->order(3)->final()->create();

        $result = $workflow->getLastStep();

        $this->assertInstanceOf(WorkflowStep::class, $result);
        $this->assertEquals($lastStep->id, $result->id);
        $this->assertTrue($result->is_final_step);
    }

    public function test_get_step_by_order_returns_correct_step(): void
    {
        $workflow = Workflow::factory()->create();
        $offices = Office::factory()->count(3)->create();

        WorkflowStep::factory()->forWorkflow($workflow)->forOffice($offices[0])->order(1)->create();
        $secondStep = WorkflowStep::factory()->forWorkflow($workflow)->forOffice($offices[1])->order(2)->create();
        WorkflowStep::factory()->forWorkflow($workflow)->forOffice($offices[2])->order(3)->create();

        $result = $workflow->getStepByOrder(2);

        $this->assertInstanceOf(WorkflowStep::class, $result);
        $this->assertEquals($secondStep->id, $result->id);
    }

    public function test_get_next_step_returns_subsequent_step(): void
    {
        $workflow = Workflow::factory()->create();
        $offices = Office::factory()->count(3)->create();

        WorkflowStep::factory()->forWorkflow($workflow)->forOffice($offices[0])->order(1)->create();
        $secondStep = WorkflowStep::factory()->forWorkflow($workflow)->forOffice($offices[1])->order(2)->create();
        WorkflowStep::factory()->forWorkflow($workflow)->forOffice($offices[2])->order(3)->create();

        $result = $workflow->getNextStep(1);

        $this->assertInstanceOf(WorkflowStep::class, $result);
        $this->assertEquals($secondStep->id, $result->id);
    }

    public function test_get_next_step_returns_null_at_end(): void
    {
        $workflow = Workflow::factory()->create();
        $office = Office::factory()->create();

        WorkflowStep::factory()->forWorkflow($workflow)->forOffice($office)->order(1)->final()->create();

        $result = $workflow->getNextStep(1);

        $this->assertNull($result);
    }

    public function test_deleting_workflow_cascades_to_steps(): void
    {
        $workflow = Workflow::factory()->create();
        $offices = Office::factory()->count(3)->create();

        foreach ($offices as $index => $office) {
            WorkflowStep::factory()
                ->forWorkflow($workflow)
                ->forOffice($office)
                ->order($index + 1)
                ->create();
        }

        $this->assertDatabaseCount('workflow_steps', 3);

        $workflow->delete();

        $this->assertDatabaseCount('workflow_steps', 0);
    }

    public function test_workflow_factory_generates_valid_data(): void
    {
        $workflow = Workflow::factory()->create();

        $this->assertNotNull($workflow->id);
        $this->assertContains($workflow->category, ['PR', 'PO', 'VCH']);
        $this->assertNotEmpty($workflow->name);
        $this->assertIsBool($workflow->is_active);
    }
}
