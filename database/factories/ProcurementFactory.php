<?php

namespace Database\Factories;

use App\Models\Office;
use App\Models\Particular;
use App\Models\Procurement;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Procurement>
 */
class ProcurementFactory extends Factory
{
    protected $model = Procurement::class;

    public function definition(): array
    {
        return [
            'end_user_id' => Office::factory(),
            'particular_id' => Particular::factory(),
            'purpose' => $this->faker->sentence(12),
            'abc_amount' => $this->faker->randomFloat(2, 1000, 500000),
            'date_of_entry' => $this->faker->dateTimeBetween('-60 days', 'now')->format('Y-m-d'),
            'status' => Procurement::STATUS_CREATED,
            'created_by_user_id' => User::factory(),
        ];
    }

    public function inProgress(): static
    {
        return $this->state(fn () => ['status' => Procurement::STATUS_IN_PROGRESS]);
    }

    public function completed(): static
    {
        return $this->state(fn () => ['status' => Procurement::STATUS_COMPLETED]);
    }
}
