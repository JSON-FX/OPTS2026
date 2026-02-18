<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\ActionTaken;
use App\Models\Office;
use App\Models\Workflow;
use App\Models\WorkflowStep;
use Illuminate\Database\Seeder;

/**
 * Seeds workflows and workflow steps for transaction routing.
 *
 * Creates workflows for each transaction category:
 * - PR: Purchase Request (5 steps)
 * - PO: Purchase Order (3 steps)
 * - VCH: Voucher (6 steps)
 */
class WorkflowSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Cache offices by abbreviation for step creation
        $offices = Office::all()->keyBy('abbreviation');

        // Cache action_taken by description for default action assignment
        $actionTaken = ActionTaken::all()->keyBy('description');

        $this->createPurchaseRequestWorkflow($offices, $actionTaken);
        $this->createPurchaseOrderWorkflow($offices, $actionTaken);
        $this->createVoucherWorkflow($offices, $actionTaken);
    }

    /**
     * Create PR workflow with 5 steps.
     *
     * Flow: MBO → MMO → BAC → MMO-PO → BAC
     *
     * @param  \Illuminate\Database\Eloquent\Collection<string, Office>  $offices
     */
    private function createPurchaseRequestWorkflow($offices, $actionTaken): void
    {
        $workflow = Workflow::create([
            'category' => 'PR',
            'name' => 'Standard Purchase Request Workflow',
            'description' => 'Standard workflow for processing purchase requests through budget, mayor, BAC review, procurement, and final BAC approval.',
            'is_active' => true,
        ]);

        $steps = [
            ['abbreviation' => 'MBO', 'step_order' => 1, 'expected_days' => 2, 'is_final_step' => false, 'action_taken' => 'Creation of Purchase Request'],
            ['abbreviation' => 'MMO', 'step_order' => 2, 'expected_days' => 2, 'is_final_step' => false, 'action_taken' => 'For Approval'],
            ['abbreviation' => 'BAC', 'step_order' => 3, 'expected_days' => 3, 'is_final_step' => false, 'action_taken' => 'For BAC Meeting (Mode of Procurement)'],
            ['abbreviation' => 'MMO-PO', 'step_order' => 4, 'expected_days' => 2, 'is_final_step' => false, 'action_taken' => 'For Canvassing'],
            ['abbreviation' => 'BAC', 'step_order' => 5, 'expected_days' => 2, 'is_final_step' => true, 'action_taken' => 'To Complete P.R'],
        ];

        $this->createWorkflowSteps($workflow, $steps, $offices, $actionTaken);
    }

    /**
     * Create PO workflow with 3 steps.
     *
     * Flow: BAC → MMO-PO → MMO-PSMD
     *
     * @param  \Illuminate\Database\Eloquent\Collection<string, Office>  $offices
     */
    private function createPurchaseOrderWorkflow($offices, $actionTaken): void
    {
        $workflow = Workflow::create([
            'category' => 'PO',
            'name' => 'Standard Purchase Order Workflow',
            'description' => 'Standard workflow for processing purchase orders through BAC, procurement, and property/supply management.',
            'is_active' => true,
        ]);

        $steps = [
            ['abbreviation' => 'BAC', 'step_order' => 1, 'expected_days' => 2, 'is_final_step' => false, 'action_taken' => 'Creation of Purchase Order'],
            ['abbreviation' => 'MMO-PO', 'step_order' => 2, 'expected_days' => 2, 'is_final_step' => false, 'action_taken' => 'For Supplier Signature'],
            ['abbreviation' => 'MMO-PSMD', 'step_order' => 3, 'expected_days' => 2, 'is_final_step' => true, 'action_taken' => 'Waiting For Delivery'],
        ];

        $this->createWorkflowSteps($workflow, $steps, $offices, $actionTaken);
    }

    /**
     * Create VCH workflow with 6 steps.
     *
     * Flow: MBO → MACCO → MMO → MTO → MMO → MTO
     *
     * @param  \Illuminate\Database\Eloquent\Collection<string, Office>  $offices
     */
    private function createVoucherWorkflow($offices, $actionTaken): void
    {
        $workflow = Workflow::create([
            'category' => 'VCH',
            'name' => 'Standard Voucher Workflow',
            'description' => 'Standard workflow for voucher processing through budget, accounting, mayor approval, treasurer disbursement, final mayor and treasurer sign-off.',
            'is_active' => true,
        ]);

        $steps = [
            ['abbreviation' => 'MBO', 'step_order' => 1, 'expected_days' => 2, 'is_final_step' => false, 'action_taken' => 'Creation of Voucher'],
            ['abbreviation' => 'MACCO', 'step_order' => 2, 'expected_days' => 2, 'is_final_step' => false, 'action_taken' => 'For Obligation Request'],
            ['abbreviation' => 'MMO', 'step_order' => 3, 'expected_days' => 2, 'is_final_step' => false, 'action_taken' => "Check for Mayor's Signature"],
            ['abbreviation' => 'MTO', 'step_order' => 4, 'expected_days' => 1, 'is_final_step' => false, 'action_taken' => 'For Preparation of Check with Signature'],
            ['abbreviation' => 'MMO', 'step_order' => 5, 'expected_days' => 2, 'is_final_step' => false, 'action_taken' => "Check for Mayor's Signature"],
            ['abbreviation' => 'MTO', 'step_order' => 6, 'expected_days' => 1, 'is_final_step' => true, 'action_taken' => 'For Disbursement and Completion of Voucher'],
        ];

        $this->createWorkflowSteps($workflow, $steps, $offices, $actionTaken);
    }

    /**
     * Create workflow steps from configuration.
     *
     * @param  array<int, array{abbreviation: string, step_order: int, expected_days: int, is_final_step: bool, action_taken?: string}>  $steps
     * @param  \Illuminate\Database\Eloquent\Collection<string, Office>  $offices
     * @param  \Illuminate\Database\Eloquent\Collection<string, ActionTaken>  $actionTaken
     */
    private function createWorkflowSteps(Workflow $workflow, array $steps, $offices, $actionTaken): void
    {
        foreach ($steps as $stepConfig) {
            $office = $offices->get($stepConfig['abbreviation']);

            if (! $office) {
                $this->command->warn("Office with abbreviation '{$stepConfig['abbreviation']}' not found. Skipping step.");

                continue;
            }

            $actionTakenId = null;
            if (! empty($stepConfig['action_taken'])) {
                $action = $actionTaken->get($stepConfig['action_taken']);
                if ($action) {
                    $actionTakenId = $action->id;
                } else {
                    $this->command->warn("Action taken '{$stepConfig['action_taken']}' not found. Skipping default action for step {$stepConfig['step_order']}.");
                }
            }

            WorkflowStep::create([
                'workflow_id' => $workflow->id,
                'office_id' => $office->id,
                'step_order' => $stepConfig['step_order'],
                'expected_days' => $stepConfig['expected_days'],
                'is_final_step' => $stepConfig['is_final_step'],
                'action_taken_id' => $actionTakenId,
            ]);
        }
    }
}
