<?php

namespace Database\Factories;

use App\Models\Particular;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Particular>
 */
class ParticularFactory extends Factory
{
    protected $model = Particular::class;

    public function definition(): array
    {
        return [
            'description' => $this->faker->sentence(4),
            'is_active' => true,
        ];
    }
}
