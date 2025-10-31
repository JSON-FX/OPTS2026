<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\PurchaseOrder;
use App\Models\Transaction;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PurchaseOrder>
 */
class PurchaseOrderFactory extends Factory
{
    protected $model = PurchaseOrder::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'transaction_id' => Transaction::factory(),
            'supplier_id' => \App\Models\Supplier::factory(),
            'supplier_address' => fake()->address(),
            'purchase_request_id' => \App\Models\PurchaseRequest::factory(),
            'particulars' => fake()->sentence(),
            'fund_type_id' => \App\Models\FundType::factory(),
            'total_cost' => fake()->randomFloat(2, 1000, 100000),
            'date_of_po' => fake()->date(),
            'delivery_date' => fake()->optional()->date(),
            'delivery_term' => fake()->optional()->numberBetween(1, 90),
            'payment_term' => fake()->optional()->numberBetween(1, 90),
            'amount_in_words' => fake()->words(5, true),
            'mode_of_procurement' => fake()->randomElement(['Public Bidding', 'Shopping', 'Direct Contracting']),
        ];
    }
}
