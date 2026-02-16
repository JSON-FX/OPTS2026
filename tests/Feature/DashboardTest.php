<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Office;
use App\Models\Procurement;
use App\Models\Transaction;
use App\Models\TransactionAction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Feature tests for Dashboard (Story 4.1.1).
 */
class DashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RoleSeeder::class);
    }

    public function test_dashboard_loads_for_viewer(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Viewer');

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->has('summary')
                ->has('officeWorkload')
                ->has('userOfficeId')
            );
    }

    public function test_dashboard_loads_for_endorser(): void
    {
        $office = Office::factory()->create();
        $user = User::factory()->create(['office_id' => $office->id]);
        $user->assignRole('Endorser');

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('userOfficeId', $office->id)
            );
    }

    public function test_dashboard_loads_for_administrator(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Administrator');

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
            );
    }

    public function test_dashboard_requires_authentication(): void
    {
        $this->get(route('dashboard'))
            ->assertRedirect(route('login'));
    }

    public function test_summary_cards_show_correct_procurement_counts(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Viewer');

        Procurement::factory()->count(3)->create(['status' => 'Created', 'created_by_user_id' => $user->id]);
        Procurement::factory()->count(2)->create(['status' => 'In Progress', 'created_by_user_id' => $user->id]);
        Procurement::factory()->create(['status' => 'Completed', 'created_by_user_id' => $user->id]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('summary.procurements.total', 6)
                ->where('summary.procurements.created', 3)
                ->where('summary.procurements.in_progress', 2)
                ->where('summary.procurements.completed', 1)
                ->where('summary.procurements.on_hold', 0)
                ->where('summary.procurements.cancelled', 0)
            );
    }

    public function test_summary_cards_show_correct_transaction_counts(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Viewer');
        $procurement = Procurement::factory()->create(['created_by_user_id' => $user->id]);

        Transaction::factory()->count(2)->create([
            'procurement_id' => $procurement->id,
            'category' => 'PR',
            'status' => 'In Progress',
            'created_by_user_id' => $user->id,
        ]);
        Transaction::factory()->create([
            'procurement_id' => $procurement->id,
            'category' => 'PO',
            'status' => 'Created',
            'created_by_user_id' => $user->id,
        ]);
        Transaction::factory()->create([
            'procurement_id' => $procurement->id,
            'category' => 'VCH',
            'status' => 'Completed',
            'created_by_user_id' => $user->id,
        ]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('summary.purchase_requests.total', 2)
                ->where('summary.purchase_requests.in_progress', 2)
                ->where('summary.purchase_orders.total', 1)
                ->where('summary.purchase_orders.created', 1)
                ->where('summary.vouchers.total', 1)
                ->where('summary.vouchers.completed', 1)
            );
    }

    public function test_office_workload_shows_active_transactions(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Viewer');
        $office = Office::factory()->create(['name' => 'Test Office']);
        $procurement = Procurement::factory()->create(['created_by_user_id' => $user->id]);

        Transaction::factory()->create([
            'procurement_id' => $procurement->id,
            'category' => 'PR',
            'status' => 'In Progress',
            'current_office_id' => $office->id,
            'created_by_user_id' => $user->id,
        ]);
        Transaction::factory()->create([
            'procurement_id' => $procurement->id,
            'category' => 'PO',
            'status' => 'In Progress',
            'current_office_id' => $office->id,
            'created_by_user_id' => $user->id,
        ]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('officeWorkload', 1)
                ->where('officeWorkload.0.office_name', 'Test Office')
                ->where('officeWorkload.0.pr_count', 1)
                ->where('officeWorkload.0.po_count', 1)
                ->where('officeWorkload.0.vch_count', 0)
                ->where('officeWorkload.0.total', 2)
            );
    }

    public function test_office_workload_excludes_completed_and_cancelled(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Viewer');
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

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('officeWorkload', 0)
            );
    }

    public function test_dashboard_empty_state(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Viewer');

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('summary.procurements.total', 0)
                ->where('summary.purchase_requests.total', 0)
                ->where('summary.purchase_orders.total', 0)
                ->where('summary.vouchers.total', 0)
                ->has('officeWorkload', 0)
            );
    }

    // -------------------------------------------------------
    // Story 4.1.2 - Activity Feed & Stagnant Panel Feature Tests
    // -------------------------------------------------------

    public function test_dashboard_includes_activity_feed_prop(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Viewer');

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->has('activityFeed')
            );
    }

    public function test_dashboard_includes_stagnant_transactions_prop(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Viewer');

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->has('stagnantTransactions')
            );
    }

    public function test_activity_feed_returns_data_with_actions(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Viewer');
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

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('activityFeed', 1)
                ->where('activityFeed.0.action_type', 'endorse')
                ->where('activityFeed.0.transaction_reference_number', $transaction->reference_number)
                ->where('activityFeed.0.actor_name', $user->name)
            );
    }

    public function test_activity_feed_empty_state(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Viewer');

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('activityFeed', 0)
            );
    }

    public function test_stagnant_transactions_empty_state(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Viewer');

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('stagnantTransactions', 0)
            );
    }

    public function test_stagnant_transactions_with_overdue_data(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Viewer');
        $office = Office::factory()->create();
        $procurement = Procurement::factory()->create(['created_by_user_id' => $user->id]);

        $transaction = Transaction::factory()->create([
            'procurement_id' => $procurement->id,
            'category' => 'PR',
            'status' => 'In Progress',
            'current_office_id' => $office->id,
            'created_by_user_id' => $user->id,
        ]);

        // Create old action to trigger idle stagnation
        TransactionAction::factory()->receive()->create([
            'transaction_id' => $transaction->id,
            'from_user_id' => $user->id,
            'created_at' => now()->subDays(15),
        ]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('stagnantTransactions')
            );
    }

    public function test_dashboard_all_roles_see_activity_and_stagnant(): void
    {
        $roles = ['Viewer', 'Endorser', 'Administrator'];

        foreach ($roles as $roleName) {
            $user = User::factory()->create();
            $user->assignRole($roleName);

            $this->actingAs($user)
                ->get(route('dashboard'))
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->has('activityFeed')
                    ->has('stagnantTransactions')
                );
        }
    }
}
