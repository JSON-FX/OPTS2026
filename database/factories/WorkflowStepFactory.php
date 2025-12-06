<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Office;
use App\Models\Workflow;
use App\Models\WorkflowStep;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkflowStep>
 */
class WorkflowStepFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<WorkflowStep>
     */
    protected $model = WorkflowStep::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'workflow_id' => Workflow::factory(),
            'office_id' => Office::factory(),
            'step_order' => 1,
            'expected_days' => $this->faker->numberBetween(1, 5),
            'is_final_step' => false,
        ];
    }

    /**
     * Set the step order.
     */
    public function order(int $order): static
    {
        return $this->state(fn (array $attributes) => [
            'step_order' => $order,
        ]);
    }

    /**
     * Set expected days for this step.
     */
    public function expectedDays(int $days): static
    {
        return $this->state(fn (array $attributes) => [
            'expected_days' => $days,
        ]);
    }

    /**
     * Mark this step as the final step.
     */
    public function final(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_final_step' => true,
        ]);
    }

    /**
     * Set the workflow for this step.
     */
    public function forWorkflow(Workflow $workflow): static
    {
        return $this->state(fn (array $attributes) => [
            'workflow_id' => $workflow->id,
        ]);
    }

    /**
     * Set the office for this step.
     */
    public function forOffice(Office $office): static
    {
        return $this->state(fn (array $attributes) => [
            'office_id' => $office->id,
        ]);
    }
}
