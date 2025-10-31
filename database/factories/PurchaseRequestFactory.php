<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\FundType;
use App\Models\PurchaseRequest;
use App\Models\Transaction;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PurchaseRequest>
 */
class PurchaseRequestFactory extends Factory
{
    protected $model = PurchaseRequest::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'transaction_id' => Transaction::factory(),
            'fund_type_id' => FundType::factory(),
        ];
    }
}
