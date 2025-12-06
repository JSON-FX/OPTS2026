<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\User;
use App\Models\Workflow;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Workflow>
 */
class WorkflowFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<Workflow>
     */
    protected $model = Workflow::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $category = $this->faker->randomElement(['PR', 'PO', 'VCH']);

        return [
            'category' => $category,
            'name' => $this->getWorkflowName($category),
            'description' => $this->faker->optional(0.7)->sentence(),
            'is_active' => true,
            'created_by_user_id' => null,
        ];
    }

    /**
     * Generate a realistic workflow name based on category.
     */
    private function getWorkflowName(string $category): string
    {
        $names = [
            'PR' => [
                'Standard Purchase Request',
                'Emergency Purchase Request',
                'Direct Purchase Request',
                'Procurement Request Workflow',
            ],
            'PO' => [
                'Standard Purchase Order',
                'Emergency Purchase Order',
                'Bulk Purchase Order',
                'Purchase Order Workflow',
            ],
            'VCH' => [
                'Standard Voucher',
                'Petty Cash Voucher',
                'Reimbursement Voucher',
                'Voucher Processing Workflow',
            ],
        ];

        return $this->faker->randomElement($names[$category] ?? ['Default Workflow']);
    }

    /**
     * Indicate the workflow is for Purchase Requests.
     */
    public function pr(): static
    {
        return $this->state(fn (array $attributes) => [
            'category' => 'PR',
            'name' => $this->getWorkflowName('PR'),
        ]);
    }

    /**
     * Indicate the workflow is for Purchase Orders.
     */
    public function po(): static
    {
        return $this->state(fn (array $attributes) => [
            'category' => 'PO',
            'name' => $this->getWorkflowName('PO'),
        ]);
    }

    /**
     * Indicate the workflow is for Vouchers.
     */
    public function vch(): static
    {
        return $this->state(fn (array $attributes) => [
            'category' => 'VCH',
            'name' => $this->getWorkflowName('VCH'),
        ]);
    }

    /**
     * Indicate the workflow is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Indicate the workflow was created by a specific user.
     */
    public function createdBy(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'created_by_user_id' => $user->id,
        ]);
    }
}
