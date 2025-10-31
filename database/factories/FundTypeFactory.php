<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\FundType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FundType>
 */
class FundTypeFactory extends Factory
{
    protected $model = FundType::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true),
            'abbreviation' => strtoupper(fake()->lexify('???')),
            'is_active' => true,
        ];
    }
}
