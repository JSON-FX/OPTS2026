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
     * Story 2.6/2.7 - Purchase Order with manual reference numbers and supplier address snapshot.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'transaction_id' => Transaction::factory(),
            'supplier_id' => \App\Models\Supplier::factory(),
            'supplier_address' => fake()->address(),
            'contract_price' => fake()->randomFloat(2, 1000, 100000),
        ];
    }
}
