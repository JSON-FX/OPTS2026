<?php

declare(strict_types=1);

namespace Tests\Feature\Migrations;

use App\Models\Office;
use App\Models\Workflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class WorkflowStepsTableTest extends TestCase
{
    use RefreshDatabase;

    public function test_workflow_steps_table_exists(): void
    {
        $this->assertTrue(Schema::hasTable('workflow_steps'));
    }

    public function test_workflow_steps_table_has_required_columns(): void
    {
        $this->assertTrue(Schema::hasColumn('workflow_steps', 'id'));
        $this->assertTrue(Schema::hasColumn('workflow_steps', 'workflow_id'));
        $this->assertTrue(Schema::hasColumn('workflow_steps', 'office_id'));
        $this->assertTrue(Schema::hasColumn('workflow_steps', 'step_order'));
        $this->assertTrue(Schema::hasColumn('workflow_steps', 'expected_days'));
        $this->assertTrue(Schema::hasColumn('workflow_steps', 'is_final_step'));
        $this->assertTrue(Schema::hasColumn('workflow_steps', 'created_at'));
        $this->assertTrue(Schema::hasColumn('workflow_steps', 'updated_at'));
    }

    public function test_workflows_table_has_new_columns(): void
    {
        $this->assertTrue(Schema::hasColumn('workflows', 'description'));
        $this->assertTrue(Schema::hasColumn('workflows', 'created_by_user_id'));
    }

    public function test_workflow_can_have_multiple_steps(): void
    {
        $workflow = Workflow::factory()->create();
        $offices = Office::factory()->count(5)->create();

        foreach ($offices as $index => $office) {
            $workflow->steps()->create([
                'office_id' => $office->id,
                'step_order' => $index + 1,
                'expected_days' => rand(1, 5),
                'is_final_step' => $index === 4,
            ]);
        }

        $this->assertCount(5, $workflow->steps);
    }

    public function test_cascade_delete_removes_steps_when_workflow_deleted(): void
    {
        $workflow = Workflow::factory()->create();
        $offices = Office::factory()->count(3)->create();

        foreach ($offices as $index => $office) {
            $workflow->steps()->create([
                'office_id' => $office->id,
                'step_order' => $index + 1,
                'expected_days' => 2,
                'is_final_step' => $index === 2,
            ]);
        }

        $workflowId = $workflow->id;

        $this->assertDatabaseCount('workflow_steps', 3);

        $workflow->delete();

        $this->assertDatabaseMissing('workflows', ['id' => $workflowId]);
        $this->assertDatabaseCount('workflow_steps', 0);
    }

    public function test_restrict_delete_prevents_office_deletion_when_used_in_step(): void
    {
        $workflow = Workflow::factory()->create();
        $office = Office::factory()->create();

        $workflow->steps()->create([
            'office_id' => $office->id,
            'step_order' => 1,
            'expected_days' => 2,
            'is_final_step' => true,
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        // Force delete to bypass soft deletes and trigger FK constraint
        $office->forceDelete();
    }

    public function test_workflow_description_can_be_null(): void
    {
        $workflow = Workflow::create([
            'category' => 'PR',
            'name' => 'Test Workflow',
            'description' => null,
            'is_active' => true,
        ]);

        $this->assertNull($workflow->description);
        $this->assertDatabaseHas('workflows', [
            'id' => $workflow->id,
            'description' => null,
        ]);
    }

    public function test_workflow_created_by_user_id_can_be_null(): void
    {
        $workflow = Workflow::create([
            'category' => 'PR',
            'name' => 'Test Workflow',
            'is_active' => true,
            'created_by_user_id' => null,
        ]);

        $this->assertNull($workflow->created_by_user_id);
    }
}
