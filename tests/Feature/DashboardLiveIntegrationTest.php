<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Office;
use App\Models\Procurement;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\Transaction;
use App\Models\TransactionAction;
use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowStep;
use App\Services\DashboardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DashboardLiveIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private User $viewer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 10, 14)->setTime(10, 0));
        $this->seed(\Database\Seeders\RoleSeeder::class);
        $this->viewer = User::factory()->create(['selected_year' => 2026]);
        $this->viewer->assignRole('Viewer');
        $this->actingAs($this->viewer);
    }

    public function test_empty_selected_year_does_not_leak_workload_from_another_year(): void
    {
        $office = Office::factory()->create();
        $workflow = Workflow::factory()->pr()->create();
        WorkflowStep::factory()->forWorkflow($workflow)->forOffice($office)->create();
        Transaction::factory()->create(['current_office_id' => $office->id]);
        $this->viewer->update(['selected_year' => 2025]);

        $this->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
            ->where('officeWorkload.0.total', 0)
            ->where('summary.purchase_requests.total', 0)
            ->where('documents.total', 0)
            ->where('attentionMeta.total', 0));
    }

    public function test_office_scope_applies_to_documents_counts_workload_and_procurements(): void
    {
        $office = Office::factory()->create(['is_active' => false]);
        $otherOffice = Office::factory()->create();
        $procurement = Procurement::factory()->create();
        foreach (['PR', 'PO'] as $category) {
            Transaction::factory()->create([
                'procurement_id' => $procurement->id,
                'current_office_id' => $office->id,
                'category' => $category,
            ]);
        }
        Transaction::factory()->create(['current_office_id' => $otherOffice->id, 'category' => 'VCH']);

        $this->get(route('dashboard', ['office_id' => $office->id]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.office_id', $office->id)
                ->where('summary.procurements.total', 1)
                ->where('summary.purchase_requests.total', 1)
                ->where('summary.purchase_orders.total', 1)
                ->where('summary.vouchers.total', 0)
                ->has('officeWorkload', 1)
                ->where('officeWorkload.0.office_id', $office->id)
                ->where('officeWorkload.0.total', 2)
                ->where('documents.total', 2)
                ->where('offices', fn ($offices) => collect($offices)->contains('id', $office->id)));
    }

    public function test_register_uses_entity_routes_and_full_assigned_workflow(): void
    {
        $office = Office::factory()->create(['name' => 'Municipal Budget Office', 'abbreviation' => 'MBO']);
        $workflow = Workflow::factory()->pr()->create();
        $step = WorkflowStep::factory()->forWorkflow($workflow)->forOffice($office)->create(['expected_days' => 3]);
        $transaction = Transaction::factory()->create([
            'category' => 'PR', 'workflow_id' => $workflow->id,
            'current_step_id' => $step->id, 'current_office_id' => $office->id,
        ]);
        $pr = PurchaseRequest::factory()->create(['transaction_id' => $transaction->id]);

        $this->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
            ->where('documents.data.0.id', $transaction->id)
            ->where('documents.data.0.url', route('purchase-requests.show', $pr->id))
            ->where('documents.data.0.current_office_name', $office->name)
            ->where('documents.data.0.current_office_abbreviation', 'MBO')
            ->where('documents.data.0.current_step_id', $step->id)
            ->where('documents.data.0.workflow_steps.0.office_name', $office->name)
            ->where('documents.data.0.workflow_steps.0.office_id', $office->id)
            ->where('documents.data.0.workflow_steps.0.expected_days', 3));

        $office->delete();
        $this->get(route('dashboard', ['search' => 'MBO']))->assertInertia(fn (Assert $page) => $page
            ->where('documents.total', 1)
            ->where('documents.data.0.current_office_name', $office->name)
            ->where('documents.data.0.workflow_steps.0.office_name', $office->name)
            ->where('offices', fn ($offices) => collect($offices)->contains('id', $office->id)));

        $pr->delete();
        $this->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
            ->where('documents.data.0.url', null));
    }

    public function test_register_filters_and_pagination_run_on_server(): void
    {
        $office = Office::factory()->create(['abbreviation' => 'MBO']);
        Transaction::factory()->recycle($office)->count(12)->create(['category' => 'PR', 'current_office_id' => $office->id]);
        Transaction::factory()->create(['category' => 'PO', 'current_office_id' => $office->id]);
        Transaction::factory()->create(['category' => 'PR', 'status' => 'Completed', 'current_office_id' => $office->id]);

        $query = ['category' => 'PR', 'search' => 'MBO', 'active_only' => 1, 'page' => 2];
        $this->get(route('dashboard', $query))->assertInertia(fn (Assert $page) => $page
            ->where('documents.total', 12)
            ->where('documents.current_page', 2)
            ->has('documents.data', 2)
            ->where('documents.data.0.category', 'PR')
            ->where('filters.active_only', true)
            ->where('filters.search', 'MBO')
            ->where('summary.purchase_requests.total', 13));
    }

    public function test_deleted_transactions_and_procurements_are_excluded_from_dashboard(): void
    {
        $office = Office::factory()->create();
        $deletedTransaction = Transaction::factory()->create(['category' => 'PR', 'current_office_id' => $office->id]);
        $deletedProcurementTransaction = Transaction::factory()->create(['category' => 'PR', 'current_office_id' => $office->id]);
        foreach ([$deletedTransaction, $deletedProcurementTransaction] as $transaction) {
            TransactionAction::factory()->endorse()->outOfWorkflow()->create([
                'transaction_id' => $transaction->id, 'from_office_id' => $office->id,
            ]);
        }
        $deletedTransaction->delete();
        $deletedProcurementTransaction->procurement->delete();

        $this->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
            ->where('summary.purchase_requests.total', 0)
            ->where('documents.total', 0)
            ->has('activityFeed', 0)
            ->where('annualIncidentCount', 0)
            ->where('attentionMeta.total', 0));
    }

    public function test_attention_distinguishes_idle_awaiting_receipt_from_workflow_overdue_and_is_not_truncated(): void
    {
        $office = Office::factory()->create();
        $workflow = Workflow::factory()->pr()->create();
        $step = WorkflowStep::factory()->forWorkflow($workflow)->forOffice($office)->create(['expected_days' => 2]);
        $idle = Transaction::factory()->create([
            'category' => 'PR', 'current_office_id' => $office->id,
            'workflow_id' => $workflow->id, 'current_step_id' => $step->id,
        ]);
        TransactionAction::factory()->endorse()->create(['transaction_id' => $idle->id, 'created_at' => now()->subDays(5)]);
        $overdue = Transaction::factory()->create([
            'category' => 'PO', 'current_office_id' => $office->id, 'status' => 'In Progress',
            'workflow_id' => $workflow->id, 'current_step_id' => $step->id,
            'received_at' => now()->subDays(9),
        ]);
        $po = PurchaseOrder::factory()->create(['transaction_id' => $overdue->id]);
        for ($i = 0; $i < 25; $i++) {
            $transaction = Transaction::factory()->recycle($office)->create(['current_office_id' => $office->id]);
            TransactionAction::factory()->recycle($office)->endorse()->create(['transaction_id' => $transaction->id, 'created_at' => now()->subDays(5)]);
        }

        $this->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
            ->where('attentionMeta.total', 27)
            ->where('attentionMeta.overdue_count', 1)
            ->where('attentionMeta.idle_count', 26)
            ->where('attentionMeta.idle_threshold_days', 2)
            ->has('stagnantTransactions', 27)
            ->where('stagnantTransactions.0.id', $overdue->id)
            ->where('stagnantTransactions.0.reason', 'overdue')
            ->where('stagnantTransactions.0.expected_days', 2)
            ->where('stagnantTransactions.0.status', 'In Progress')
            ->where('stagnantTransactions.0.current_step_order', 1)
            ->where('stagnantTransactions.0.total_steps', 1)
            ->where('stagnantTransactions.0.delay_days', 5)
            ->where('stagnantTransactions.0.url', route('purchase-orders.show', $po->id))
            ->where('stagnantTransactions.1.id', $idle->id)
            ->where('stagnantTransactions.1.reason', 'idle')
            ->where('stagnantTransactions.1.awaiting_receipt', true)
            ->where('stagnantTransactions.1.days_at_current_step', 0)
            ->where('stagnantTransactions.1.idle_days', 3));
    }

    public function test_activity_is_office_scoped_before_limit_and_annual_incidents_use_selected_year(): void
    {
        $office = Office::factory()->create();
        $other = Office::factory()->create();
        $transaction = Transaction::factory()->create();
        $matching = TransactionAction::factory()->endorse()->outOfWorkflow()->create([
            'transaction_id' => $transaction->id, 'from_office_id' => $office->id,
            'to_office_id' => $other->id, 'created_at' => now()->subDays(60),
        ]);
        TransactionAction::factory()->endorse()->count(26)->create([
            'transaction_id' => $transaction->id, 'from_office_id' => $other->id,
            'to_office_id' => $other->id,
        ]);
        TransactionAction::factory()->endorse()->outOfWorkflow()->create([
            'transaction_id' => $transaction->id, 'from_office_id' => $office->id,
            'created_at' => now()->subYear(),
        ]);

        $this->get(route('dashboard', ['office_id' => $office->id]))->assertInertia(fn (Assert $page) => $page
            ->has('activityFeed', 1)
            ->where('activityFeed.0.id', $matching->id)
            ->where('activityFeed.0.from_office_name', $office->name)
            ->where('activityFeed.0.from_office_id', $office->id)
            ->where('annualIncidentCount', 1));
    }

    public function test_turnaround_excludes_receipts_in_later_years_and_other_offices(): void
    {
        $office = Office::factory()->create();
        $other = Office::factory()->create();
        $transaction = Transaction::factory()->create();
        foreach ([[$office, '2026-01-05', '2026-01-06'], [$other, '2026-01-05', '2026-01-07'], [$office, '2027-01-04', '2027-01-15']] as [$atOffice, $received, $endorsed]) {
            TransactionAction::factory()->receive()->create([
                'transaction_id' => $transaction->id, 'to_office_id' => $atOffice->id, 'created_at' => $received,
            ]);
            TransactionAction::factory()->endorse()->create([
                'transaction_id' => $transaction->id, 'from_office_id' => $atOffice->id, 'created_at' => $endorsed,
            ]);
        }

        $metrics = app(DashboardService::class)->getOfficePerformance(365, 2026, $office->id);
        $this->assertCount(1, $metrics);
        $this->assertSame(1, $metrics[0]['actions_count']);
        $this->assertEquals(1, $metrics[0]['avg_turnaround_days']);
    }

    public function test_only_valid_active_configured_announcements_are_returned(): void
    {
        $base = ['id' => 'notice-1', 'kind' => 'update', 'title' => 'Service notice', 'message' => 'Published information'];
        config(['opts.announcements' => [
            $base + ['starts_at' => '2026-10-14 09:00:00', 'ends_at' => '2026-10-14 11:00:00', 'publisher' => 'MIS Office', 'audience' => 'All offices'],
            array_replace($base, ['id' => 'future', 'starts_at' => '2026-10-15 00:00:00']),
            array_replace($base, ['id' => 'expired', 'ends_at' => '2026-10-14 10:00:00']),
            array_replace($base, ['id' => 'invalid', 'starts_at' => 'not a date']),
            array_replace($base, ['id' => 'bad-kind', 'kind' => 'unknown']),
            array_replace($base, ['id' => 'bad-detail', 'detail' => ['invalid']]),
            'invalid entry',
        ]]);

        $this->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
            ->has('announcements', 1)->where('announcements.0.id', 'notice-1')
            ->where('announcements.0.publisher', 'MIS Office')
            ->where('announcements.0.audience', 'All offices'));
    }

    public function test_default_announcements_are_empty_and_viewer_has_no_processing_rights(): void
    {
        $transaction = Transaction::factory()->create();
        $this->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
            ->has('announcements', 0)
            ->where('auth.user.can_process_transactions', false));
        $this->post(route('transactions.receive.store', $transaction))->assertForbidden();
    }

    public function test_reference_sort_applies_globally_before_pagination(): void
    {
        $office = Office::factory()->create();
        for ($reference = 13; $reference >= 1; $reference--) {
            Transaction::factory()->recycle($office)->create(['reference_number' => sprintf('PR-SORT-%03d', $reference)]);
        }

        $this->get(route('dashboard', ['sort' => 'reference_asc', 'page' => 2]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.sort', 'reference_asc')->where('documents.total', 13)
                ->has('documents.data', 3)
                ->where('documents.data.0.reference_number', 'PR-SORT-011')
                ->where('documents.data.2.reference_number', 'PR-SORT-013'));
        $this->get(route('dashboard', ['sort' => 'reference_desc']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('documents.data.0.reference_number', 'PR-SORT-013')
                ->where('documents.data.9.reference_number', 'PR-SORT-004'));
        $this->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
            ->where('filters.sort', 'newest')
            ->where('documents.data.0.reference_number', 'PR-SORT-001'));
    }

    public function test_selected_document_is_available_outside_register_filters_and_page(): void
    {
        $office = Office::factory()->create();
        $otherOffice = Office::factory()->create();
        $transaction = Transaction::factory()->create([
            'category' => 'PR', 'current_office_id' => $office->id, 'created_at' => now()->subYear(),
        ]);
        $purchaseRequest = PurchaseRequest::factory()->create(['transaction_id' => $transaction->id]);

        $this->get(route('dashboard', [
            'document_id' => $transaction->id, 'office_id' => $otherOffice->id, 'category' => 'PO', 'search' => 'unmatched',
        ]))->assertInertia(fn (Assert $page) => $page
            ->where('documents.total', 0)
            ->where('selectedDocument.id', $transaction->id)
            ->where('selectedDocument.current_office_name', $office->name)
            ->where('selectedDocument.url', route('purchase-requests.show', $purchaseRequest->id)));
        $this->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
            ->where('selectedDocument', null));
    }

    public function test_selected_deleted_transaction_or_procurement_returns_null(): void
    {
        $transaction = Transaction::factory()->create();
        $transaction->delete();
        $this->get(route('dashboard', ['document_id' => $transaction->id]))
            ->assertInertia(fn (Assert $page) => $page->where('selectedDocument', null));
        $transaction->restore();
        $transaction->procurement->delete();
        $this->get(route('dashboard', ['document_id' => $transaction->id]))
            ->assertInertia(fn (Assert $page) => $page->where('selectedDocument', null));
    }

    public function test_pending_documents_require_processing_eligibility_and_match_office_and_receipt_state_across_years(): void
    {
        $office = Office::factory()->create();
        $otherOffice = Office::factory()->create();
        $endorser = User::factory()->create(['office_id' => $office->id, 'selected_year' => 2026]);
        $endorser->assignRole('Endorser');
        $pending = Transaction::factory()->create([
            'category' => 'PR', 'current_office_id' => $office->id, 'status' => 'In Progress',
            'received_at' => null, 'created_at' => now()->subYear(),
        ]);
        $purchaseRequest = PurchaseRequest::factory()->create(['transaction_id' => $pending->id]);
        foreach ([
            ['current_office_id' => $otherOffice->id, 'status' => 'In Progress', 'received_at' => null],
            ['current_office_id' => $office->id, 'status' => 'Created', 'received_at' => null],
            ['current_office_id' => $office->id, 'status' => 'Completed', 'received_at' => null],
            ['current_office_id' => $office->id, 'status' => 'In Progress', 'received_at' => now()],
        ] as $state) {
            Transaction::factory()->create($state);
        }

        $this->actingAs($endorser)->get(route('dashboard', ['office_id' => $otherOffice->id]))
            ->assertInertia(fn (Assert $page) => $page
                ->has('pendingDocuments', 1)->where('pendingDocuments.0.id', $pending->id)
                ->where('pendingDocuments.0.url', route('purchase-requests.show', $purchaseRequest->id))
                ->where('pendingReceiptsCount', 1));
        $this->actingAs($this->viewer)->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page->has('pendingDocuments', 0));
        $endorser->update(['is_active' => false]);
        $this->actingAs($endorser)->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page->has('pendingDocuments', 0));
        $endorser->update(['is_active' => true]);
        $office->update(['is_active' => false]);
        $endorser->unsetRelation('office');
        $this->actingAs($endorser)->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page->has('pendingDocuments', 0));
    }

    public function test_pending_documents_are_capped_at_fifty_and_exclude_deleted_records(): void
    {
        $office = Office::factory()->create();
        $endorser = User::factory()->create(['office_id' => $office->id]);
        $endorser->assignRole('Endorser');
        Transaction::factory()->recycle($office)->count(51)->create([
            'current_office_id' => $office->id, 'status' => 'In Progress', 'received_at' => null,
        ]);
        $deleted = Transaction::factory()->create([
            'current_office_id' => $office->id, 'status' => 'In Progress', 'received_at' => null,
        ]);
        $deleted->delete();
        $deletedParent = Transaction::factory()->create([
            'current_office_id' => $office->id, 'status' => 'In Progress', 'received_at' => null,
        ]);
        $deletedParent->procurement->delete();

        $this->actingAs($endorser)->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
            ->has('pendingDocuments', 50)
            ->where('pendingDocuments', fn ($rows) => ! collect($rows)->contains('id', $deleted->id)
                && ! collect($rows)->contains('id', $deletedParent->id)));
    }

    public function test_next_action_preserves_existing_endorsement_receipt_completion_and_role_checks(): void
    {
        $office = Office::factory()->create();
        $nextOffice = Office::factory()->create(['abbreviation' => 'MTO']);
        $workflow = Workflow::factory()->pr()->create();
        $first = WorkflowStep::factory()->forWorkflow($workflow)->forOffice($office)->order(1)->create();
        $final = WorkflowStep::factory()->forWorkflow($workflow)->forOffice($nextOffice)->order(2)->final()->create();
        $endorser = User::factory()->create(['office_id' => $office->id]);
        $endorser->assignRole('Endorser');
        $transaction = Transaction::factory()->create([
            'category' => 'PR', 'current_office_id' => $office->id,
            'current_step_id' => $first->id, 'workflow_id' => $workflow->id,
        ]);
        $purchaseRequest = PurchaseRequest::factory()->create(['transaction_id' => $transaction->id]);
        $documentRoute = route('purchase-requests.show', $purchaseRequest->id);
        $selectionRoute = route('dashboard', ['document_id' => $transaction->id]);

        $this->get($selectionRoute)->assertInertia(fn (Assert $page) => $page
            ->where('selectedDocument.next_action', null)
            ->where('documents.data.0.next_action', null));
        $this->actingAs($endorser)->get($selectionRoute)->assertInertia(fn (Assert $page) => $page
            ->where('selectedDocument.next_action.label', 'Endorse to MTO')
            ->where('selectedDocument.next_action.url', route('transactions.endorse.create', $transaction->id))
            ->where('documents.data.0.next_action.label', 'Endorse to MTO'));
        $transaction->update(['status' => 'In Progress', 'received_at' => null]);
        $this->get($selectionRoute)->assertInertia(fn (Assert $page) => $page
            ->where('selectedDocument.next_action.label', 'Review receipt')
            ->where('selectedDocument.next_action.url', $documentRoute)
            ->where('pendingDocuments.0.next_action.label', 'Review receipt'));
        $finalEndorser = User::factory()->create(['office_id' => $nextOffice->id]);
        $finalEndorser->assignRole('Endorser');
        $transaction->update(['current_step_id' => $final->id, 'current_office_id' => $nextOffice->id, 'received_at' => now()]);
        $this->actingAs($finalEndorser)->get($selectionRoute)->assertInertia(fn (Assert $page) => $page
            ->where('selectedDocument.next_action.label', 'Review completion')
            ->where('selectedDocument.next_action.url', $documentRoute));
        $transaction->update(['status' => 'Completed']);
        $this->get($selectionRoute)->assertInertia(fn (Assert $page) => $page
            ->where('selectedDocument.next_action', null));
        $transaction->update(['status' => 'Created', 'current_step_id' => $first->id, 'current_office_id' => $office->id]);
        $this->get($selectionRoute)->assertInertia(fn (Assert $page) => $page
            ->where('selectedDocument.next_action', null));
    }

    public function test_selected_and_pending_old_year_documents_retain_their_own_attention_metadata(): void
    {
        $office = Office::factory()->create();
        $otherOffice = Office::factory()->create();
        $workflow = Workflow::factory()->pr()->create();
        $step = WorkflowStep::factory()->forWorkflow($workflow)->forOffice($office)->create(['expected_days' => 2]);
        $transaction = Transaction::factory()->create([
            'category' => 'PR', 'status' => 'In Progress', 'current_office_id' => $office->id,
            'current_step_id' => $step->id, 'workflow_id' => $workflow->id,
            'created_at' => now()->subYear(), 'received_at' => now()->subDays(9),
        ]);
        PurchaseRequest::factory()->create(['transaction_id' => $transaction->id]);
        TransactionAction::factory()->receive()->create([
            'transaction_id' => $transaction->id, 'created_at' => now()->subDays(9),
        ]);

        $this->get(route('dashboard', ['office_id' => $otherOffice->id, 'document_id' => $transaction->id]))
            ->assertInertia(fn (Assert $page) => $page
                ->has('stagnantTransactions', 0)->where('documents.total', 0)
                ->where('selectedDocument.attention.id', $transaction->id)
                ->where('selectedDocument.attention.reason', 'overdue')
                ->where('selectedDocument.attention.delay_days', 5)
                ->where('selectedDocument.attention.expected_days', 2));

        $transaction->update(['received_at' => null]);
        $endorser = User::factory()->create(['office_id' => $office->id]);
        $endorser->assignRole('Endorser');
        $this->actingAs($endorser)->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
            ->has('stagnantTransactions', 0)->where('documents.total', 0)
            ->where('pendingDocuments.0.id', $transaction->id)
            ->where('pendingDocuments.0.attention.reason', 'idle')
            ->where('pendingDocuments.0.attention.awaiting_receipt', true)
            ->where('pendingDocuments.0.attention.delay_days', 0)
            ->where('pendingDocuments.0.attention.days_at_current_step', 0));

        foreach (['On Hold', 'Cancelled', 'Completed'] as $status) {
            $transaction->update(['status' => $status]);
            $this->get(route('dashboard', ['document_id' => $transaction->id]))
                ->assertInertia(fn (Assert $page) => $page->where('selectedDocument.attention', null));
        }
    }

    public function test_queue_filters_do_not_change_background_dashboard_filters_counts_or_register(): void
    {
        $office = Office::factory()->create();
        $otherOffice = Office::factory()->create();
        Transaction::factory()->recycle($office)->create([
            'category' => 'PR', 'reference_number' => 'QUEUE-PR-001', 'current_office_id' => $office->id,
        ]);
        Transaction::factory()->recycle($office)->create([
            'category' => 'PR', 'reference_number' => 'OTHER-PR-001', 'current_office_id' => $office->id,
        ]);
        Transaction::factory()->recycle($office)->create([
            'category' => 'PR', 'reference_number' => 'QUEUE-COMPLETED-001', 'current_office_id' => $office->id, 'status' => 'Completed',
        ]);
        Transaction::factory()->recycle($office)->create(['category' => 'PO', 'current_office_id' => $office->id]);
        Transaction::factory()->recycle($otherOffice)->create(['category' => 'PR', 'current_office_id' => $otherOffice->id]);

        $this->get(route('dashboard', [
            'queue_office_id' => $office->id, 'queue_category' => 'PR', 'queue_search' => 'QUEUE',
        ]))->assertInertia(fn (Assert $page) => $page
            ->where('filters.office_id', null)->where('filters.category', 'all')
            ->where('filters.search', '')->where('filters.active_only', false)
            ->where('summary.purchase_requests.total', 4)->where('summary.purchase_orders.total', 1)
            ->where('documents.total', 5)->has('officeWorkload', 2)
            ->where('queueFilters.office_id', $office->id)->where('queueFilters.category', 'PR')
            ->where('queueFilters.search', 'QUEUE')->where('queueFilters.active_only', true)
            ->where('queueDocuments.total', 1)
            ->where('queueDocuments.data.0.reference_number', 'QUEUE-PR-001'));
    }

    public function test_queue_pagination_uses_queue_page_independently_of_register_page(): void
    {
        $office = Office::factory()->create();
        for ($reference = 1; $reference <= 11; $reference++) {
            Transaction::factory()->recycle($office)->create([
                'category' => 'PR', 'reference_number' => sprintf('QUEUE-%03d', $reference), 'current_office_id' => $office->id,
            ]);
        }
        $query = ['queue_office_id' => $office->id, 'queue_sort' => 'reference_asc', 'queue_page' => 2, 'page' => 1];
        $this->get(route('dashboard', $query))->assertInertia(fn (Assert $page) => $page
            ->where('documents.current_page', 1)->has('documents.data', 10)
            ->where('queueDocuments.current_page', 2)->has('queueDocuments.data', 1)
            ->where('queueDocuments.total', 11)
            ->where('queueDocuments.data.0.reference_number', 'QUEUE-011'));
        $this->get(route('dashboard', [...$query, 'queue_page' => 1, 'page' => 2]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('documents.current_page', 2)->has('documents.data', 1)
                ->where('queueDocuments.current_page', 1)->has('queueDocuments.data', 10)
                ->where('queueDocuments.data.0.reference_number', 'QUEUE-001'));
    }

    public function test_queue_props_are_null_until_requested_and_can_be_loaded_independently(): void
    {
        $office = Office::factory()->create();
        Transaction::factory()->recycle($office)->create(['current_office_id' => $office->id]);
        $response = $this->get(route('dashboard'));
        $response->assertInertia(fn (Assert $page) => $page
            ->where('queueFilters', null)->where('queueDocuments', null));
        $this->get(route('dashboard', ['queue_office_id' => $office->id]), [
            'X-Inertia' => 'true', 'X-Inertia-Version' => $response->viewData('page')['version'],
            'X-Inertia-Partial-Component' => 'Dashboard',
            'X-Inertia-Partial-Data' => 'queueFilters,queueDocuments',
        ])->assertOk()->assertJsonPath('props.queueFilters.office_id', $office->id)
            ->assertJsonPath('props.queueDocuments.total', 1)->assertJsonMissingPath('props.documents');
    }

    public function test_invalid_dashboard_filters_are_rejected(): void
    {
        foreach ([['office_id' => 999999], ['category' => 'bad'], ['search' => str_repeat('a', 101)], ['active_only' => 'bad'], ['page' => 0], ['sort' => 'bad'], ['document_id' => 999999], ['document_id' => 'bad'], ['queue_office_id' => 999999], ['queue_category' => 'bad'],
            ['queue_search' => str_repeat('a', 101)], ['queue_sort' => 'bad'], ['queue_active_only' => 'bad'], ['queue_page' => 0],
        ] as $query) {
            $this->getJson(route('dashboard', $query))->assertUnprocessable();
        }
    }
}
