<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\FundType;
use App\Models\Procurement;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchaseRequestControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RoleSeeder::class);
    }

    public function test_create_route_returns_inertia_page_for_endorser(): void
    {
        $endorser = User::factory()->create();
        $endorser->assignRole('Endorser');

        $procurement = Procurement::factory()->create();
        $fundTypes = FundType::factory()->count(3)->create();

        $response = $this->actingAs($endorser)
            ->get(route('procurements.purchase-requests.create', $procurement));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('PurchaseRequests/Create')
            ->has('procurement')
            ->has('fundTypes', 3)
        );
    }

    public function test_create_route_forbidden_for_viewer(): void
    {
        $viewer = User::factory()->create();
        $viewer->assignRole('Viewer');

        $procurement = Procurement::factory()->create();

        $response = $this->actingAs($viewer)
            ->get(route('procurements.purchase-requests.create', $procurement));

        $response->assertForbidden();
    }

    public function test_store_creates_transaction_and_purchase_request(): void
    {
        $endorser = User::factory()->create();
        $endorser->assignRole('Endorser');

        $procurement = Procurement::factory()->create(['status' => 'Created']);
        $fundType = FundType::factory()->create();

        $response = $this->actingAs($endorser)
            ->post(route('procurements.purchase-requests.store', $procurement), [
                'fund_type_id' => $fundType->id,
            ]);

        $response->assertRedirect(route('procurements.show', $procurement));

        $this->assertDatabaseHas('transactions', [
            'procurement_id' => $procurement->id,
            'category' => 'PR',
            'status' => 'Created',
            'created_by_user_id' => $endorser->id,
        ]);

        $transaction = Transaction::where('procurement_id', $procurement->id)->first();
        $this->assertNotNull($transaction);
        $this->assertStringStartsWith('PR-', $transaction->reference_number);

        $this->assertDatabaseHas('purchase_requests', [
            'transaction_id' => $transaction->id,
            'fund_type_id' => $fundType->id,
        ]);
    }

    public function test_store_transitions_procurement_status_to_in_progress(): void
    {
        $endorser = User::factory()->create();
        $endorser->assignRole('Endorser');

        $procurement = Procurement::factory()->create(['status' => 'Created']);
        $fundType = FundType::factory()->create();

        $this->actingAs($endorser)
            ->post(route('procurements.purchase-requests.store', $procurement), [
                'fund_type_id' => $fundType->id,
            ]);

        $this->assertDatabaseHas('procurements', [
            'id' => $procurement->id,
            'status' => 'In Progress',
        ]);
    }

    public function test_store_fails_when_pr_already_exists(): void
    {
        $endorser = User::factory()->create();
        $endorser->assignRole('Endorser');

        $procurement = Procurement::factory()->create();
        $fundType = FundType::factory()->create();

        // Create existing PR
        $transaction = Transaction::factory()->create([
            'procurement_id' => $procurement->id,
            'category' => 'PR',
        ]);
        PurchaseRequest::factory()->create([
            'transaction_id' => $transaction->id,
        ]);

        $response = $this->actingAs($endorser)
            ->post(route('procurements.purchase-requests.store', $procurement), [
                'fund_type_id' => $fundType->id,
            ]);

        // Should fail due to business rule
        $response->assertStatus(302); // Redirect with error
    }

    public function test_show_route_returns_pr_details_with_relationships(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Viewer');

        $procurement = Procurement::factory()->create();
        $fundType = FundType::factory()->create();
        $transaction = Transaction::factory()->create([
            'procurement_id' => $procurement->id,
            'category' => 'PR',
        ]);
        $pr = PurchaseRequest::factory()->create([
            'transaction_id' => $transaction->id,
            'fund_type_id' => $fundType->id,
        ]);

        $response = $this->actingAs($user)
            ->get(route('purchase-requests.show', $pr));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('PurchaseRequests/Show')
            ->has('purchaseRequest')
            ->has('canEdit')
            ->has('canDelete')
        );
    }

    public function test_edit_route_returns_inertia_page_for_administrator(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Administrator');

        $transaction = Transaction::factory()->create(['category' => 'PR']);
        $pr = PurchaseRequest::factory()->create(['transaction_id' => $transaction->id]);

        $response = $this->actingAs($admin)
            ->get(route('purchase-requests.edit', $pr));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('PurchaseRequests/Edit')
            ->has('purchaseRequest')
            ->has('fundTypes')
        );
    }

    public function test_update_modifies_fund_type(): void
    {
        $endorser = User::factory()->create();
        $endorser->assignRole('Endorser');

        $oldFundType = FundType::factory()->create();
        $newFundType = FundType::factory()->create();

        $transaction = Transaction::factory()->create(['category' => 'PR']);
        $pr = PurchaseRequest::factory()->create([
            'transaction_id' => $transaction->id,
            'fund_type_id' => $oldFundType->id,
        ]);

        $response = $this->actingAs($endorser)
            ->put(route('purchase-requests.update', $pr), [
                'fund_type_id' => $newFundType->id,
            ]);

        $response->assertRedirect(route('purchase-requests.show', $pr));

        $this->assertDatabaseHas('purchase_requests', [
            'id' => $pr->id,
            'fund_type_id' => $newFundType->id,
        ]);
    }

    public function test_destroy_soft_deletes_pr_and_transaction(): void
    {
        $endorser = User::factory()->create();
        $endorser->assignRole('Endorser');

        $procurement = Procurement::factory()->create();
        $transaction = Transaction::factory()->create([
            'procurement_id' => $procurement->id,
            'category' => 'PR',
        ]);
        $pr = PurchaseRequest::factory()->create(['transaction_id' => $transaction->id]);

        $response = $this->actingAs($endorser)
            ->delete(route('purchase-requests.destroy', $pr));

        $response->assertRedirect(route('procurements.show', $procurement));

        $this->assertSoftDeleted('purchase_requests', ['id' => $pr->id]);
        $this->assertSoftDeleted('transactions', ['id' => $transaction->id]);
    }

    public function test_destroy_fails_when_po_exists(): void
    {
        $endorser = User::factory()->create();
        $endorser->assignRole('Endorser');

        $procurement = Procurement::factory()->create();
        $prTransaction = Transaction::factory()->create([
            'procurement_id' => $procurement->id,
            'category' => 'PR',
        ]);
        $pr = PurchaseRequest::factory()->create(['transaction_id' => $prTransaction->id]);

        // Create PO
        $poTransaction = Transaction::factory()->create([
            'procurement_id' => $procurement->id,
            'category' => 'PO',
        ]);
        PurchaseOrder::factory()->create(['transaction_id' => $poTransaction->id]);

        $response = $this->actingAs($endorser)
            ->delete(route('purchase-requests.destroy', $pr));

        $response->assertStatus(422);

        $this->assertDatabaseHas('purchase_requests', ['id' => $pr->id, 'deleted_at' => null]);
    }

    public function test_viewer_cannot_create_pr(): void
    {
        $viewer = User::factory()->create();
        $viewer->assignRole('Viewer');

        $procurement = Procurement::factory()->create();
        $fundType = FundType::factory()->create();

        $response = $this->actingAs($viewer)
            ->post(route('procurements.purchase-requests.store', $procurement), [
                'fund_type_id' => $fundType->id,
            ]);

        $response->assertForbidden();
    }

    public function test_viewer_can_view_pr(): void
    {
        $viewer = User::factory()->create();
        $viewer->assignRole('Viewer');

        $transaction = Transaction::factory()->create(['category' => 'PR']);
        $pr = PurchaseRequest::factory()->create(['transaction_id' => $transaction->id]);

        $response = $this->actingAs($viewer)
            ->get(route('purchase-requests.show', $pr));

        $response->assertOk();
    }

    public function test_administrator_can_create_pr(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Administrator');

        $procurement = Procurement::factory()->create(['status' => 'Created']);
        $fundType = FundType::factory()->create();

        $response = $this->actingAs($admin)
            ->post(route('procurements.purchase-requests.store', $procurement), [
                'fund_type_id' => $fundType->id,
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('transactions', ['procurement_id' => $procurement->id, 'category' => 'PR']);
    }

    // Story 2.6: Manual Reference Number Tests

    public function test_store_with_manual_reference_number_creates_correct_format(): void
    {
        $endorser = User::factory()->create();
        $endorser->assignRole('Endorser');

        $procurement = Procurement::factory()->create(['status' => 'Created']);
        $fundType = FundType::factory()->create(['abbreviation' => 'GAA']);

        $response = $this->actingAs($endorser)
            ->post(route('procurements.purchase-requests.store', $procurement), [
                'fund_type_id' => $fundType->id,
                'ref_year' => '2025',
                'ref_month' => '10',
                'ref_number' => '001',
                'is_continuation' => false,
            ]);

        $response->assertRedirect(route('procurements.show', $procurement));

        $this->assertDatabaseHas('transactions', [
            'procurement_id' => $procurement->id,
            'category' => 'PR',
            'reference_number' => 'PR-GAA-2025-10-001',
            'is_continuation' => false,
        ]);
    }

    public function test_store_with_continuation_flag_adds_cont_prefix(): void
    {
        $endorser = User::factory()->create();
        $endorser->assignRole('Endorser');

        $procurement = Procurement::factory()->create(['status' => 'Created']);
        $fundType = FundType::factory()->create(['abbreviation' => 'SEF']);

        $response = $this->actingAs($endorser)
            ->post(route('procurements.purchase-requests.store', $procurement), [
                'fund_type_id' => $fundType->id,
                'ref_year' => '2024',
                'ref_month' => '12',
                'ref_number' => '9999',
                'is_continuation' => true,
            ]);

        $response->assertRedirect(route('procurements.show', $procurement));

        $transaction = Transaction::where('reference_number', 'CONT-PR-SEF-2024-12-9999')->first();
        $this->assertNotNull($transaction);
        $this->assertTrue((bool) $transaction->is_continuation);
    }

    public function test_store_rejects_duplicate_reference_number(): void
    {
        $endorser = User::factory()->create();
        $endorser->assignRole('Endorser');

        $procurement1 = Procurement::factory()->create();
        $procurement2 = Procurement::factory()->create();
        $fundType = FundType::factory()->create(['abbreviation' => 'GAA']);

        // Create first PR
        $this->actingAs($endorser)
            ->post(route('procurements.purchase-requests.store', $procurement1), [
                'fund_type_id' => $fundType->id,
                'ref_year' => '2025',
                'ref_month' => '10',
                'ref_number' => '001',
                'is_continuation' => false,
            ]);

        // Attempt duplicate
        $response = $this->actingAs($endorser)
            ->post(route('procurements.purchase-requests.store', $procurement2), [
                'fund_type_id' => $fundType->id,
                'ref_year' => '2025',
                'ref_month' => '10',
                'ref_number' => '001',
                'is_continuation' => false,
            ]);

        $response->assertSessionHasErrors('ref_number');
    }

    public function test_store_validates_year_must_be_4_digits(): void
    {
        $endorser = User::factory()->create();
        $endorser->assignRole('Endorser');

        $procurement = Procurement::factory()->create();
        $fundType = FundType::factory()->create();

        $response = $this->actingAs($endorser)
            ->post(route('procurements.purchase-requests.store', $procurement), [
                'fund_type_id' => $fundType->id,
                'ref_year' => '24', // Invalid: only 2 digits
                'ref_month' => '10',
                'ref_number' => '001',
                'is_continuation' => false,
            ]);

        $response->assertSessionHasErrors('ref_year');
    }

    public function test_store_validates_month_must_be_01_to_12(): void
    {
        $endorser = User::factory()->create();
        $endorser->assignRole('Endorser');

        $procurement = Procurement::factory()->create();
        $fundType = FundType::factory()->create();

        $response = $this->actingAs($endorser)
            ->post(route('procurements.purchase-requests.store', $procurement), [
                'fund_type_id' => $fundType->id,
                'ref_year' => '2025',
                'ref_month' => '13', // Invalid: out of range
                'ref_number' => '001',
                'is_continuation' => false,
            ]);

        $response->assertSessionHasErrors('ref_month');
    }

    public function test_update_changes_reference_number_with_new_components(): void
    {
        $endorser = User::factory()->create();
        $endorser->assignRole('Endorser');

        $fundType1 = FundType::factory()->create(['abbreviation' => 'GAA']);
        $fundType2 = FundType::factory()->create(['abbreviation' => 'SEF']);

        $transaction = Transaction::factory()->create([
            'category' => 'PR',
            'reference_number' => 'PR-GAA-2025-10-001',
            'is_continuation' => false,
        ]);
        $pr = PurchaseRequest::factory()->create([
            'transaction_id' => $transaction->id,
            'fund_type_id' => $fundType1->id,
        ]);

        $response = $this->actingAs($endorser)
            ->put(route('purchase-requests.update', $pr), [
                'fund_type_id' => $fundType2->id,
                'ref_year' => '2025',
                'ref_month' => '11',
                'ref_number' => '002',
                'is_continuation' => false,
            ]);

        $response->assertRedirect(route('purchase-requests.show', $pr));

        $this->assertDatabaseHas('transactions', [
            'id' => $transaction->id,
            'reference_number' => 'PR-SEF-2025-11-002',
        ]);
    }

    public function test_update_rejects_duplicate_reference_number(): void
    {
        $endorser = User::factory()->create();
        $endorser->assignRole('Endorser');

        $fundType = FundType::factory()->create(['abbreviation' => 'GAA']);

        // Create first PR with reference PR-GAA-2025-10-001
        $transaction1 = Transaction::factory()->create([
            'category' => 'PR',
            'reference_number' => 'PR-GAA-2025-10-001',
        ]);
        PurchaseRequest::factory()->create([
            'transaction_id' => $transaction1->id,
            'fund_type_id' => $fundType->id,
        ]);

        // Create second PR with different reference
        $transaction2 = Transaction::factory()->create([
            'category' => 'PR',
            'reference_number' => 'PR-GAA-2025-10-002',
        ]);
        $pr2 = PurchaseRequest::factory()->create([
            'transaction_id' => $transaction2->id,
            'fund_type_id' => $fundType->id,
        ]);

        // Attempt to update PR2 to use PR1's reference number
        $response = $this->actingAs($endorser)
            ->put(route('purchase-requests.update', $pr2), [
                'fund_type_id' => $fundType->id,
                'ref_year' => '2025',
                'ref_month' => '10',
                'ref_number' => '001',
                'is_continuation' => false,
            ]);

        $response->assertSessionHasErrors('ref_number');
    }
}
