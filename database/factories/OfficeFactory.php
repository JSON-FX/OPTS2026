<?php

namespace Database\Factories;

use App\Models\Office;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Office>
 */
class OfficeFactory extends Factory
{
    protected $model = Office::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->company . ' Office',
            'type' => $this->faker->randomElement(['Administrative', 'Executive', 'Financial']),
            'abbreviation' => strtoupper($this->faker->lexify('???')),
            'is_active' => true,
        ];
    }
}
