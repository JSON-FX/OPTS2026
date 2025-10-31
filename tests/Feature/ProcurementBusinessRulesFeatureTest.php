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
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class ProcurementBusinessRulesFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    /** @test */
    public function po_validation_fails_when_no_pr_exists(): void
    {
        $user = User::role('Endorser')->first();
        $procurement = $this->createProcurement();

        $request = StorePurchaseOrderRequest::createFrom(
            request()->replace([
                'procurement_id' => $procurement->id,
                'supplier_id' => 1,
                'supplier_address' => 'Test Address',
                'particulars' => 'Test',
                'fund_type_id' => 1,
                'total_cost' => 10000.00,
                'date_of_po' => now()->toDateString(),
                'delivery_date' => now()->addDays(30)->toDateString(),
                'delivery_term' => 'Test term',
                'payment_term' => 'Test term',
                'amount_in_words' => 'Ten thousand',
                'mode_of_procurement' => 'Public Bidding',
            ])
        );
        $request->setUserResolver(fn () => $user);

        $validator = Validator::make(
            $request->all(),
            $request->rules()
        );

        $this->assertTrue($validator->fails());
        $this->assertStringContainsString(
            'You must create a Purchase Request before adding a Purchase Order for this procurement',
            $validator->errors()->first('procurement_id')
        );
    }

    /** @test */
    public function po_validation_passes_when_pr_exists(): void
    {
        $user = User::role('Endorser')->first();
        $procurement = $this->createProcurement();
        $this->createPurchaseRequest($procurement);

        $request = StorePurchaseOrderRequest::createFrom(
            request()->replace([
                'procurement_id' => $procurement->id,
                'supplier_id' => 1,
                'supplier_address' => 'Test Address',
                'contract_price' => 10000.00,
            ])
        );
        $request->setUserResolver(fn () => $user);

        $validator = Validator::make(
            $request->all(),
            $request->rules()
        );

        $this->assertFalse($validator->fails());
    }

    /** @test */
    public function vch_validation_fails_when_no_po_exists(): void
    {
        $user = User::role('Endorser')->first();
        $procurement = $this->createProcurement();
        $this->createPurchaseRequest($procurement);

        $request = StoreVoucherRequest::createFrom(
            request()->replace([
                'procurement_id' => $procurement->id,
                'supplier_id' => 1,
                'obr_number' => 'OBR-TEST',
                'particulars' => 'Test',
                'gross_amount' => 10000.00,
            ])
        );
        $request->setUserResolver(fn () => $user);

        $validator = Validator::make(
            $request->all(),
            $request->rules()
        );

        $this->assertTrue($validator->fails());
        $this->assertStringContainsString(
            'You must create a Purchase Order before adding a Voucher for this procurement',
            $validator->errors()->first('procurement_id')
        );
    }

    /** @test */
    public function vch_validation_passes_when_po_exists(): void
    {
        $user = User::role('Endorser')->first();
        $procurement = $this->createProcurement();
        $pr = $this->createPurchaseRequest($procurement);
        $this->createPurchaseOrder($procurement, $pr);

        $request = StoreVoucherRequest::createFrom(
            request()->replace([
                'procurement_id' => $procurement->id,
                'payee' => 'Test Payee',
            ])
        );
        $request->setUserResolver(fn () => $user);

        $validator = Validator::make(
            $request->all(),
            $request->rules()
        );

        $this->assertFalse($validator->fails());
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

        PurchaseRequest::create([
            'transaction_id' => $transaction->id,
            'supplier_id' => 1,
            'purpose' => 'Test PR Purpose',
            'estimated_budget' => 10000.00,
            'date_of_pr' => now()->toDateString(),
        ]);

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

        // Get the PurchaseRequest record from the transaction
        $prRecord = PurchaseRequest::where('transaction_id', $prTransaction->id)->first();

        PurchaseOrder::create([
            'transaction_id' => $transaction->id,
            'purchase_request_id' => $prRecord->id,
            'supplier_id' => 1,
            'supplier_address' => 'Test Address',
            'particulars' => 'Test Particulars',
            'fund_type_id' => 1,
            'total_cost' => 9500.00,
            'date_of_po' => now()->toDateString(),
            'delivery_date' => now()->addDays(30)->toDateString(),
            'delivery_term' => 'Test delivery term',
            'payment_term' => 'Test payment term',
            'amount_in_words' => 'Nine thousand five hundred pesos only',
            'mode_of_procurement' => 'Public Bidding',
        ]);

        return $transaction;
    }
}
