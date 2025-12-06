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
 * Creates sample workflows for each transaction category:
 * - PR: Purchase Request (5 steps)
 * - PO: Purchase Order (4 steps)
 * - VCH: Voucher (3 steps)
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
     * Flow: Originating Office → Budget → BAC → Accounting → Releasing
     *
     * @param  \Illuminate\Database\Eloquent\Collection<string, Office>  $offices
     */
    private function createPurchaseRequestWorkflow($offices): void
    {
        $workflow = Workflow::create([
            'category' => 'PR',
            'name' => 'Standard Purchase Request Workflow',
            'description' => 'Standard workflow for processing purchase requests through budget validation, BAC review, and accounting approval.',
            'is_active' => true,
        ]);

        $steps = [
            ['abbreviation' => 'GSO', 'step_order' => 1, 'expected_days' => 1, 'is_final_step' => false], // Originating Office (GSO as example)
            ['abbreviation' => 'MBO', 'step_order' => 2, 'expected_days' => 2, 'is_final_step' => false], // Budget Office
            ['abbreviation' => 'BAC', 'step_order' => 3, 'expected_days' => 3, 'is_final_step' => false], // BAC
            ['abbreviation' => 'ACCT', 'step_order' => 4, 'expected_days' => 2, 'is_final_step' => false], // Accounting
            ['abbreviation' => 'MTO', 'step_order' => 5, 'expected_days' => 1, 'is_final_step' => true], // Releasing (Treasurer)
        ];

        $this->createWorkflowSteps($workflow, $steps, $offices);
    }

    /**
     * Create PO workflow with 4 steps.
     *
     * Flow: Originating Office → BAC → Accounting → Releasing
     *
     * @param  \Illuminate\Database\Eloquent\Collection<string, Office>  $offices
     */
    private function createPurchaseOrderWorkflow($offices): void
    {
        $workflow = Workflow::create([
            'category' => 'PO',
            'name' => 'Standard Purchase Order Workflow',
            'description' => 'Standard workflow for processing purchase orders after BAC approval through accounting and releasing.',
            'is_active' => true,
        ]);

        $steps = [
            ['abbreviation' => 'GSO', 'step_order' => 1, 'expected_days' => 1, 'is_final_step' => false], // Originating Office
            ['abbreviation' => 'BAC', 'step_order' => 2, 'expected_days' => 2, 'is_final_step' => false], // BAC
            ['abbreviation' => 'ACCT', 'step_order' => 3, 'expected_days' => 2, 'is_final_step' => false], // Accounting
            ['abbreviation' => 'MTO', 'step_order' => 4, 'expected_days' => 1, 'is_final_step' => true], // Releasing
        ];

        $this->createWorkflowSteps($workflow, $steps, $offices);
    }

    /**
     * Create VCH workflow with 3 steps.
     *
     * Flow: Originating Office → Accounting → Cashier
     *
     * @param  \Illuminate\Database\Eloquent\Collection<string, Office>  $offices
     */
    private function createVoucherWorkflow($offices): void
    {
        $workflow = Workflow::create([
            'category' => 'VCH',
            'name' => 'Standard Voucher Workflow',
            'description' => 'Standard workflow for voucher processing through accounting verification and cashier disbursement.',
            'is_active' => true,
        ]);

        $steps = [
            ['abbreviation' => 'GSO', 'step_order' => 1, 'expected_days' => 1, 'is_final_step' => false], // Originating Office
            ['abbreviation' => 'ACCT', 'step_order' => 2, 'expected_days' => 2, 'is_final_step' => false], // Accounting
            ['abbreviation' => 'MTO', 'step_order' => 3, 'expected_days' => 1, 'is_final_step' => true], // Cashier (Treasurer)
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
