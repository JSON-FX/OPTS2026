<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ActionTaken;
use App\Models\Office;
use App\Models\Transaction;
use App\Models\TransactionAction;
use App\Models\User;
use App\Models\WorkflowStep;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Factory for TransactionAction model.
 *
 * Story 3.3 - Transaction Actions Schema & Models
 *
 * @extends Factory<TransactionAction>
 */
class TransactionActionFactory extends Factory
{
    protected $model = TransactionAction::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'transaction_id' => Transaction::factory(),
            'action_type' => $this->faker->randomElement(TransactionAction::TYPES),
            'action_taken_id' => null,
            'from_office_id' => Office::factory(),
            'to_office_id' => Office::factory(),
            'from_user_id' => User::factory(),
            'to_user_id' => null,
            'workflow_step_id' => null,
            'is_out_of_workflow' => false,
            'notes' => $this->faker->optional(0.3)->sentence(),
            'reason' => null,
            'ip_address' => $this->faker->ipv4(),
        ];
    }

    /**
     * State for endorse action.
     */
    public function endorse(): static
    {
        return $this->state(fn (array $attributes) => [
            'action_type' => TransactionAction::TYPE_ENDORSE,
            'to_office_id' => Office::factory(),
            'to_user_id' => null,
            'reason' => null,
        ]);
    }

    /**
     * State for receive action.
     */
    public function receive(): static
    {
        return $this->state(fn (array $attributes) => [
            'action_type' => TransactionAction::TYPE_RECEIVE,
            'to_user_id' => User::factory(),
            'reason' => null,
        ]);
    }

    /**
     * State for complete action.
     */
    public function complete(): static
    {
        return $this->state(fn (array $attributes) => [
            'action_type' => TransactionAction::TYPE_COMPLETE,
            'to_office_id' => null,
            'to_user_id' => null,
            'reason' => null,
        ]);
    }

    /**
     * State for hold action.
     */
    public function hold(): static
    {
        return $this->state(fn (array $attributes) => [
            'action_type' => TransactionAction::TYPE_HOLD,
            'to_office_id' => null,
            'to_user_id' => null,
            'reason' => $this->faker->sentence(),
        ]);
    }

    /**
     * State for cancel action.
     */
    public function cancel(): static
    {
        return $this->state(fn (array $attributes) => [
            'action_type' => TransactionAction::TYPE_CANCEL,
            'to_office_id' => null,
            'to_user_id' => null,
            'reason' => $this->faker->sentence(),
        ]);
    }

    /**
     * State for bypass action (admin redirect).
     */
    public function bypass(): static
    {
        return $this->state(fn (array $attributes) => [
            'action_type' => TransactionAction::TYPE_BYPASS,
            'to_office_id' => Office::factory(),
            'to_user_id' => null,
            'reason' => $this->faker->sentence(),
            'is_out_of_workflow' => true,
        ]);
    }

    /**
     * State for out-of-workflow action.
     */
    public function outOfWorkflow(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_out_of_workflow' => true,
        ]);
    }

    /**
     * State with action taken reference.
     */
    public function withActionTaken(): static
    {
        return $this->state(fn (array $attributes) => [
            'action_taken_id' => ActionTaken::factory(),
        ]);
    }

    /**
     * State with workflow step reference.
     */
    public function withWorkflowStep(): static
    {
        return $this->state(fn (array $attributes) => [
            'workflow_step_id' => WorkflowStep::factory(),
        ]);
    }

    /**
     * State for specific transaction.
     */
    public function forTransaction(Transaction $transaction): static
    {
        return $this->state(fn (array $attributes) => [
            'transaction_id' => $transaction->id,
        ]);
    }
}
