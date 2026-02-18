<?php

declare(strict_types=1);

namespace Database\Seeders;

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

        $this->createPurchaseRequestWorkflow($offices);
        $this->createPurchaseOrderWorkflow($offices);
        $this->createVoucherWorkflow($offices);
    }

    /**
     * Create PR workflow with 5 steps.
     *
     * Flow: MBO → MMO → BAC → MMO-PO → BAC
     *
     * @param  \Illuminate\Database\Eloquent\Collection<string, Office>  $offices
     */
    private function createPurchaseRequestWorkflow($offices): void
    {
        $workflow = Workflow::create([
            'category' => 'PR',
            'name' => 'Standard Purchase Request Workflow',
            'description' => 'Standard workflow for processing purchase requests through budget, mayor, BAC review, procurement, and final BAC approval.',
            'is_active' => true,
        ]);

        $steps = [
            ['abbreviation' => 'MBO', 'step_order' => 1, 'expected_days' => 2, 'is_final_step' => false],
            ['abbreviation' => 'MMO', 'step_order' => 2, 'expected_days' => 2, 'is_final_step' => false],
            ['abbreviation' => 'BAC', 'step_order' => 3, 'expected_days' => 3, 'is_final_step' => false],
            ['abbreviation' => 'MMO-PO', 'step_order' => 4, 'expected_days' => 2, 'is_final_step' => false],
            ['abbreviation' => 'BAC', 'step_order' => 5, 'expected_days' => 2, 'is_final_step' => true],
        ];

        $this->createWorkflowSteps($workflow, $steps, $offices);
    }

    /**
     * Create PO workflow with 3 steps.
     *
     * Flow: BAC → MMO-PO → MMO-PSMD
     *
     * @param  \Illuminate\Database\Eloquent\Collection<string, Office>  $offices
     */
    private function createPurchaseOrderWorkflow($offices): void
    {
        $workflow = Workflow::create([
            'category' => 'PO',
            'name' => 'Standard Purchase Order Workflow',
            'description' => 'Standard workflow for processing purchase orders through BAC, procurement, and property/supply management.',
            'is_active' => true,
        ]);

        $steps = [
            ['abbreviation' => 'BAC', 'step_order' => 1, 'expected_days' => 2, 'is_final_step' => false],
            ['abbreviation' => 'MMO-PO', 'step_order' => 2, 'expected_days' => 2, 'is_final_step' => false],
            ['abbreviation' => 'MMO-PSMD', 'step_order' => 3, 'expected_days' => 2, 'is_final_step' => true],
        ];

        $this->createWorkflowSteps($workflow, $steps, $offices);
    }

    /**
     * Create VCH workflow with 6 steps.
     *
     * Flow: MBO → MACCO → MMO → MTO → MMO → MTO
     *
     * @param  \Illuminate\Database\Eloquent\Collection<string, Office>  $offices
     */
    private function createVoucherWorkflow($offices): void
    {
        $workflow = Workflow::create([
            'category' => 'VCH',
            'name' => 'Standard Voucher Workflow',
            'description' => 'Standard workflow for voucher processing through budget, accounting, mayor approval, treasurer disbursement, final mayor and treasurer sign-off.',
            'is_active' => true,
        ]);

        $steps = [
            ['abbreviation' => 'MBO', 'step_order' => 1, 'expected_days' => 2, 'is_final_step' => false],
            ['abbreviation' => 'MACCO', 'step_order' => 2, 'expected_days' => 2, 'is_final_step' => false],
            ['abbreviation' => 'MMO', 'step_order' => 3, 'expected_days' => 2, 'is_final_step' => false],
            ['abbreviation' => 'MTO', 'step_order' => 4, 'expected_days' => 1, 'is_final_step' => false],
            ['abbreviation' => 'MMO', 'step_order' => 5, 'expected_days' => 2, 'is_final_step' => false],
            ['abbreviation' => 'MTO', 'step_order' => 6, 'expected_days' => 1, 'is_final_step' => true],
        ];

        $this->createWorkflowSteps($workflow, $steps, $offices);
    }

    /**
     * Create workflow steps from configuration.
     *
     * @param  array<int, array{abbreviation: string, step_order: int, expected_days: int, is_final_step: bool}>  $steps
     * @param  \Illuminate\Database\Eloquent\Collection<string, Office>  $offices
     */
    private function createWorkflowSteps(Workflow $workflow, array $steps, $offices): void
    {
        foreach ($steps as $stepConfig) {
            $office = $offices->get($stepConfig['abbreviation']);

            if (! $office) {
                $this->command->warn("Office with abbreviation '{$stepConfig['abbreviation']}' not found. Skipping step.");

                continue;
            }

            WorkflowStep::create([
                'workflow_id' => $workflow->id,
                'office_id' => $office->id,
                'step_order' => $stepConfig['step_order'],
                'expected_days' => $stepConfig['expected_days'],
                'is_final_step' => $stepConfig['is_final_step'],
            ]);
        }
    }
}
