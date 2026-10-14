<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\Office;
use App\Models\Particular;
use App\Models\Procurement;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Voucher;
use App\Services\ProcurementBusinessRules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProcurementBusinessRulesTest extends TestCase
{
    use RefreshDatabase;

    private ProcurementBusinessRules $businessRules;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RoleSeeder::class);
        User::factory()->create(['office_id' => Office::factory()->create()->id])->assignRole('Endorser');
        $this->businessRules = app(ProcurementBusinessRules::class);
    }

    /** @test */
    public function can_create_pr_returns_true_when_no_pr_exists(): void
    {
        $procurement = $this->createProcurement();

        $result = $this->businessRules->canCreatePR($procurement);

        $this->assertTrue($result);
    }

    /** @test */
    public function can_create_pr_returns_false_when_pr_exists(): void
    {
        $procurement = $this->createProcurement();
        $this->createPurchaseRequest($procurement);

        $result = $this->businessRules->canCreatePR($procurement);

        $this->assertFalse($result);
    }

    /** @test */
    public function can_create_po_returns_false_when_no_pr_exists(): void
    {
        $procurement = $this->createProcurement();

        $result = $this->businessRules->canCreatePO($procurement);

        $this->assertFalse($result);
    }

    /** @test */
    public function can_create_po_returns_true_when_pr_exists_and_no_po_exists(): void
    {
        $procurement = $this->createProcurement();
        $this->createPurchaseRequest($procurement);

        $result = $this->businessRules->canCreatePO($procurement);

        $this->assertTrue($result);
    }

    /** @test */
    public function can_create_po_returns_false_when_po_already_exists(): void
    {
        $procurement = $this->createProcurement();
        $this->createPurchaseRequest($procurement);
        $this->createPurchaseOrder($procurement);

        $result = $this->businessRules->canCreatePO($procurement);

        $this->assertFalse($result);
    }

    /** @test */
    public function can_create_vch_returns_false_when_no_po_exists(): void
    {
        $procurement = $this->createProcurement();
        $this->createPurchaseRequest($procurement);

        $result = $this->businessRules->canCreateVCH($procurement);

        $this->assertFalse($result);
    }

    /** @test */
    public function can_create_vch_returns_true_when_po_exists_and_no_vch_exists(): void
    {
        $procurement = $this->createProcurement();
        $this->createPurchaseRequest($procurement);
        $this->createPurchaseOrder($procurement);

        $result = $this->businessRules->canCreateVCH($procurement);

        $this->assertTrue($result);
    }

    /** @test */
    public function can_create_vch_returns_false_when_vch_already_exists(): void
    {
        $procurement = $this->createProcurement();
        $this->createPurchaseRequest($procurement);
        $this->createPurchaseOrder($procurement);
        $this->createVoucher($procurement);

        $result = $this->businessRules->canCreateVCH($procurement);

        $this->assertFalse($result);
    }

    /** @test */
    public function can_delete_pr_returns_true_when_no_po_exists(): void
    {
        $procurement = $this->createProcurement();
        $this->createPurchaseRequest($procurement);

        $result = $this->businessRules->canDeletePR($procurement);

        $this->assertTrue($result);
    }

    /** @test */
    public function can_delete_pr_returns_false_when_po_exists(): void
    {
        $procurement = $this->createProcurement();
        $this->createPurchaseRequest($procurement);
        $this->createPurchaseOrder($procurement);

        $result = $this->businessRules->canDeletePR($procurement);

        $this->assertFalse($result);
    }

    /** @test */
    public function can_delete_po_returns_true_when_no_vch_exists(): void
    {
        $procurement = $this->createProcurement();
        $this->createPurchaseRequest($procurement);
        $this->createPurchaseOrder($procurement);

        $result = $this->businessRules->canDeletePO($procurement);

        $this->assertTrue($result);
    }

    /** @test */
    public function can_delete_po_returns_false_when_vch_exists(): void
    {
        $procurement = $this->createProcurement();
        $this->createPurchaseRequest($procurement);
        $this->createPurchaseOrder($procurement);
        $this->createVoucher($procurement);

        $result = $this->businessRules->canDeletePO($procurement);

        $this->assertFalse($result);
    }

    /** @test */
    public function soft_deleted_transactions_do_not_block_creation(): void
    {
        $procurement = $this->createProcurement();
        $pr = $this->createPurchaseRequest($procurement);
        $pr->delete(); // Soft delete

        $result = $this->businessRules->canCreatePR($procurement);

        $this->assertTrue($result, 'Soft-deleted PR should not block new PR creation');
    }

    /** @test */
    public function soft_deleted_po_does_not_block_pr_deletion(): void
    {
        $procurement = $this->createProcurement();
        $this->createPurchaseRequest($procurement);
        $po = $this->createPurchaseOrder($procurement);
        $po->delete(); // Soft delete

        $result = $this->businessRules->canDeletePR($procurement);

        $this->assertTrue($result, 'Soft-deleted PO should not block PR deletion');
    }

    /** @test */
    public function soft_deleted_vch_does_not_block_po_deletion(): void
    {
        $procurement = $this->createProcurement();
        $this->createPurchaseRequest($procurement);
        $this->createPurchaseOrder($procurement);
        $vch = $this->createVoucher($procurement);
        $vch->delete(); // Soft delete

        $result = $this->businessRules->canDeletePO($procurement);

        $this->assertTrue($result, 'Soft-deleted VCH should not block PO deletion');
    }

    /** @test */
    public function rules_work_with_multiple_different_procurements(): void
    {
        $procurement1 = $this->createProcurement();
        $procurement2 = $this->createProcurement();

        $this->createPurchaseRequest($procurement1);

        $this->assertTrue($this->businessRules->canCreatePO($procurement1));
        $this->assertFalse($this->businessRules->canCreatePO($procurement2));
        $this->assertFalse($this->businessRules->canCreatePR($procurement1));
        $this->assertTrue($this->businessRules->canCreatePR($procurement2));
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

    private function createPurchaseOrder(Procurement $procurement): Transaction
    {
        // Ensure PR exists first
        $pr = $procurement->purchaseRequest()->first();
        if (! $pr) {
            $pr = $this->createPurchaseRequest($procurement);
        }

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

    private function createVoucher(Procurement $procurement): Transaction
    {
        // Ensure PO exists first
        $po = $procurement->purchaseOrder()->first();
        if (! $po) {
            $po = $this->createPurchaseOrder($procurement);
        }

        $transaction = Transaction::create([
            'procurement_id' => $procurement->id,
            'category' => Transaction::CATEGORY_VOUCHER,
            'reference_number' => 'VCH-TEST-'.uniqid(),
            'status' => 'Created',
            'created_by_user_id' => $procurement->created_by_user_id,
        ]);

        Voucher::factory()->create(['transaction_id' => $transaction->id]);

        return $transaction;
    }
}
