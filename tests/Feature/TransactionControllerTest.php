<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Office;
use App\Models\Particular;
use App\Models\Procurement;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TransactionControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RoleSeeder::class);
    }

    public function test_transactions_index_displays_all_transaction_types(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Endorser');

        $office = Office::factory()->create();
        $particular = Particular::factory()->create();

        $procurement1 = Procurement::factory()->create([
            'end_user_id' => $office->id,
            'particular_id' => $particular->id,
            'created_by_user_id' => $user->id,
        ]);

        $procurement2 = Procurement::factory()->create([
            'end_user_id' => $office->id,
            'particular_id' => $particular->id,
            'created_by_user_id' => $user->id,
        ]);

        $procurement3 = Procurement::factory()->create([
            'end_user_id' => $office->id,
            'particular_id' => $particular->id,
            'created_by_user_id' => $user->id,
        ]);

        $prTransaction = Transaction::factory()->create([
            'procurement_id' => $procurement1->id,
            'category' => 'PR',
            'reference_number' => 'PR-GAA-2025-10-001',
            'created_by_user_id' => $user->id,
            'created_at' => now()->subDays(2),
        ]);

        $poTransaction = Transaction::factory()->create([
            'procurement_id' => $procurement2->id,
            'category' => 'PO',
            'reference_number' => 'PO-2025-10-001',
            'created_by_user_id' => $user->id,
            'created_at' => now()->subDay(),
        ]);

        $vchTransaction = Transaction::factory()->create([
            'procurement_id' => $procurement3->id,
            'category' => 'VCH',
            'reference_number' => 'VCH-GAA-2025-10-001',
            'created_by_user_id' => $user->id,
            'created_at' => now(),
        ]);

        $response = $this->actingAs($user)->get(route('transactions.index'));

        $response->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Transactions/Index')
                ->has('transactions.data', 3)
                ->where('transactions.data.0.category', 'VCH')
                ->where('transactions.data.1.category', 'PO')
                ->where('transactions.data.2.category', 'PR')
            );
    }

    public function test_transactions_index_filters_by_reference_number(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Endorser');

        $office = Office::factory()->create();
        $particular = Particular::factory()->create();

        $procurement1 = Procurement::factory()->create([
            'end_user_id' => $office->id,
            'particular_id' => $particular->id,
            'created_by_user_id' => $user->id,
        ]);

        $procurement2 = Procurement::factory()->create([
            'end_user_id' => $office->id,
            'particular_id' => $particular->id,
            'created_by_user_id' => $user->id,
        ]);

        Transaction::factory()->create([
            'procurement_id' => $procurement1->id,
            'reference_number' => 'PR-GAA-2025-10-001',
            'category' => 'PR',
            'created_by_user_id' => $user->id,
        ]);

        Transaction::factory()->create([
            'procurement_id' => $procurement2->id,
            'reference_number' => 'PO-2025-10-002',
            'category' => 'PO',
            'created_by_user_id' => $user->id,
        ]);

        $response = $this->actingAs($user)->get(route('transactions.index', ['reference_number' => 'PR-GAA']));

        $response->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Transactions/Index')
                ->has('transactions.data', 1)
                ->where('transactions.data.0.reference_number', 'PR-GAA-2025-10-001')
            );
    }

    public function test_transactions_index_filters_by_category(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Endorser');

        $office = Office::factory()->create();
        $particular = Particular::factory()->create();

        $procurement1 = Procurement::factory()->create([
            'end_user_id' => $office->id,
            'particular_id' => $particular->id,
            'created_by_user_id' => $user->id,
        ]);

        $procurement2 = Procurement::factory()->create([
            'end_user_id' => $office->id,
            'particular_id' => $particular->id,
            'created_by_user_id' => $user->id,
        ]);

        Transaction::factory()->create([
            'procurement_id' => $procurement1->id,
            'category' => 'PR',
            'created_by_user_id' => $user->id,
        ]);

        Transaction::factory()->create([
            'procurement_id' => $procurement2->id,
            'category' => 'PO',
            'created_by_user_id' => $user->id,
        ]);

        $response = $this->actingAs($user)->get(route('transactions.index', ['category' => 'PR']));

        $response->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Transactions/Index')
                ->has('transactions.data', 1)
                ->where('transactions.data.0.category', 'PR')
            );
    }

    public function test_transactions_index_filters_by_status(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Endorser');

        $office = Office::factory()->create();
        $particular = Particular::factory()->create();

        $procurement1 = Procurement::factory()->create([
            'end_user_id' => $office->id,
            'particular_id' => $particular->id,
            'created_by_user_id' => $user->id,
        ]);

        $procurement2 = Procurement::factory()->create([
            'end_user_id' => $office->id,
            'particular_id' => $particular->id,
            'created_by_user_id' => $user->id,
        ]);

        Transaction::factory()->create([
            'procurement_id' => $procurement1->id,
            'status' => 'Completed',
            'created_by_user_id' => $user->id,
        ]);

        Transaction::factory()->create([
            'procurement_id' => $procurement2->id,
            'status' => 'In Progress',
            'created_by_user_id' => $user->id,
        ]);

        $response = $this->actingAs($user)->get(route('transactions.index', ['status' => 'Completed']));

        $response->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Transactions/Index')
                ->has('transactions.data', 1)
                ->where('transactions.data.0.status', 'Completed')
            );
    }

    public function test_transactions_index_filters_by_date_range(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Endorser');

        $office = Office::factory()->create();
        $particular = Particular::factory()->create();

        $procurement1 = Procurement::factory()->create([
            'end_user_id' => $office->id,
            'particular_id' => $particular->id,
            'created_by_user_id' => $user->id,
        ]);

        $procurement2 = Procurement::factory()->create([
            'end_user_id' => $office->id,
            'particular_id' => $particular->id,
            'created_by_user_id' => $user->id,
        ]);

        Transaction::factory()->create([
            'procurement_id' => $procurement1->id,
            'created_by_user_id' => $user->id,
            'created_at' => now()->subDays(10),
        ]);

        Transaction::factory()->create([
            'procurement_id' => $procurement2->id,
            'created_by_user_id' => $user->id,
            'created_at' => now()->subDays(2),
        ]);

        $response = $this->actingAs($user)->get(route('transactions.index', [
            'date_from' => now()->subDays(5)->format('Y-m-d'),
            'date_to' => now()->format('Y-m-d'),
        ]));

        $response->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Transactions/Index')
                ->has('transactions.data', 1)
            );
    }

    public function test_transactions_index_filters_by_end_user_office(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Endorser');

        $office1 = Office::factory()->create(['name' => 'Office A']);
        $office2 = Office::factory()->create(['name' => 'Office B']);
        $particular = Particular::factory()->create();

        $procurement1 = Procurement::factory()->create([
            'end_user_id' => $office1->id,
            'particular_id' => $particular->id,
            'created_by_user_id' => $user->id,
        ]);

        $procurement2 = Procurement::factory()->create([
            'end_user_id' => $office2->id,
            'particular_id' => $particular->id,
            'created_by_user_id' => $user->id,
        ]);

        Transaction::factory()->create([
            'procurement_id' => $procurement1->id,
            'created_by_user_id' => $user->id,
        ]);

        Transaction::factory()->create([
            'procurement_id' => $procurement2->id,
            'created_by_user_id' => $user->id,
        ]);

        $response = $this->actingAs($user)->get(route('transactions.index', ['end_user_id' => $office1->id]));

        $response->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Transactions/Index')
                ->has('transactions.data', 1)
                ->where('transactions.data.0.procurement_end_user_name', 'Office A')
            );
    }

    public function test_transactions_index_filters_by_created_by_me(): void
    {
        $user1 = User::factory()->create();
        $user1->assignRole('Endorser');

        $user2 = User::factory()->create();
        $user2->assignRole('Endorser');

        $office = Office::factory()->create();
        $particular = Particular::factory()->create();

        $procurement1 = Procurement::factory()->create([
            'end_user_id' => $office->id,
            'particular_id' => $particular->id,
            'created_by_user_id' => $user1->id,
        ]);

        $procurement2 = Procurement::factory()->create([
            'end_user_id' => $office->id,
            'particular_id' => $particular->id,
            'created_by_user_id' => $user2->id,
        ]);

        Transaction::factory()->create([
            'procurement_id' => $procurement1->id,
            'created_by_user_id' => $user1->id,
        ]);

        Transaction::factory()->create([
            'procurement_id' => $procurement2->id,
            'created_by_user_id' => $user2->id,
        ]);

        $response = $this->actingAs($user1)->get(route('transactions.index', ['created_by_me' => true]));

        $response->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Transactions/Index')
                ->has('transactions.data', 1)
                ->where('transactions.data.0.created_by_name', $user1->name)
            );
    }

    public function test_transactions_index_sorts_by_reference_number(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Endorser');

        $office = Office::factory()->create();
        $particular = Particular::factory()->create();

        $procurement1 = Procurement::factory()->create([
            'end_user_id' => $office->id,
            'particular_id' => $particular->id,
            'created_by_user_id' => $user->id,
        ]);

        $procurement2 = Procurement::factory()->create([
            'end_user_id' => $office->id,
            'particular_id' => $particular->id,
            'created_by_user_id' => $user->id,
        ]);

        Transaction::factory()->create([
            'procurement_id' => $procurement1->id,
            'reference_number' => 'PR-GAA-2025-10-002',
            'created_by_user_id' => $user->id,
        ]);

        Transaction::factory()->create([
            'procurement_id' => $procurement2->id,
            'reference_number' => 'PR-GAA-2025-10-001',
            'created_by_user_id' => $user->id,
        ]);

        $response = $this->actingAs($user)->get(route('transactions.index', [
            'sort_by' => 'reference_number',
            'sort_direction' => 'asc',
        ]));

        $response->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Transactions/Index')
                ->where('transactions.data.0.reference_number', 'PR-GAA-2025-10-001')
                ->where('transactions.data.1.reference_number', 'PR-GAA-2025-10-002')
            );
    }

    public function test_transactions_index_sorts_by_created_date(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Endorser');

        $office = Office::factory()->create();
        $particular = Particular::factory()->create();

        $procurement1 = Procurement::factory()->create([
            'end_user_id' => $office->id,
            'particular_id' => $particular->id,
            'created_by_user_id' => $user->id,
        ]);

        $procurement2 = Procurement::factory()->create([
            'end_user_id' => $office->id,
            'particular_id' => $particular->id,
            'created_by_user_id' => $user->id,
        ]);

        $oldTransaction = Transaction::factory()->create([
            'procurement_id' => $procurement1->id,
            'created_by_user_id' => $user->id,
            'created_at' => now()->subDays(5),
        ]);

        $newTransaction = Transaction::factory()->create([
            'procurement_id' => $procurement2->id,
            'created_by_user_id' => $user->id,
            'created_at' => now()->subDay(),
        ]);

        $response = $this->actingAs($user)->get(route('transactions.index', [
            'sort_by' => 'created_at',
            'sort_direction' => 'desc',
        ]));

        $response->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Transactions/Index')
                ->where('transactions.data.0.id', $newTransaction->id)
                ->where('transactions.data.1.id', $oldTransaction->id)
            );
    }

    public function test_transactions_index_pagination(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Endorser');

        $office = Office::factory()->create();
        $particular = Particular::factory()->create();

        // Create 60 transactions to test pagination (50 per page)
        for ($i = 0; $i < 60; $i++) {
            $procurement = Procurement::factory()->create([
                'end_user_id' => $office->id,
                'particular_id' => $particular->id,
                'created_by_user_id' => $user->id,
            ]);

            Transaction::factory()->create([
                'procurement_id' => $procurement->id,
                'created_by_user_id' => $user->id,
            ]);
        }

        $responsePage1 = $this->actingAs($user)->get(route('transactions.index'));
        $responsePage1->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Transactions/Index')
                ->has('transactions.data', 50)
                ->where('transactions.current_page', 1)
                ->where('transactions.total', 60)
            );

        $responsePage2 = $this->actingAs($user)->get(route('transactions.index', ['page' => 2]));
        $responsePage2->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Transactions/Index')
                ->has('transactions.data', 10)
                ->where('transactions.current_page', 2)
            );
    }

    public function test_viewer_sees_only_view_details_action(): void
    {
        $viewer = User::factory()->create();
        $viewer->assignRole('Viewer');

        $office = Office::factory()->create();
        $particular = Particular::factory()->create();

        $procurement = Procurement::factory()->create([
            'end_user_id' => $office->id,
            'particular_id' => $particular->id,
            'created_by_user_id' => $viewer->id,
        ]);

        Transaction::factory()->create([
            'procurement_id' => $procurement->id,
            'created_by_user_id' => $viewer->id,
        ]);

        $response = $this->actingAs($viewer)->get(route('transactions.index'));

        $response->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Transactions/Index')
                ->where('can.manage', false)
            );
    }

    public function test_endorser_sees_all_actions(): void
    {
        $endorser = User::factory()->create();
        $endorser->assignRole('Endorser');

        $office = Office::factory()->create();
        $particular = Particular::factory()->create();

        $procurement = Procurement::factory()->create([
            'end_user_id' => $office->id,
            'particular_id' => $particular->id,
            'created_by_user_id' => $endorser->id,
        ]);

        Transaction::factory()->create([
            'procurement_id' => $procurement->id,
            'created_by_user_id' => $endorser->id,
        ]);

        $response = $this->actingAs($endorser)->get(route('transactions.index'));

        $response->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Transactions/Index')
                ->where('can.manage', true)
            );
    }
}
