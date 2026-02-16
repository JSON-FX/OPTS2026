<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\FundType;
use App\Models\Office;
use App\Models\Procurement;
use App\Models\PurchaseRequest;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowStep;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class TransactionEtaTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Office $office;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\RoleSeeder::class);

        $this->office = Office::factory()->create();
        $this->user = User::factory()->create(['office_id' => $this->office->id]);
        $this->user->assignRole('Endorser');
    }

    public function test_transaction_detail_shows_eta_information(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-02-10')); // Tuesday

        $workflow = Workflow::factory()->create(['category' => 'PR', 'is_active' => true]);
        $step = WorkflowStep::factory()->create([
            'workflow_id' => $workflow->id,
            'office_id' => $this->office->id,
            'step_order' => 1,
            'expected_days' => 3,
        ]);

        $procurement = Procurement::factory()->create();
        $transaction = Transaction::factory()->create([
            'procurement_id' => $procurement->id,
            'category' => 'PR',
            'status' => 'In Progress',
            'workflow_id' => $workflow->id,
            'current_step_id' => $step->id,
            'current_office_id' => $this->office->id,
            'received_at' => Carbon::parse('2026-02-09'), // Monday
        ]);

        $fundType = FundType::factory()->create();
        $purchaseRequest = PurchaseRequest::factory()->create([
            'transaction_id' => $transaction->id,
            'fund_type_id' => $fundType->id,
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('purchase-requests.show', $purchaseRequest->id));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('PurchaseRequests/Show')
            ->has('purchaseRequest.transaction.eta_current_step')
            ->has('purchaseRequest.transaction.eta_completion')
            ->has('purchaseRequest.transaction.delay_days')
            ->has('purchaseRequest.transaction.delay_severity')
            ->has('purchaseRequest.transaction.days_at_current_step')
            ->has('purchaseRequest.transaction.is_stagnant')
        );

        Carbon::setTestNow();
    }

    public function test_transaction_model_appends_eta_attributes(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-02-10'));

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
            'received_at' => Carbon::parse('2026-02-09'),
        ]);

        $array = $transaction->toArray();

        $this->assertArrayHasKey('eta_current_step', $array);
        $this->assertArrayHasKey('eta_completion', $array);
        $this->assertArrayHasKey('delay_days', $array);
        $this->assertArrayHasKey('is_stagnant', $array);
        $this->assertArrayHasKey('delay_severity', $array);
        $this->assertArrayHasKey('days_at_current_step', $array);

        // ETA should be 3 business days from Monday 2/9 => Thursday 2/12
        $this->assertEquals('2026-02-12', $array['eta_current_step']);
        $this->assertEquals(0, $array['delay_days']);
        $this->assertEquals('on_track', $array['delay_severity']);

        Carbon::setTestNow();
    }

    public function test_completed_transaction_returns_null_eta(): void
    {
        $transaction = Transaction::factory()->create([
            'status' => 'Completed',
        ]);

        $array = $transaction->toArray();

        $this->assertNull($array['eta_current_step']);
        $this->assertNull($array['eta_completion']);
        $this->assertEquals(0, $array['delay_days']);
        $this->assertEquals('on_track', $array['delay_severity']);
    }

    public function test_config_idle_threshold_is_accessible(): void
    {
        $this->assertEquals(2, config('opts.idle_threshold_days'));
    }
}
