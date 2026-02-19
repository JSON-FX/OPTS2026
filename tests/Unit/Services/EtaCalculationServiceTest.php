<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\Transaction;
use App\Models\TransactionAction;
use App\Models\Workflow;
use App\Models\WorkflowStep;
use App\Services\EtaCalculationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class EtaCalculationServiceTest extends TestCase
{
    use RefreshDatabase;

    private EtaCalculationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new EtaCalculationService;
    }

    // ---------------------------------------------------
    // Business Days Helpers
    // ---------------------------------------------------

    public function test_business_days_between_excludes_weekends(): void
    {
        // Monday to Friday = 4 business days
        $start = Carbon::parse('2026-02-09'); // Monday
        $end = Carbon::parse('2026-02-13');   // Friday

        $this->assertEquals(4, $this->service->businessDaysBetween($start, $end));
    }

    public function test_business_days_between_full_week(): void
    {
        // Monday to next Monday = 5 business days
        $start = Carbon::parse('2026-02-09'); // Monday
        $end = Carbon::parse('2026-02-16');   // Next Monday

        $this->assertEquals(5, $this->service->businessDaysBetween($start, $end));
    }

    public function test_business_days_between_same_day_is_zero(): void
    {
        $date = Carbon::parse('2026-02-09');

        $this->assertEquals(0, $this->service->businessDaysBetween($date, $date));
    }

    public function test_business_days_between_over_weekend(): void
    {
        // Friday to Monday = 1 business day (only Friday counts)
        $start = Carbon::parse('2026-02-13'); // Friday
        $end = Carbon::parse('2026-02-16');   // Monday

        $this->assertEquals(1, $this->service->businessDaysBetween($start, $end));
    }

    public function test_add_business_days_skips_weekends(): void
    {
        // Starting Thursday, add 3 business days => next Tuesday
        $start = Carbon::parse('2026-02-12'); // Thursday
        $result = $this->service->addBusinessDays($start, 3);

        $this->assertEquals('2026-02-17', $result->toDateString()); // Tuesday
    }

    public function test_add_business_days_zero_returns_same_date(): void
    {
        $start = Carbon::parse('2026-02-12');
        $result = $this->service->addBusinessDays($start, 0);

        $this->assertEquals('2026-02-12', $result->toDateString());
    }

    public function test_add_business_days_starting_friday(): void
    {
        // Starting Friday, add 1 business day => Monday
        $start = Carbon::parse('2026-02-13'); // Friday
        $result = $this->service->addBusinessDays($start, 1);

        $this->assertEquals('2026-02-16', $result->toDateString()); // Monday
    }

    public function test_add_business_days_starting_saturday(): void
    {
        // Starting Saturday, add 1 business day => Monday
        $start = Carbon::parse('2026-02-14'); // Saturday
        $result = $this->service->addBusinessDays($start, 1);

        $this->assertEquals('2026-02-16', $result->toDateString()); // Monday
    }

    // ---------------------------------------------------
    // getCurrentStepEta
    // ---------------------------------------------------

    public function test_get_current_step_eta_returns_null_for_completed(): void
    {
        $transaction = Transaction::factory()->create(['status' => 'Completed']);

        $this->assertNull($this->service->getCurrentStepEta($transaction));
    }

    public function test_get_current_step_eta_returns_null_for_cancelled(): void
    {
        $transaction = Transaction::factory()->create(['status' => 'Cancelled']);

        $this->assertNull($this->service->getCurrentStepEta($transaction));
    }

    public function test_get_current_step_eta_returns_null_without_received_at(): void
    {
        $transaction = Transaction::factory()->create([
            'status' => 'In Progress',
            'received_at' => null,
        ]);

        $this->assertNull($this->service->getCurrentStepEta($transaction));
    }

    public function test_get_current_step_eta_calculates_correctly(): void
    {
        $workflow = Workflow::factory()->create(['category' => 'PR']);
        $step = WorkflowStep::factory()->create([
            'workflow_id' => $workflow->id,
            'step_order' => 1,
            'expected_days' => 3,
        ]);

        $transaction = Transaction::factory()->create([
            'status' => 'In Progress',
            'workflow_id' => $workflow->id,
            'current_step_id' => $step->id,
            'received_at' => Carbon::parse('2026-02-09'), // Monday
        ]);

        $eta = $this->service->getCurrentStepEta($transaction);

        // 3 business days from Monday 2/9 => Thursday 2/12
        $this->assertNotNull($eta);
        $this->assertEquals('2026-02-12', $eta->toDateString());
    }

    // ---------------------------------------------------
    // getCompletionEta
    // ---------------------------------------------------

    public function test_get_completion_eta_returns_null_for_completed(): void
    {
        $transaction = Transaction::factory()->create(['status' => 'Completed']);

        $this->assertNull($this->service->getCompletionEta($transaction));
    }

    public function test_get_completion_eta_returns_null_without_workflow(): void
    {
        $transaction = Transaction::factory()->create([
            'status' => 'In Progress',
            'workflow_id' => null,
        ]);

        $this->assertNull($this->service->getCompletionEta($transaction));
    }

    public function test_get_completion_eta_sums_remaining_steps(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-02-09')); // Monday

        $workflow = Workflow::factory()->create(['category' => 'PR']);
        $step1 = WorkflowStep::factory()->create([
            'workflow_id' => $workflow->id,
            'step_order' => 1,
            'expected_days' => 2,
        ]);
        WorkflowStep::factory()->create([
            'workflow_id' => $workflow->id,
            'step_order' => 2,
            'expected_days' => 3,
        ]);

        $transaction = Transaction::factory()->create([
            'status' => 'In Progress',
            'workflow_id' => $workflow->id,
            'current_step_id' => $step1->id,
            'received_at' => Carbon::parse('2026-02-09'), // Monday
        ]);

        $eta = $this->service->getCompletionEta($transaction);

        // Total remaining = 2 + 3 = 5 business days
        // Days spent = 0 (received today)
        // 5 business days from Monday 2/9 => Monday 2/16
        $this->assertNotNull($eta);
        $this->assertEquals('2026-02-16', $eta->toDateString());

        Carbon::setTestNow(); // Reset
    }

    // ---------------------------------------------------
    // getDelayDays
    // ---------------------------------------------------

    public function test_get_delay_days_returns_zero_when_on_track(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-02-10')); // Tuesday

        $workflow = Workflow::factory()->create(['category' => 'PR']);
        $step = WorkflowStep::factory()->create([
            'workflow_id' => $workflow->id,
            'step_order' => 1,
            'expected_days' => 3,
        ]);

        $transaction = Transaction::factory()->create([
            'status' => 'In Progress',
            'workflow_id' => $workflow->id,
            'current_step_id' => $step->id,
            'received_at' => Carbon::parse('2026-02-09'), // Monday
        ]);

        // ETA is Thursday 2/12, today is Tuesday 2/10 => on track
        $this->assertEquals(0, $this->service->getDelayDays($transaction));

        Carbon::setTestNow();
    }

    public function test_get_delay_days_returns_positive_when_overdue(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-02-16')); // Monday (next week)

        $workflow = Workflow::factory()->create(['category' => 'PR']);
        $step = WorkflowStep::factory()->create([
            'workflow_id' => $workflow->id,
            'step_order' => 1,
            'expected_days' => 2,
        ]);

        $transaction = Transaction::factory()->create([
            'status' => 'In Progress',
            'workflow_id' => $workflow->id,
            'current_step_id' => $step->id,
            'received_at' => Carbon::parse('2026-02-09'), // Monday
        ]);

        // ETA is Wednesday 2/11, today is Monday 2/16
        // Business days from Wed 2/11 to Mon 2/16 = 3 (Wed, Thu, Fri)
        $delayDays = $this->service->getDelayDays($transaction);
        $this->assertEquals(3, $delayDays);

        Carbon::setTestNow();
    }

    // ---------------------------------------------------
    // isStagnant
    // ---------------------------------------------------

    public function test_is_stagnant_returns_false_for_completed(): void
    {
        $transaction = Transaction::factory()->create(['status' => 'Completed']);

        $this->assertFalse($this->service->isStagnant($transaction));
    }

    public function test_is_stagnant_returns_true_when_delayed(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-02-16'));

        $workflow = Workflow::factory()->create(['category' => 'PR']);
        $step = WorkflowStep::factory()->create([
            'workflow_id' => $workflow->id,
            'step_order' => 1,
            'expected_days' => 1,
        ]);

        $transaction = Transaction::factory()->create([
            'status' => 'In Progress',
            'workflow_id' => $workflow->id,
            'current_step_id' => $step->id,
            'received_at' => Carbon::parse('2026-02-09'),
        ]);

        $this->assertTrue($this->service->isStagnant($transaction));

        Carbon::setTestNow();
    }

    public function test_is_stagnant_returns_true_when_idle_too_long(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-02-12')); // Thursday

        $workflow = Workflow::factory()->create(['category' => 'PR']);
        $step = WorkflowStep::factory()->create([
            'workflow_id' => $workflow->id,
            'step_order' => 1,
            'expected_days' => 10, // Long deadline, so not delayed
        ]);

        $transaction = Transaction::factory()->create([
            'status' => 'In Progress',
            'workflow_id' => $workflow->id,
            'current_step_id' => $step->id,
            'received_at' => Carbon::parse('2026-02-09'),
        ]);

        // Create an action from 2+ business days ago
        TransactionAction::factory()->create([
            'transaction_id' => $transaction->id,
            'action_type' => 'receive',
            'from_user_id' => $transaction->created_by_user_id,
            'created_at' => Carbon::parse('2026-02-09'), // Monday
        ]);

        // Today is Thursday, last action was Monday => 3 business days ago >= 2 threshold
        $this->assertTrue($this->service->isStagnant($transaction));

        Carbon::setTestNow();
    }

    // ---------------------------------------------------
    // getDelaySeverity
    // ---------------------------------------------------

    public function test_get_delay_severity_on_track(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-02-10'));

        $workflow = Workflow::factory()->create(['category' => 'PR']);
        $step = WorkflowStep::factory()->create([
            'workflow_id' => $workflow->id,
            'step_order' => 1,
            'expected_days' => 5,
        ]);

        $transaction = Transaction::factory()->create([
            'status' => 'In Progress',
            'workflow_id' => $workflow->id,
            'current_step_id' => $step->id,
            'received_at' => Carbon::parse('2026-02-09'),
        ]);

        $this->assertEquals('on_track', $this->service->getDelaySeverity($transaction));

        Carbon::setTestNow();
    }

    public function test_get_delay_severity_warning(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-02-13')); // Friday

        $workflow = Workflow::factory()->create(['category' => 'PR']);
        $step = WorkflowStep::factory()->create([
            'workflow_id' => $workflow->id,
            'step_order' => 1,
            'expected_days' => 1,
        ]);

        $transaction = Transaction::factory()->create([
            'status' => 'In Progress',
            'workflow_id' => $workflow->id,
            'current_step_id' => $step->id,
            'received_at' => Carbon::parse('2026-02-09'), // Monday
        ]);

        // ETA is Tuesday 2/10, today is Friday 2/13 => 3 days overdue... actually:
        // businessDaysBetween(Tue 2/10, Fri 2/13) = 3 (Tue, Wed, Thu) => overdue
        // Let me adjust: need exactly 1-2 delay
        Carbon::setTestNow(Carbon::parse('2026-02-12')); // Thursday
        // ETA is Tuesday 2/10, today is Thursday 2/12 => 2 business days (Tue, Wed) => warning
        $this->assertEquals('warning', $this->service->getDelaySeverity($transaction));

        Carbon::setTestNow();
    }

    public function test_get_delay_severity_overdue(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-02-16')); // Monday next week

        $workflow = Workflow::factory()->create(['category' => 'PR']);
        $step = WorkflowStep::factory()->create([
            'workflow_id' => $workflow->id,
            'step_order' => 1,
            'expected_days' => 1,
        ]);

        $transaction = Transaction::factory()->create([
            'status' => 'In Progress',
            'workflow_id' => $workflow->id,
            'current_step_id' => $step->id,
            'received_at' => Carbon::parse('2026-02-09'), // Monday
        ]);

        // ETA is Tuesday 2/10, today is Monday 2/16
        // businessDaysBetween(Tue 2/10, Mon 2/16) = 4 (Tue, Wed, Thu, Fri) => overdue
        $this->assertEquals('overdue', $this->service->getDelaySeverity($transaction));

        Carbon::setTestNow();
    }

    public function test_get_delay_severity_returns_on_track_for_completed(): void
    {
        $transaction = Transaction::factory()->create(['status' => 'Completed']);

        $this->assertEquals('on_track', $this->service->getDelaySeverity($transaction));
    }

    // ---------------------------------------------------
    // getDaysAtCurrentStep
    // ---------------------------------------------------

    public function test_days_at_current_step_returns_zero_without_received_at(): void
    {
        $transaction = Transaction::factory()->create([
            'status' => 'In Progress',
            'received_at' => null,
        ]);

        $this->assertEquals(0, $this->service->getDaysAtCurrentStep($transaction));
    }

    public function test_days_at_current_step_calculates_correctly(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-02-12')); // Thursday

        $transaction = Transaction::factory()->create([
            'status' => 'In Progress',
            'received_at' => Carbon::parse('2026-02-09'), // Monday
        ]);

        // Monday to Thursday = 3 business days
        $this->assertEquals(3, $this->service->getDaysAtCurrentStep($transaction));

        Carbon::setTestNow();
    }
}
