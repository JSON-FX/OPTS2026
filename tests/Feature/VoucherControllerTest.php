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

class VoucherControllerTest extends TestCase
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
    public function test_endorser_can_create_vch_with_valid_payee(): void
    {
        // Create full procurement chain: PR → PO → VCH
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

        $poTransaction = Transaction::factory()->create([
            'procurement_id' => $this->procurement->id,
            'category' => 'PO',
            'reference_number' => 'PO-2025-10-042',
            'status' => 'Created',
        ]);
        PurchaseOrder::factory()->create([
            'transaction_id' => $poTransaction->id,
            'supplier_id' => $this->supplier->id,
            'supplier_address' => '123 Main Street',
            'contract_price' => 125000.50,
        ]);

        $this->procurement->refresh();

        $response = $this->actingAs($this->endorser)
            ->post(route('procurements.vouchers.store', $this->procurement), [
                'reference_number' => 'VCH-2025-001',
                'payee' => 'John Doe Payment Services',
            ]);

        $response->assertRedirect(route('procurements.show', $this->procurement));
        $response->assertSessionHas('success');

        // Verify VCH transaction created with manual reference number
        $this->assertDatabaseHas('transactions', [
            'procurement_id' => $this->procurement->id,
            'category' => 'VCH',
            'reference_number' => 'VCH-2025-001',
            'status' => 'Created',
        ]);

        $transaction = Transaction::where('category', 'VCH')
            ->where('procurement_id', $this->procurement->id)
            ->first();

        $this->assertNotNull($transaction);
        $this->assertEquals('VCH-2025-001', $transaction->reference_number);

        // Verify Voucher record
        $this->assertDatabaseHas('vouchers', [
            'transaction_id' => $transaction->id,
            'payee' => 'John Doe Payment Services',
        ]);
    }

    /** @test */
    public function test_create_vch_fails_if_no_po_exists(): void
    {
        // Only create PR (no PO)
        $prTransaction = Transaction::factory()->create([
            'procurement_id' => $this->procurement->id,
            'category' => 'PR',
            'reference_number' => 'PR-GAA-2025-10-001',
        ]);
        PurchaseRequest::factory()->create([
            'transaction_id' => $prTransaction->id,
            'fund_type_id' => $this->fundType->id,
        ]);

        $response = $this->actingAs($this->endorser)
            ->post(route('procurements.vouchers.store', $this->procurement), [
                'reference_number' => 'VCH-2025-002',
                'payee' => 'John Doe',
            ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors(['procurement']);

        $this->assertDatabaseMissing('vouchers', [
            'payee' => 'John Doe',
        ]);
    }

    /** @test */
    public function test_vch_reference_number_manual_input_validated(): void
    {
        // Create PR + PO
        $prTransaction = Transaction::factory()->create([
            'procurement_id' => $this->procurement->id,
            'category' => 'PR',
        ]);
        PurchaseRequest::factory()->create([
            'transaction_id' => $prTransaction->id,
            'fund_type_id' => $this->fundType->id,
        ]);

        $poTransaction = Transaction::factory()->create([
            'procurement_id' => $this->procurement->id,
            'category' => 'PO',
        ]);
        PurchaseOrder::factory()->create([
            'transaction_id' => $poTransaction->id,
            'supplier_id' => $this->supplier->id,
            'supplier_address' => '123 Main Street',
            'contract_price' => 100000.00,
        ]);

        $response = $this->actingAs($this->endorser)
            ->post(route('procurements.vouchers.store', $this->procurement), [
                'reference_number' => 'VCH-GAA-2025-10-042',
                'payee' => 'Test Payee',
            ]);

        $response->assertRedirect(route('procurements.show', $this->procurement));

        $transaction = Transaction::where('category', 'VCH')->first();

        // Verify manual reference number stored exactly as input
        $this->assertEquals('VCH-GAA-2025-10-042', $transaction->reference_number);
    }

    /** @test */
    public function test_vch_reference_number_duplicate_rejected(): void
    {
        // Create PR + PO
        $prTransaction = Transaction::factory()->create([
            'procurement_id' => $this->procurement->id,
            'category' => 'PR',
        ]);
        PurchaseRequest::factory()->create([
            'transaction_id' => $prTransaction->id,
            'fund_type_id' => $this->fundType->id,
        ]);

        $poTransaction = Transaction::factory()->create([
            'procurement_id' => $this->procurement->id,
            'category' => 'PO',
        ]);
        PurchaseOrder::factory()->create([
            'transaction_id' => $poTransaction->id,
            'supplier_id' => $this->supplier->id,
            'supplier_address' => '123 Main Street',
            'contract_price' => 100000.00,
        ]);

        // Create first VCH with reference number
        $this->actingAs($this->endorser)
            ->post(route('procurements.vouchers.store', $this->procurement), [
                'reference_number' => 'VCH-2025-DUPLICATE',
                'payee' => 'First Payee',
            ]);

        // Attempt to create second VCH with same reference number (new procurement)
        $procurement2 = Procurement::factory()->create(['status' => 'Created']);

        $prTransaction2 = Transaction::factory()->create([
            'procurement_id' => $procurement2->id,
            'category' => 'PR',
        ]);
        PurchaseRequest::factory()->create([
            'transaction_id' => $prTransaction2->id,
            'fund_type_id' => $this->fundType->id,
        ]);

        $poTransaction2 = Transaction::factory()->create([
            'procurement_id' => $procurement2->id,
            'category' => 'PO',
        ]);
        PurchaseOrder::factory()->create([
            'transaction_id' => $poTransaction2->id,
            'supplier_id' => $this->supplier->id,
            'supplier_address' => '123 Main Street',
            'contract_price' => 100000.00,
        ]);

        $response = $this->actingAs($this->endorser)
            ->post(route('procurements.vouchers.store', $procurement2), [
                'reference_number' => 'VCH-2025-DUPLICATE',
                'payee' => 'Second Payee',
            ]);

        $response->assertSessionHasErrors(['reference_number']);
        $response->assertStatus(302);

        // Verify only one VCH with this reference exists
        $this->assertEquals(1, Transaction::where('reference_number', 'VCH-2025-DUPLICATE')->count());
    }

    /** @test */
    public function test_viewer_cannot_create_vch(): void
    {
        // Create PR + PO
        $prTransaction = Transaction::factory()->create([
            'procurement_id' => $this->procurement->id,
            'category' => 'PR',
        ]);
        PurchaseRequest::factory()->create([
            'transaction_id' => $prTransaction->id,
            'fund_type_id' => $this->fundType->id,
        ]);

        $poTransaction = Transaction::factory()->create([
            'procurement_id' => $this->procurement->id,
            'category' => 'PO',
        ]);
        PurchaseOrder::factory()->create([
            'transaction_id' => $poTransaction->id,
            'supplier_id' => $this->supplier->id,
            'supplier_address' => '123 Main Street',
            'contract_price' => 100000.00,
        ]);

        $response = $this->actingAs($this->viewer)
            ->post(route('procurements.vouchers.store', $this->procurement), [
                'reference_number' => 'VCH-2025-003',
                'payee' => 'John Doe',
            ]);

        $response->assertForbidden();
    }

    /** @test */
    public function test_endorser_can_update_vch_payee(): void
    {
        // Create full chain
        $prTransaction = Transaction::factory()->create([
            'procurement_id' => $this->procurement->id,
            'category' => 'PR',
        ]);
        PurchaseRequest::factory()->create([
            'transaction_id' => $prTransaction->id,
            'fund_type_id' => $this->fundType->id,
        ]);

        $poTransaction = Transaction::factory()->create([
            'procurement_id' => $this->procurement->id,
            'category' => 'PO',
        ]);
        PurchaseOrder::factory()->create([
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
        $voucher = Voucher::factory()->create([
            'transaction_id' => $vchTransaction->id,
            'payee' => 'Original Payee',
        ]);

        $response = $this->actingAs($this->endorser)
            ->put(route('vouchers.update', $voucher->id), [
                'reference_number' => 'VCH-2025-000001-UPDATED',
                'payee' => 'Updated Payee Name',
            ]);

        $response->assertRedirect(route('vouchers.show', $voucher->id));

        $this->assertDatabaseHas('vouchers', [
            'id' => $voucher->id,
            'payee' => 'Updated Payee Name',
        ]);

        $this->assertDatabaseHas('transactions', [
            'id' => $vchTransaction->id,
            'reference_number' => 'VCH-2025-000001-UPDATED',
        ]);
    }

    /** @test */
    public function test_endorser_can_soft_delete_vch(): void
    {
        // Create full chain
        $prTransaction = Transaction::factory()->create([
            'procurement_id' => $this->procurement->id,
            'category' => 'PR',
        ]);
        PurchaseRequest::factory()->create([
            'transaction_id' => $prTransaction->id,
            'fund_type_id' => $this->fundType->id,
        ]);

        $poTransaction = Transaction::factory()->create([
            'procurement_id' => $this->procurement->id,
            'category' => 'PO',
        ]);
        PurchaseOrder::factory()->create([
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
        $voucher = Voucher::factory()->create([
            'transaction_id' => $vchTransaction->id,
            'payee' => 'Test Payee',
        ]);

        $response = $this->actingAs($this->endorser)
            ->delete(route('vouchers.destroy', $voucher->id));

        $response->assertRedirect(route('procurements.show', $this->procurement->id));

        // Verify soft delete
        $this->assertSoftDeleted('vouchers', ['id' => $voucher->id]);
        $this->assertSoftDeleted('transactions', ['id' => $vchTransaction->id]);
    }

    /** @test */
    public function test_vch_relationships_load_correctly(): void
    {
        // Create full chain
        $prTransaction = Transaction::factory()->create([
            'procurement_id' => $this->procurement->id,
            'category' => 'PR',
        ]);
        PurchaseRequest::factory()->create([
            'transaction_id' => $prTransaction->id,
            'fund_type_id' => $this->fundType->id,
        ]);

        $poTransaction = Transaction::factory()->create([
            'procurement_id' => $this->procurement->id,
            'category' => 'PO',
        ]);
        PurchaseOrder::factory()->create([
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
        $voucher = Voucher::factory()->create([
            'transaction_id' => $vchTransaction->id,
            'payee' => 'Test Payee',
        ]);

        $voucher->load('transaction.procurement');

        $this->assertNotNull($voucher->transaction);
        $this->assertEquals('VCH', $voucher->transaction->category);
        $this->assertNotNull($voucher->transaction->procurement);
        $this->assertEquals($this->procurement->id, $voucher->transaction->procurement->id);
    }
}
