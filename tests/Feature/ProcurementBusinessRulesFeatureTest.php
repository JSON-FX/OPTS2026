<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Requests\StorePurchaseOrderRequest;
use App\Http\Requests\StoreVoucherRequest;
use App\Models\Office;
use App\Models\Particular;
use App\Models\Procurement;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\Supplier;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProcurementBusinessRulesFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RoleSeeder::class);
        foreach (['Viewer', 'Endorser', 'Administrator'] as $role) {
            User::factory()->create(['office_id' => Office::factory()->create()->id])->assignRole($role);
        }
    }

    public function test_po_validation_fails_when_no_pr_exists(): void
    {
        $procurement = $this->createProcurement();

        $response = $this->actingAs(User::role('Endorser')->first())
            ->post(route('procurements.purchase-orders.store', $procurement), $this->poInput());

        $response->assertRedirect();
        $response->assertSessionHasErrors('procurement');
        $this->assertDatabaseMissing('transactions', ['procurement_id' => $procurement->id, 'category' => 'PO']);
    }

    public function test_po_validation_passes_when_pr_exists(): void
    {
        $procurement = $this->createProcurement();
        $this->createPurchaseRequest($procurement);

        $response = $this->actingAs(User::role('Endorser')->first())
            ->post(route('procurements.purchase-orders.store', $procurement), $this->poInput());

        $response->assertRedirect(route('procurements.show', $procurement));
        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('transactions', ['procurement_id' => $procurement->id, 'category' => 'PO']);
    }

    public function test_vch_validation_fails_when_no_po_exists(): void
    {
        $procurement = $this->createProcurement();
        $this->createPurchaseRequest($procurement);

        $response = $this->actingAs(User::role('Endorser')->first())
            ->post(route('procurements.vouchers.store', $procurement), [
                'reference_number' => 'VCH-TEST-001',
                'payee' => 'Demo Payee',
            ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors('procurement');
        $this->assertDatabaseMissing('transactions', ['procurement_id' => $procurement->id, 'category' => 'VCH']);
    }

    public function test_vch_validation_passes_when_po_exists(): void
    {
        $procurement = $this->createProcurement();
        $pr = $this->createPurchaseRequest($procurement);
        $this->createPurchaseOrder($procurement, $pr);

        $response = $this->actingAs(User::role('Endorser')->first())
            ->post(route('procurements.vouchers.store', $procurement), [
                'reference_number' => 'VCH-TEST-001',
                'payee' => 'Demo Payee',
            ]);

        $response->assertRedirect(route('procurements.show', $procurement));
        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('transactions', ['procurement_id' => $procurement->id, 'category' => 'VCH']);
    }

    private function poInput(): array
    {
        return [
            'supplier_id' => Supplier::factory()->create()->id,
            'contract_price' => 10000,
            'ref_year' => '2026',
            'ref_month' => '10',
            'ref_number' => '001',
            'is_continuation' => false,
        ];
    }

    /** @test */
    public function authorization_fails_for_viewer_role_on_po_request(): void
    {
        $user = User::role('Viewer')->first();
        $procurement = $this->createProcurement();

        $request = StorePurchaseOrderRequest::createFrom(
            request()->replace(['procurement_id' => $procurement->id])
        );
        $request->setUserResolver(fn () => $user);

        $this->assertFalse($request->authorize());
    }

    /** @test */
    public function authorization_passes_for_endorser_role_on_po_request(): void
    {
        $user = User::role('Endorser')->first();
        $procurement = $this->createProcurement();

        $request = StorePurchaseOrderRequest::createFrom(
            request()->replace(['procurement_id' => $procurement->id])
        );
        $request->setUserResolver(fn () => $user);

        $this->assertTrue($request->authorize());
    }

    /** @test */
    public function authorization_fails_for_viewer_role_on_vch_request(): void
    {
        $user = User::role('Viewer')->first();
        $procurement = $this->createProcurement();

        $request = StoreVoucherRequest::createFrom(
            request()->replace(['procurement_id' => $procurement->id])
        );
        $request->setUserResolver(fn () => $user);

        $this->assertFalse($request->authorize());
    }

    /** @test */
    public function authorization_passes_for_administrator_role_on_vch_request(): void
    {
        $user = User::role('Administrator')->first();
        $procurement = $this->createProcurement();

        $request = StoreVoucherRequest::createFrom(
            request()->replace(['procurement_id' => $procurement->id])
        );
        $request->setUserResolver(fn () => $user);

        $this->assertTrue($request->authorize());
    }

    private function createProcurement(): Procurement
    {
        $user = User::role('Endorser')->first();
        $office = Office::factory()->create();
        $particular = Particular::factory()->create();

        return Procurement::create([
            'end_user_id' => $office->id,
            'particular_id' => $particular->id,
            'purpose' => 'Test Purpose',
            'abc_amount' => 10000.00,
            'date_of_entry' => now()->toDateString(),
            'status' => Procurement::STATUS_CREATED,
            'created_by_user_id' => $user->id,
        ]);
    }

    private function createPurchaseRequest(Procurement $procurement): Transaction
    {
        $transaction = Transaction::create([
            'procurement_id' => $procurement->id,
            'category' => Transaction::CATEGORY_PURCHASE_REQUEST,
            'reference_number' => 'PR-TEST-'.uniqid(),
            'status' => 'Created',
            'created_by_user_id' => $procurement->created_by_user_id,
        ]);

        PurchaseRequest::factory()->create(['transaction_id' => $transaction->id]);

        return $transaction;
    }

    private function createPurchaseOrder(Procurement $procurement, Transaction $prTransaction): Transaction
    {
        $transaction = Transaction::create([
            'procurement_id' => $procurement->id,
            'category' => Transaction::CATEGORY_PURCHASE_ORDER,
            'reference_number' => 'PO-TEST-'.uniqid(),
            'status' => 'Created',
            'created_by_user_id' => $procurement->created_by_user_id,
        ]);

        PurchaseOrder::factory()->create(['transaction_id' => $transaction->id]);

        return $transaction;
    }
}
