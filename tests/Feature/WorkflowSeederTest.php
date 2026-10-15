<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Office;
use App\Models\Workflow;
use Database\Seeders\ActionTakenSeeder;
use Database\Seeders\WorkflowSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkflowSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeded_workflows_have_contiguous_office_steps_and_a_final_step(): void
    {
        foreach (['MBO', 'MMO', 'MMO-BAC', 'MMO-PO', 'MMO-PSMD', 'MACCO', 'MTO'] as $abbreviation) {
            Office::factory()->create(['abbreviation' => $abbreviation]);
        }
        $this->seed([ActionTakenSeeder::class, WorkflowSeeder::class]);

        foreach (['PR' => 5, 'PO' => 3, 'VCH' => 6] as $category => $count) {
            $workflow = Workflow::where('category', $category)->firstOrFail();
            $steps = $workflow->steps()->ordered()->with('office')->get();

            $this->assertCount($count, $steps);
            $this->assertSame(range(1, $count), $steps->pluck('step_order')->all());
            $this->assertTrue($steps->every(fn ($step) => $step->office !== null));
            $this->assertSame(1, $steps->where('is_final_step', true)->count());
            $this->assertTrue($steps->last()->is_final_step);
        }

        $this->assertSame(
            ['MBO', 'MMO', 'MMO-BAC', 'MMO-PO', 'MMO-BAC'],
            Workflow::where('category', 'PR')->firstOrFail()->steps()->ordered()->with('office')->get()->pluck('office.abbreviation')->all()
        );
        $this->assertSame(
            ['MMO-BAC', 'MMO-PO', 'MMO-PSMD'],
            Workflow::where('category', 'PO')->firstOrFail()->steps()->ordered()->with('office')->get()->pluck('office.abbreviation')->all()
        );
    }
}
