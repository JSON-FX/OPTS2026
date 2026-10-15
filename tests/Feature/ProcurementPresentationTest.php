<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Office;
use App\Models\Particular;
use App\Models\Procurement;
use App\Models\ProcurementStatusHistory;
use App\Models\PurchaseRequest;
use App\Models\Transaction;
use App\Models\TransactionAction;
use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowStep;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ProcurementPresentationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['Viewer', 'Endorser', 'Administrator'] as $name) {
            Role::create(['name' => $name, 'guard_name' => 'web']);
        }
    }

    private function user(string $role = 'Viewer'): User
    {
        $user = User::factory()->create(['selected_year' => 2026]);
        $user->assignRole($role);

        return $user;
    }

    public function test_procurement_redirect_feedback_is_shared_with_the_workspace(): void
    {
        $this->actingAs($this->user())->withSession(['success' => 'Procurement created successfully.'])
            ->get(route('procurements.index'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('flash.success', 'Procurement created successfully.')
                ->where('flash.error', null));
    }

    public function test_form_date_matches_server_validation_near_manila_midnight(): void
    {
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-10-14 18:00:00', 'UTC'));
        $this->actingAs($this->user('Endorser'))->get(route('procurements.create'))
            ->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('formOptions.today', today()->toDateString()));
        $this->travelBack();
    }

    public function test_register_searches_real_document_reference_and_office_abbreviation(): void
    {
        $user = $this->user();
        $office = Office::factory()->create(['name' => 'Municipal Budget Office', 'abbreviation' => 'MBO']);
        $procurement = Procurement::factory()->create(['end_user_id' => $office->id, 'created_at' => '2026-01-01']);
        $transaction = Transaction::factory()->create([
            'procurement_id' => $procurement->id, 'category' => 'PR', 'reference_number' => 'PR-REAL-001',
        ]);
        $pr = PurchaseRequest::factory()->create(['transaction_id' => $transaction->id]);
        Procurement::factory()->create(['created_at' => '2026-01-02']);

        foreach (['MBO', 'PR-REAL-001'] as $search) {
            $this->actingAs($user)->get(route('procurements.index', ['search' => $search]))
                ->assertOk()->assertInertia(fn (Assert $page) => $page
                ->has('procurements.data', 1)
                ->where('procurements.data.0.id', $procurement->id)
                ->where('procurements.data.0.documents.0.reference_number', 'PR-REAL-001')
                ->where('procurements.data.0.documents.0.url', route('purchase-requests.show', $pr))
                ->where('procurements.data.0.documents.0.next_action', null));
        }
    }

    public function test_register_counts_are_global_to_selected_year_and_sorting_and_page_size_are_server_driven(): void
    {
        $user = $this->user();
        Procurement::factory()->create(['created_at' => '2025-01-01', 'abc_amount' => 999999, 'status' => 'Cancelled']);
        $newest = Procurement::factory()->create(['created_at' => '2026-10-01', 'abc_amount' => 1, 'status' => 'Cancelled']);
        $oldest = Procurement::factory()->create(['created_at' => '2026-01-01', 'abc_amount' => 999, 'status' => 'Completed']);
        Procurement::factory()->count(12)->create(['created_at' => '2026-02-01', 'abc_amount' => 20, 'status' => 'Created']);

        $this->actingAs($user)->get(route('procurements.index', ['sort' => 'oldest', 'per_page' => 12]))
            ->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('procurements.data', 12)
            ->where('procurements.total', 14)
            ->where('procurements.data.0.id', $oldest->id)
            ->where('filters.sort', 'oldest')->where('filters.per_page', 12));
        $this->get(route('procurements.index', ['sort' => 'amount', 'status' => 'Completed']))
            ->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('procurements.data.0.id', $oldest->id)
            ->where('statusCounts.total', 14)->where('statusCounts.Cancelled', 1)
            ->where('statusCounts.Created', 12)->where('statusCounts.Completed', 1));
        $this->get(route('procurements.index', ['sort' => 'amount_asc', 'per_page' => 999]))
            ->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('procurements.data.0.id', $newest->id)->where('filters.per_page', 6));
    }

    public function test_form_options_use_only_active_sso_offices_and_active_particulars_and_keep_historical_labels(): void
    {
        $user = $this->user('Endorser');
        $active = Office::factory()->create(['sso_id' => 100, 'is_active' => true, 'abbreviation' => 'ACTIVE']);
        $historical = Office::factory()->create(['sso_id' => null, 'is_active' => false, 'abbreviation' => 'OLD']);
        $historical->delete();
        $inactive = Particular::factory()->create(['is_active' => false]);
        $activeParticular = Particular::factory()->create(['is_active' => true]);
        $procurement = Procurement::factory()->create([
            'end_user_id' => $historical->id, 'particular_id' => $inactive->id, 'created_at' => '2026-01-01',
        ]);

        $this->actingAs($user)->get(route('procurements.index'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('procurements.data.0.end_user.abbreviation', 'OLD')
                ->where('formOptions.offices', fn ($offices) => collect($offices)->pluck('id')->all() === [$active->id])
                ->where('formOptions.particulars', fn ($particulars) => collect($particulars)->pluck('id')->all() === [$activeParticular->id]));
        $this->get(route('procurements.edit', $procurement))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Procurements/Show')
                ->where('initialModal', 'edit')->where('procurement.end_user.abbreviation', 'OLD'));
        $this->get(route('procurements.create'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Procurements/Index')->where('initialModal', 'create'));
    }

    public function test_detail_has_real_workflow_route_full_history_and_timeline_document_link(): void
    {
        $user = $this->user();
        $office = Office::factory()->create();
        $procurement = Procurement::factory()->create();
        $workflow = Workflow::factory()->create(['category' => 'PR']);
        $step = WorkflowStep::factory()->create(['workflow_id' => $workflow->id, 'office_id' => $office->id, 'step_order' => 1]);
        $tx = Transaction::factory()->create([
            'procurement_id' => $procurement->id, 'category' => 'PR', 'workflow_id' => $workflow->id,
            'current_step_id' => $step->id, 'current_office_id' => $office->id,
        ]);
        $pr = PurchaseRequest::factory()->create(['transaction_id' => $tx->id]);
        $action = TransactionAction::factory()->create(['transaction_id' => $tx->id, 'to_office_id' => $office->id]);
        $office->delete();
        for ($i = 0; $i < 8; $i++) {
            ProcurementStatusHistory::create([
                'procurement_id' => $procurement->id, 'new_status' => 'Created',
                'changed_by_user_id' => $user->id, 'created_at' => now()->subDays($i), 'reason' => 'Reason '.$i,
            ]);
        }

        $this->actingAs($user)->get(route('procurements.show', $procurement))->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('procurement.status_history', 8)
                ->where('procurement.status_history.0.reason', 'Reason 0')
                ->where('procurement.documents.0.url', route('purchase-requests.show', $pr))
                ->where('procurement.documents.0.workflow_steps.0.office_name', $office->name)
                ->where('activityTimeline.0.id', $action->id)
                ->where('activityTimeline.0.transaction_id', $tx->id)
                ->where('activityTimeline.0.url', route('purchase-requests.show', $pr))
                ->where('activityTimeline.0.to_office.name', $office->name));
    }

    public function test_document_summaries_are_loaded_in_bulk_without_transaction_appends(): void
    {
        $user = $this->user();
        $procurements = Procurement::factory()->count(6)->create(['created_at' => '2026-01-01']);
        foreach ($procurements as $procurement) {
            $tx = Transaction::factory()->create(['procurement_id' => $procurement->id, 'category' => 'PR']);
            PurchaseRequest::factory()->create(['transaction_id' => $tx->id]);
        }
        DB::enableQueryLog();
        $this->actingAs($user)->get(route('procurements.index'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('procurements.data', 6)
            ->missing('procurements.data.0.documents.0.eta_completion'));
        $documentQueries = collect(DB::getQueryLog())->filter(fn ($entry) => str_contains($entry['query'], 'from "purchase_requests"'));
        DB::disableQueryLog();
        $this->assertCount(1, $documentQueries);
    }

    public function test_viewer_cannot_mutate_procurements_and_archiving_keeps_linked_documents(): void
    {
        $viewer = $this->user();
        $procurement = Procurement::factory()->create();
        $tx = Transaction::factory()->create(['procurement_id' => $procurement->id]);
        $this->actingAs($viewer)->get(route('procurements.edit', $procurement))->assertForbidden();
        $this->post(route('procurements.store'), [])->assertForbidden();
        $this->put(route('procurements.update', $procurement), [])->assertForbidden();
        $this->delete(route('procurements.destroy', $procurement))->assertForbidden();
        $this->actingAs($this->user('Endorser'))->delete(route('procurements.destroy', $procurement))
            ->assertRedirect(route('procurements.index'));
        $this->assertSoftDeleted($procurement);
        $this->assertNotSoftDeleted($tx);
    }
}
