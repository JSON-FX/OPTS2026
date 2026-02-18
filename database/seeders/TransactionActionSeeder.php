<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Office;
use App\Models\Transaction;
use App\Models\TransactionAction;
use App\Models\User;
use App\Models\WorkflowStep;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Seeder for transaction actions demonstrating endorsement chains.
 *
 * Story 3.3 - Transaction Actions Schema & Models
 */
class TransactionActionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // No example transaction action data seeded.
    }

    /**
     * Create a complete endorsement chain ending in complete.
     */
    private function createCompleteChain(
        Transaction $transaction,
        $workflowSteps,
        $usersByOffice,
        array $offices,
        Carbon $actionDate,
        $faker
    ): void {
        $stepCount = min(3, $workflowSteps->count());

        for ($i = 0; $i < $stepCount; $i++) {
            $step = $workflowSteps[$i];
            $nextStep = $workflowSteps[$i + 1] ?? null;

            $fromOfficeId = $step->office_id;
            $toOfficeId = $nextStep?->office_id ?? $fromOfficeId;
            $fromUser = $this->getUserForOffice($usersByOffice, $fromOfficeId, $offices);
            $toUser = $this->getUserForOffice($usersByOffice, $toOfficeId, $offices);

            // Endorse action
            $actionDate = $actionDate->copy()->addHours($faker->numberBetween(2, 24));
            $this->createAction($transaction, [
                'action_type' => TransactionAction::TYPE_ENDORSE,
                'from_office_id' => $fromOfficeId,
                'to_office_id' => $toOfficeId,
                'from_user_id' => $fromUser->id,
                'workflow_step_id' => $step->id,
                'notes' => $faker->optional(0.4)->sentence(),
                'ip_address' => $faker->ipv4(),
                'created_at' => $actionDate,
            ]);

            // Receive action
            $actionDate = $actionDate->copy()->addHours($faker->numberBetween(1, 8));
            $this->createAction($transaction, [
                'action_type' => TransactionAction::TYPE_RECEIVE,
                'from_office_id' => $fromOfficeId,
                'to_office_id' => $toOfficeId,
                'from_user_id' => $toUser->id,
                'to_user_id' => $toUser->id,
                'workflow_step_id' => $nextStep?->id ?? $step->id,
                'ip_address' => $faker->ipv4(),
                'created_at' => $actionDate,
            ]);
        }

        // Complete action
        $lastStep = $workflowSteps->last();
        $finalUser = $this->getUserForOffice($usersByOffice, $lastStep->office_id, $offices);
        $actionDate = $actionDate->copy()->addHours($faker->numberBetween(4, 48));
        $this->createAction($transaction, [
            'action_type' => TransactionAction::TYPE_COMPLETE,
            'from_office_id' => $lastStep->office_id,
            'to_office_id' => null,
            'from_user_id' => $finalUser->id,
            'workflow_step_id' => $lastStep->id,
            'notes' => 'Transaction completed successfully',
            'ip_address' => $faker->ipv4(),
            'created_at' => $actionDate,
        ]);

        // Update transaction tracking columns
        $transaction->update([
            'current_step_id' => $lastStep->id,
            'received_at' => $actionDate->copy()->subHours(2),
            'endorsed_at' => $actionDate,
        ]);
    }

    /**
     * Create a partial endorsement chain (in progress).
     */
    private function createPartialChain(
        Transaction $transaction,
        $workflowSteps,
        $usersByOffice,
        array $offices,
        Carbon $actionDate,
        $faker
    ): void {
        $stepCount = min(2, $workflowSteps->count());

        for ($i = 0; $i < $stepCount; $i++) {
            $step = $workflowSteps[$i];
            $nextStep = $workflowSteps[$i + 1] ?? null;

            $fromOfficeId = $step->office_id;
            $toOfficeId = $nextStep?->office_id ?? $fromOfficeId;
            $fromUser = $this->getUserForOffice($usersByOffice, $fromOfficeId, $offices);

            // Endorse action
            $actionDate = $actionDate->copy()->addHours($faker->numberBetween(2, 24));
            $this->createAction($transaction, [
                'action_type' => TransactionAction::TYPE_ENDORSE,
                'from_office_id' => $fromOfficeId,
                'to_office_id' => $toOfficeId,
                'from_user_id' => $fromUser->id,
                'workflow_step_id' => $step->id,
                'ip_address' => $faker->ipv4(),
                'created_at' => $actionDate,
            ]);

            if ($nextStep) {
                // Receive action
                $toUser = $this->getUserForOffice($usersByOffice, $toOfficeId, $offices);
                $actionDate = $actionDate->copy()->addHours($faker->numberBetween(1, 8));
                $this->createAction($transaction, [
                    'action_type' => TransactionAction::TYPE_RECEIVE,
                    'from_office_id' => $fromOfficeId,
                    'to_office_id' => $toOfficeId,
                    'from_user_id' => $toUser->id,
                    'to_user_id' => $toUser->id,
                    'workflow_step_id' => $nextStep->id,
                    'ip_address' => $faker->ipv4(),
                    'created_at' => $actionDate,
                ]);
            }
        }

        // Update transaction tracking columns
        $currentStep = $workflowSteps[$stepCount] ?? $workflowSteps->last();
        $transaction->update([
            'current_step_id' => $currentStep->id,
            'received_at' => $actionDate,
            'endorsed_at' => $actionDate->copy()->subHours(4),
        ]);
    }

    /**
     * Create a mixed chain with hold and out-of-workflow examples.
     */
    private function createMixedChain(
        Transaction $transaction,
        $workflowSteps,
        $usersByOffice,
        array $offices,
        Carbon $actionDate,
        $faker
    ): void {
        $step = $workflowSteps->first();
        $nextStep = $workflowSteps[1] ?? $step;

        $fromOfficeId = $step->office_id;
        $fromUser = $this->getUserForOffice($usersByOffice, $fromOfficeId, $offices);

        // Initial endorse
        $actionDate = $actionDate->copy()->addHours($faker->numberBetween(2, 12));
        $this->createAction($transaction, [
            'action_type' => TransactionAction::TYPE_ENDORSE,
            'from_office_id' => $fromOfficeId,
            'to_office_id' => $nextStep->office_id,
            'from_user_id' => $fromUser->id,
            'workflow_step_id' => $step->id,
            'ip_address' => $faker->ipv4(),
            'created_at' => $actionDate,
        ]);

        // Out-of-workflow bypass (misroute correction)
        $bypassOfficeId = $faker->randomElement(array_diff($offices, [$fromOfficeId, $nextStep->office_id])) ?: $fromOfficeId;
        $bypassUser = $this->getUserForOffice($usersByOffice, $bypassOfficeId, $offices);
        $actionDate = $actionDate->copy()->addHours($faker->numberBetween(1, 6));
        $this->createAction($transaction, [
            'action_type' => TransactionAction::TYPE_BYPASS,
            'from_office_id' => $bypassOfficeId,
            'to_office_id' => $nextStep->office_id,
            'from_user_id' => $bypassUser->id,
            'workflow_step_id' => $step->id,
            'is_out_of_workflow' => true,
            'reason' => 'Misroute correction by administrator',
            'ip_address' => $faker->ipv4(),
            'created_at' => $actionDate,
        ]);

        // Hold action example
        $holdUser = $this->getUserForOffice($usersByOffice, $nextStep->office_id, $offices);
        $actionDate = $actionDate->copy()->addHours($faker->numberBetween(2, 12));
        $this->createAction($transaction, [
            'action_type' => TransactionAction::TYPE_HOLD,
            'from_office_id' => $nextStep->office_id,
            'to_office_id' => null,
            'from_user_id' => $holdUser->id,
            'workflow_step_id' => $nextStep->id,
            'reason' => 'Awaiting additional documentation',
            'ip_address' => $faker->ipv4(),
            'created_at' => $actionDate,
        ]);

        // Update transaction tracking columns
        $transaction->update([
            'current_step_id' => $nextStep->id,
            'received_at' => null,
            'endorsed_at' => $actionDate->copy()->subHours(6),
        ]);
    }

    /**
     * Create a transaction action record.
     */
    private function createAction(Transaction $transaction, array $data): void
    {
        DB::table('transaction_actions')->insert(array_merge([
            'transaction_id' => $transaction->id,
            'action_taken_id' => null,
            'to_user_id' => null,
            'is_out_of_workflow' => false,
            'notes' => null,
            'reason' => null,
        ], $data));
    }

    /**
     * Get a user for a specific office or fallback to any user.
     */
    private function getUserForOffice($usersByOffice, int $officeId, array $offices): User
    {
        if ($usersByOffice->has($officeId) && $usersByOffice[$officeId]->isNotEmpty()) {
            return $usersByOffice[$officeId]->random();
        }

        // Fallback: get any user from any office
        foreach ($usersByOffice as $users) {
            if ($users->isNotEmpty()) {
                return $users->random();
            }
        }

        // Last resort: get first user
        return User::query()->first();
    }
}
