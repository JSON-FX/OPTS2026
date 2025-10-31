<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Procurement;
use App\Models\Transaction;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Transaction>
 */
class TransactionFactory extends Factory
{
    protected $model = Transaction::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'procurement_id' => Procurement::factory(),
            'category' => fake()->randomElement(['PR', 'PO', 'VCH']),
            'reference_number' => strtoupper(fake()->randomElement(['PR', 'PO', 'VCH'])).'-'.now()->year.'-'.str_pad((string) fake()->unique()->numberBetween(1, 999999), 6, '0', STR_PAD_LEFT),
            'is_continuation' => false,
            'status' => 'Created',
            'workflow_id' => null,
            'current_office_id' => null,
            'current_user_id' => null,
            'created_by_user_id' => \App\Models\User::factory(),
        ];
    }
}
