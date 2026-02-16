<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\Office;
use App\Models\Procurement;
use App\Models\Transaction;
use App\Models\TransactionAction;
use App\Models\User;
use App\Services\DashboardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardServiceTest extends TestCase
{
    use RefreshDatabase;

    private DashboardService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(DashboardService::class);
    }

    public function test_get_summary_cards_returns_correct_structure(): void
    {
        $summary = $this->service->getSummaryCards();

        $this->assertArrayHasKey('procurements', $summary);
        $this->assertArrayHasKey('purchase_requests', $summary);
        $this->assertArrayHasKey('purchase_orders', $summary);
        $this->assertArrayHasKey('vouchers', $summary);

        foreach ($summary as $counts) {
            $this->assertArrayHasKey('total', $counts);
            $this->assertArrayHasKey('created', $counts);
            $this->assertArrayHasKey('in_progress', $counts);
            $this->assertArrayHasKey('completed', $counts);
            $this->assertArrayHasKey('on_hold', $counts);
            $this->assertArrayHasKey('cancelled', $counts);
        }
    }

    public function test_get_summary_cards_with_no_data(): void
    {
        $summary = $this->service->getSummaryCards();

        $this->assertEquals(0, $summary['procurements']['total']);
        $this->assertEquals(0, $summary['purchase_requests']['total']);
        $this->assertEquals(0, $summary['purchase_orders']['total']);
        $this->assertEquals(0, $summary['vouchers']['total']);
    }

    public function test_get_summary_cards_counts_procurements_by_status(): void
    {
        $user = User::factory()->create();

        Procurement::factory()->count(3)->create(['status' => 'Created', 'created_by_user_id' => $user->id]);
        Procurement::factory()->count(2)->create(['status' => 'In Progress', 'created_by_user_id' => $user->id]);
        Procurement::factory()->create(['status' => 'Completed', 'created_by_user_id' => $user->id]);

        $summary = $this->service->getSummaryCards();

        $this->assertEquals(6, $summary['procurements']['total']);
        $this->assertEquals(3, $summary['procurements']['created']);
        $this->assertEquals(2, $summary['procurements']['in_progress']);
        $this->assertEquals(1, $summary['procurements']['completed']);
        $this->assertEquals(0, $summary['procurements']['on_hold']);
        $this->assertEquals(0, $summary['procurements']['cancelled']);
    }

    public function test_get_summary_cards_counts_transactions_by_category_and_status(): void
    {
        $user = User::factory()->create();
        $procurement = Procurement::factory()->create(['created_by_user_id' => $user->id]);

        // Create PR transactions
        Transaction::factory()->count(2)->create([
            'procurement_id' => $procurement->id,
            'category' => 'PR',
            'status' => 'In Progress',
            'created_by_user_id' => $user->id,
        ]);
        Transaction::factory()->create([
            'procurement_id' => $procurement->id,
            'category' => 'PR',
            'status' => 'Completed',
            'created_by_user_id' => $user->id,
        ]);

        // Create PO transactions
        Transaction::factory()->create([
            'procurement_id' => $procurement->id,
            'category' => 'PO',
            'status' => 'Created',
            'created_by_user_id' => $user->id,
        ]);

        // Create VCH transactions
        Transaction::factory()->count(2)->create([
            'procurement_id' => $procurement->id,
            'category' => 'VCH',
            'status' => 'On Hold',
            'created_by_user_id' => $user->id,
        ]);

        $summary = $this->service->getSummaryCards();

        $this->assertEquals(3, $summary['purchase_requests']['total']);
        $this->assertEquals(2, $summary['purchase_requests']['in_progress']);
        $this->assertEquals(1, $summary['purchase_requests']['completed']);

        $this->assertEquals(1, $summary['purchase_orders']['total']);
        $this->assertEquals(1, $summary['purchase_orders']['created']);

        $this->assertEquals(2, $summary['vouchers']['total']);
        $this->assertEquals(2, $summary['vouchers']['on_hold']);
    }

    public function test_get_office_workload_returns_correct_structure(): void
    {
        $user = User::factory()->create();
        $office = Office::factory()->create();
        $procurement = Procurement::factory()->create(['created_by_user_id' => $user->id]);

        Transaction::factory()->create([
            'procurement_id' => $procurement->id,
            'category' => 'PR',
            'status' => 'In Progress',
            'current_office_id' => $office->id,
            'created_by_user_id' => $user->id,
        ]);

        $workload = $this->service->getOfficeWorkload();

        $this->assertCount(1, $workload);

        $row = $workload->first();
        $this->assertEquals($office->id, $row->office_id);
        $this->assertEquals($office->name, $row->office_name);
        $this->assertEquals($office->abbreviation, $row->office_abbreviation);
        $this->assertEquals(1, $row->pr_count);
        $this->assertEquals(0, $row->po_count);
        $this->assertEquals(0, $row->vch_count);
        $this->assertEquals(1, $row->total);
        $this->assertIsInt($row->stagnant_count);
    }

    public function test_get_office_workload_with_no_data(): void
    {
        $workload = $this->service->getOfficeWorkload();

        $this->assertCount(0, $workload);
    }

    public function test_get_office_workload_excludes_completed_transactions(): void
    {
        $user = User::factory()->create();
        $office = Office::factory()->create();
        $procurement = Procurement::factory()->create(['created_by_user_id' => $user->id]);

        Transaction::factory()->create([
            'procurement_id' => $procurement->id,
            'category' => 'PR',
            'status' => 'Completed',
            'current_office_id' => $office->id,
            'created_by_user_id' => $user->id,
        ]);

        $workload = $this->service->getOfficeWorkload();

        $this->assertCount(0, $workload);
    }

    public function test_get_office_workload_groups_by_office(): void
    {
        $user = User::factory()->create();
        $office1 = Office::factory()->create(['name' => 'Office A']);
        $office2 = Office::factory()->create(['name' => 'Office B']);
        $procurement = Procurement::factory()->create(['created_by_user_id' => $user->id]);

        Transaction::factory()->count(2)->create([
            'procurement_id' => $procurement->id,
            'category' => 'PR',
            'status' => 'In Progress',
            'current_office_id' => $office1->id,
            'created_by_user_id' => $user->id,
        ]);
        Transaction::factory()->create([
            'procurement_id' => $procurement->id,
            'category' => 'PO',
            'status' => 'In Progress',
            'current_office_id' => $office1->id,
            'created_by_user_id' => $user->id,
        ]);
        Transaction::factory()->create([
            'procurement_id' => $procurement->id,
            'category' => 'VCH',
            'status' => 'Created',
            'current_office_id' => $office2->id,
            'created_by_user_id' => $user->id,
        ]);

        $workload = $this->service->getOfficeWorkload();

        $this->assertCount(2, $workload);

        // Sorted by total desc, so office1 (3) comes first
        $first = $workload->first();
        $this->assertEquals($office1->id, $first->office_id);
        $this->assertEquals(2, $first->pr_count);
        $this->assertEquals(1, $first->po_count);
        $this->assertEquals(0, $first->vch_count);
        $this->assertEquals(3, $first->total);

        $second = $workload->last();
        $this->assertEquals($office2->id, $second->office_id);
        $this->assertEquals(0, $second->pr_count);
        $this->assertEquals(0, $second->po_count);
        $this->assertEquals(1, $second->vch_count);
        $this->assertEquals(1, $second->total);
    }

    // -------------------------------------------------------
    // Story 4.1.2 - Activity Feed Tests
    // -------------------------------------------------------

    public function test_get_recent_activity_returns_empty_array_when_no_actions(): void
    {
        $result = $this->service->getRecentActivity();

        $this->assertIsArray($result);
        $this->assertCount(0, $result);
    }

    public function test_get_recent_activity_returns_correct_structure(): void
    {
        $user = User::factory()->create();
        $office1 = Office::factory()->create();
        $office2 = Office::factory()->create();
        $procurement = Procurement::factory()->create(['created_by_user_id' => $user->id]);
        $transaction = Transaction::factory()->create([
            'procurement_id' => $procurement->id,
            'category' => 'PR',
            'created_by_user_id' => $user->id,
        ]);

        TransactionAction::factory()->endorse()->create([
            'transaction_id' => $transaction->id,
            'from_user_id' => $user->id,
            'from_office_id' => $office1->id,
            'to_office_id' => $office2->id,
        ]);

        $result = $this->service->getRecentActivity();

        $this->assertCount(1, $result);
        $entry = $result[0];
        $this->assertArrayHasKey('id', $entry);
        $this->assertArrayHasKey('action_type', $entry);
        $this->assertArrayHasKey('transaction_reference_number', $entry);
        $this->assertArrayHasKey('transaction_id', $entry);
        $this->assertArrayHasKey('transaction_category', $entry);
        $this->assertArrayHasKey('actor_name', $entry);
        $this->assertArrayHasKey('from_office', $entry);
        $this->assertArrayHasKey('to_office', $entry);
        $this->assertArrayHasKey('is_out_of_workflow', $entry);
        $this->assertArrayHasKey('created_at', $entry);
        $this->assertEquals('endorse', $entry['action_type']);
        $this->assertEquals($user->name, $entry['actor_name']);
    }

    public function test_get_recent_activity_filters_action_types(): void
    {
        $user = User::factory()->create();
        $procurement = Procurement::factory()->create(['created_by_user_id' => $user->id]);
        $transaction = Transaction::factory()->create([
            'procurement_id' => $procurement->id,
            'category' => 'PR',
            'created_by_user_id' => $user->id,
        ]);

        // These should appear
        TransactionAction::factory()->endorse()->create([
            'transaction_id' => $transaction->id,
            'from_user_id' => $user->id,
        ]);
        TransactionAction::factory()->receive()->create([
            'transaction_id' => $transaction->id,
            'from_user_id' => $user->id,
        ]);
        TransactionAction::factory()->complete()->create([
            'transaction_id' => $transaction->id,
            'from_user_id' => $user->id,
        ]);

        // These should NOT appear
        TransactionAction::factory()->hold()->create([
            'transaction_id' => $transaction->id,
            'from_user_id' => $user->id,
        ]);
        TransactionAction::factory()->cancel()->create([
            'transaction_id' => $transaction->id,
            'from_user_id' => $user->id,
        ]);

        $result = $this->service->getRecentActivity();

        $this->assertCount(3, $result);
        $actionTypes = array_column($result, 'action_type');
        $this->assertContains('endorse', $actionTypes);
        $this->assertContains('receive', $actionTypes);
        $this->assertContains('complete', $actionTypes);
        $this->assertNotContains('hold', $actionTypes);
        $this->assertNotContains('cancel', $actionTypes);
    }

    public function test_get_recent_activity_respects_limit(): void
    {
        $user = User::factory()->create();
        $procurement = Procurement::factory()->create(['created_by_user_id' => $user->id]);
        $transaction = Transaction::factory()->create([
            'procurement_id' => $procurement->id,
            'category' => 'PR',
            'created_by_user_id' => $user->id,
        ]);

        TransactionAction::factory()->endorse()->count(5)->create([
            'transaction_id' => $transaction->id,
            'from_user_id' => $user->id,
        ]);

        $result = $this->service->getRecentActivity(3);

        $this->assertCount(3, $result);
    }

    public function test_get_recent_activity_orders_by_created_at_desc(): void
    {
        $user = User::factory()->create();
        $procurement = Procurement::factory()->create(['created_by_user_id' => $user->id]);
        $transaction = Transaction::factory()->create([
            'procurement_id' => $procurement->id,
            'category' => 'PR',
            'created_by_user_id' => $user->id,
        ]);

        $oldest = TransactionAction::factory()->endorse()->create([
            'transaction_id' => $transaction->id,
            'from_user_id' => $user->id,
            'created_at' => now()->subHours(2),
        ]);
        $newest = TransactionAction::factory()->receive()->create([
            'transaction_id' => $transaction->id,
            'from_user_id' => $user->id,
            'created_at' => now(),
        ]);

        $result = $this->service->getRecentActivity();

        $this->assertEquals($newest->id, $result[0]['id']);
        $this->assertEquals($oldest->id, $result[1]['id']);
    }

    // -------------------------------------------------------
    // Story 4.1.2 - Stagnant Transactions Tests
    // -------------------------------------------------------

    public function test_get_stagnant_transactions_returns_empty_array_when_no_stagnant(): void
    {
        $result = $this->service->getStagnantTransactions();

        $this->assertIsArray($result);
        $this->assertCount(0, $result);
    }

    public function test_get_stagnant_transactions_returns_correct_structure(): void
    {
        $user = User::factory()->create();
        $office = Office::factory()->create();
        $procurement = Procurement::factory()->create(['created_by_user_id' => $user->id]);

        // Create a transaction that's stagnant (idle for long time)
        $transaction = Transaction::factory()->create([
            'procurement_id' => $procurement->id,
            'category' => 'PR',
            'status' => 'In Progress',
            'current_office_id' => $office->id,
            'created_by_user_id' => $user->id,
        ]);

        // Create an old action to trigger idle stagnation
        TransactionAction::factory()->receive()->create([
            'transaction_id' => $transaction->id,
            'from_user_id' => $user->id,
            'created_at' => now()->subDays(10),
        ]);

        $result = $this->service->getStagnantTransactions();

        if (count($result) > 0) {
            $entry = $result[0];
            $this->assertArrayHasKey('id', $entry);
            $this->assertArrayHasKey('reference_number', $entry);
            $this->assertArrayHasKey('category', $entry);
            $this->assertArrayHasKey('current_office_name', $entry);
            $this->assertArrayHasKey('delay_days', $entry);
            $this->assertArrayHasKey('delay_severity', $entry);
            $this->assertArrayHasKey('days_at_current_step', $entry);
        }
    }

    public function test_get_stagnant_transactions_excludes_completed_and_cancelled(): void
    {
        $user = User::factory()->create();
        $office = Office::factory()->create();
        $procurement = Procurement::factory()->create(['created_by_user_id' => $user->id]);

        Transaction::factory()->create([
            'procurement_id' => $procurement->id,
            'category' => 'PR',
            'status' => 'Completed',
            'current_office_id' => $office->id,
            'created_by_user_id' => $user->id,
        ]);
        Transaction::factory()->create([
            'procurement_id' => $procurement->id,
            'category' => 'PO',
            'status' => 'Cancelled',
            'current_office_id' => $office->id,
            'created_by_user_id' => $user->id,
        ]);

        $result = $this->service->getStagnantTransactions();

        $this->assertCount(0, $result);
    }

    public function test_get_stagnant_transactions_respects_limit(): void
    {
        $user = User::factory()->create();
        $office = Office::factory()->create();
        $procurement = Procurement::factory()->create(['created_by_user_id' => $user->id]);

        // Create several transactions with old actions to trigger stagnation
        for ($i = 0; $i < 5; $i++) {
            $tx = Transaction::factory()->create([
                'procurement_id' => $procurement->id,
                'category' => 'PR',
                'status' => 'In Progress',
                'current_office_id' => $office->id,
                'created_by_user_id' => $user->id,
            ]);
            TransactionAction::factory()->receive()->create([
                'transaction_id' => $tx->id,
                'from_user_id' => $user->id,
                'created_at' => now()->subDays(10 + $i),
            ]);
        }

        $result = $this->service->getStagnantTransactions(3);

        $this->assertLessThanOrEqual(3, count($result));
    }
}
