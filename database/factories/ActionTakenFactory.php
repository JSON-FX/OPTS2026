<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ActionTaken;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Factory for ActionTaken model.
 *
 * @extends Factory<ActionTaken>
 */
class ActionTakenFactory extends Factory
{
    protected $model = ActionTaken::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'description' => $this->faker->unique()->sentence(3),
            'is_active' => true,
        ];
    }

    /**
     * State for inactive action taken.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
