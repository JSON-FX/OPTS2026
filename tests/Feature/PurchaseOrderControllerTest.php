<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\FundType;
use App\Models\Procurement;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\Supplier;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Voucher;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchaseOrderControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $endorser;

    protected User $administrator;

    protected User $viewer;

    protected Procurement $procurement;

    protected FundType $fundType;

    protected Supplier $supplier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        $this->endorser = User::factory()->create();
        $this->endorser->assignRole('Endorser');

        $this->administrator = User::factory()->create();
        $this->administrator->assignRole('Administrator');

        $this->viewer = User::factory()->create();
        $this->viewer->assignRole('Viewer');

        $this->fundType = FundType::factory()->create(['abbreviation' => 'GAA']);
        $this->supplier = Supplier::factory()->create([
            'name' => 'Test Supplier Inc.',
            'address' => '123 Main Street',
        ]);

        $this->procurement = Procurement::factory()->create([
            'status' => 'Created',
        ]);
    }

    /** @test */
    public function test_endorser_can_create_po_with_manual_reference_number(): void
    {
        // Create PR first (prerequisite for PO)
        $prTransaction = Transaction::factory()->create([
            'procurement_id' => $this->procurement->id,
            'category' => 'PR',
            'reference_number' => 'PR-GAA-2025-10-001',
            'status' => 'Created',
        ]);
        PurchaseRequest::factory()->create([
            'transaction_id' => $prTransaction->id,
            'fund_type_id' => $this->fundType->id,
        ]);

        // Refresh procurement to ensure relationship is loaded for business rule check
        $this->procurement->refresh();

        $response = $this->actingAs($this->endorser)
            ->post(route('procurements.purchase-orders.store', $this->procurement), [
                'supplier_id' => $this->supplier->id,
                'contract_price' => 125000.50,
                'ref_year' => '2025',
                'ref_month' => '10',
                'ref_number' => '042',
                'is_continuation' => false,
            ]);

        $response->assertRedirect(route('procurements.show', $this->procurement));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('transactions', [
            'procurement_id' => $this->procurement->id,
            'category' => 'PO',
            'reference_number' => 'PO-2025-10-042',
            'is_continuation' => false,
            'status' => 'Created',
        ]);

        $transaction = Transaction::where('reference_number', 'PO-2025-10-042')->first();
        $this->assertDatabaseHas('purchase_orders', [
            'transaction_id' => $transaction->id,
            'supplier_id' => $this->supplier->id,
            'supplier_address' => '123 Main Street', // Snapshot
            'contract_price' => 125000.50,
        ]);
    }

    /** @test */
    public function test_create_po_fails_if_no_pr_exists(): void
    {
        $response = $this->actingAs($this->endorser)
            ->post(route('procurements.purchase-orders.store', $this->procurement), [
                'supplier_id' => $this->supplier->id,
                'contract_price' => 125000.50,
                'ref_year' => '2025',
                'ref_month' => '10',
                'ref_number' => '042',
                'is_continuation' => false,
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['procurement']);
    }

    /** @test */
    public function test_create_po_with_continuation_flag(): void
    {
        // Create PR first
        $prTransaction = Transaction::factory()->create([
            'procurement_id' => $this->procurement->id,
            'category' => 'PR',
            'reference_number' => 'PR-GAA-2024-12-999',
        ]);
        PurchaseRequest::factory()->create([
            'transaction_id' => $prTransaction->id,
            'fund_type_id' => $this->fundType->id,
        ]);

        $response = $this->actingAs($this->endorser)
            ->post(route('procurements.purchase-orders.store', $this->procurement), [
                'supplier_id' => $this->supplier->id,
                'contract_price' => 50000.00,
                'ref_year' => '2024',
                'ref_month' => '12',
                'ref_number' => '9999',
                'is_continuation' => true,
            ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('transactions', [
            'procurement_id' => $this->procurement->id,
            'category' => 'PO',
            'reference_number' => 'CONT-PO-2024-12-9999',
            'is_continuation' => true,
        ]);
    }

    /** @test */
    public function test_supplier_address_is_snapshot_not_reference(): void
    {
        // Create PR
        $prTransaction = Transaction::factory()->create([
            'procurement_id' => $this->procurement->id,
            'category' => 'PR',
            'reference_number' => 'PR-GAA-2025-10-001',
        ]);
        PurchaseRequest::factory()->create([
            'transaction_id' => $prTransaction->id,
            'fund_type_id' => $this->fundType->id,
        ]);

        // Create PO
        $this->actingAs($this->endorser)
            ->post(route('procurements.purchase-orders.store', $this->procurement), [
                'supplier_id' => $this->supplier->id,
                'contract_price' => 100000.00,
                'ref_year' => '2025',
                'ref_month' => '10',
                'ref_number' => '050',
                'is_continuation' => false,
            ]);

        $poTransaction = Transaction::where('reference_number', 'PO-2025-10-050')->first();
        $po = PurchaseOrder::where('transaction_id', $poTransaction->id)->first();

        $this->assertEquals('123 Main Street', $po->supplier_address);

        // Update supplier address
        $this->supplier->update(['address' => '456 Oak Avenue']);

        // Reload PO and verify address hasn't changed
        $po->refresh();
        $this->assertEquals('123 Main Street', $po->supplier_address); // Still old address
    }

    /** @test */
    public function test_update_po_changes_reference_number(): void
    {
        // Create PR + PO
        $prTransaction = Transaction::factory()->create([
            'procurement_id' => $this->procurement->id,
            'category' => 'PR',
            'reference_number' => 'PR-GAA-2025-10-001',
        ]);
        PurchaseRequest::factory()->create([
            'transaction_id' => $prTransaction->id,
            'fund_type_id' => $this->fundType->id,
        ]);

        $poTransaction = Transaction::factory()->create([
            'procurement_id' => $this->procurement->id,
            'category' => 'PO',
            'reference_number' => 'PO-2025-10-050',
            'is_continuation' => false,
        ]);
        $po = PurchaseOrder::factory()->create([
            'transaction_id' => $poTransaction->id,
            'supplier_id' => $this->supplier->id,
            'supplier_address' => '123 Main Street',
            'contract_price' => 100000.00,
        ]);

        $response = $this->actingAs($this->endorser)
            ->put(route('purchase-orders.update', $po->id), [
                'supplier_id' => $this->supplier->id,
                'contract_price' => 100000.00,
                'ref_year' => '2025',
                'ref_month' => '11',
                'ref_number' => '075',
                'is_continuation' => false,
            ]);

        $response->assertRedirect(route('purchase-orders.show', $po->id));

        $this->assertDatabaseHas('transactions', [
            'id' => $poTransaction->id,
            'reference_number' => 'PO-2025-11-075',
        ]);
    }

    /** @test */
    public function test_update_po_with_supplier_change_updates_address_snapshot(): void
    {
        // Create PR + PO
        $prTransaction = Transaction::factory()->create([
            'procurement_id' => $this->procurement->id,
            'category' => 'PR',
            'reference_number' => 'PR-GAA-2025-10-001',
        ]);
        PurchaseRequest::factory()->create([
            'transaction_id' => $prTransaction->id,
            'fund_type_id' => $this->fundType->id,
        ]);

        $poTransaction = Transaction::factory()->create([
            'procurement_id' => $this->procurement->id,
            'category' => 'PO',
            'reference_number' => 'PO-2025-10-050',
        ]);
        $po = PurchaseOrder::factory()->create([
            'transaction_id' => $poTransaction->id,
            'supplier_id' => $this->supplier->id,
            'supplier_address' => '123 Main Street',
            'contract_price' => 100000.00,
        ]);

        // Create new supplier
        $newSupplier = Supplier::factory()->create([
            'name' => 'New Supplier Co.',
            'address' => '789 Elm Street',
        ]);

        $response = $this->actingAs($this->endorser)
            ->put(route('purchase-orders.update', $po->id), [
                'supplier_id' => $newSupplier->id,
                'contract_price' => 100000.00,
                'ref_year' => '2025',
                'ref_month' => '10',
                'ref_number' => '050',
                'is_continuation' => false,
            ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('purchase_orders', [
            'id' => $po->id,
            'supplier_id' => $newSupplier->id,
            'supplier_address' => '789 Elm Street', // Updated to new supplier's address
        ]);
    }

    /** @test */
    public function test_delete_po_fails_if_voucher_exists(): void
    {
        // Create full chain: PR → PO → VCH
        $prTransaction = Transaction::factory()->create([
            'procurement_id' => $this->procurement->id,
            'category' => 'PR',
            'reference_number' => 'PR-GAA-2025-10-001',
        ]);
        PurchaseRequest::factory()->create([
            'transaction_id' => $prTransaction->id,
            'fund_type_id' => $this->fundType->id,
        ]);

        $poTransaction = Transaction::factory()->create([
            'procurement_id' => $this->procurement->id,
            'category' => 'PO',
            'reference_number' => 'PO-2025-10-050',
        ]);
        $po = PurchaseOrder::factory()->create([
            'transaction_id' => $poTransaction->id,
            'supplier_id' => $this->supplier->id,
            'supplier_address' => '123 Main Street',
            'contract_price' => 100000.00,
        ]);

        $vchTransaction = Transaction::factory()->create([
            'procurement_id' => $this->procurement->id,
            'category' => 'VCH',
            'reference_number' => 'VCH-2025-000001',
        ]);
        Voucher::factory()->create([
            'transaction_id' => $vchTransaction->id,
            'payee' => 'Test Payee',
        ]);

        $response = $this->actingAs($this->endorser)
            ->delete(route('purchase-orders.destroy', $po->id));

        $response->assertStatus(422);
        $response->assertJsonFragment(['message' => 'Cannot delete Purchase Order because Voucher VCH-2025-000001 exists. Delete the Voucher first.']);

        $this->assertDatabaseHas('purchase_orders', ['id' => $po->id]); // Not deleted
    }

    /** @test */
    public function test_viewer_cannot_create_po(): void
    {
        // Create PR first
        $prTransaction = Transaction::factory()->create([
            'procurement_id' => $this->procurement->id,
            'category' => 'PR',
            'reference_number' => 'PR-GAA-2025-10-001',
        ]);
        PurchaseRequest::factory()->create([
            'transaction_id' => $prTransaction->id,
            'fund_type_id' => $this->fundType->id,
        ]);

        $response = $this->actingAs($this->viewer)
            ->post(route('procurements.purchase-orders.store', $this->procurement), [
                'supplier_id' => $this->supplier->id,
                'contract_price' => 125000.50,
                'ref_year' => '2025',
                'ref_month' => '10',
                'ref_number' => '042',
                'is_continuation' => false,
            ]);

        $response->assertForbidden();
    }

    /** @test */
    public function test_contract_price_validation(): void
    {
        // Create PR first
        $prTransaction = Transaction::factory()->create([
            'procurement_id' => $this->procurement->id,
            'category' => 'PR',
            'reference_number' => 'PR-GAA-2025-10-001',
        ]);
        PurchaseRequest::factory()->create([
            'transaction_id' => $prTransaction->id,
            'fund_type_id' => $this->fundType->id,
        ]);

        // Test min value violation
        $response = $this->actingAs($this->endorser)
            ->post(route('procurements.purchase-orders.store', $this->procurement), [
                'supplier_id' => $this->supplier->id,
                'contract_price' => 0.00, // Below min 0.01
                'ref_year' => '2025',
                'ref_month' => '10',
                'ref_number' => '042',
                'is_continuation' => false,
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['contract_price']);

        // Test required
        $response = $this->actingAs($this->endorser)
            ->post(route('procurements.purchase-orders.store', $this->procurement), [
                'supplier_id' => $this->supplier->id,
                // contract_price missing
                'ref_year' => '2025',
                'ref_month' => '10',
                'ref_number' => '043',
                'is_continuation' => false,
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['contract_price']);
    }
}
