<?php

namespace Tests\Support;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

trait MigrationTestHelper
{
    protected function createOffice(array $overrides = []): int
    {
        $now = Carbon::now();

        return DB::table('offices')->insertGetId(array_merge([
            'name' => $overrides['name'] ?? 'Office '.Str::uuid(),
            'type' => $overrides['type'] ?? 'Administrative',
            'abbreviation' => $overrides['abbreviation'] ?? Str::upper(Str::random(6)),
            'is_active' => $overrides['is_active'] ?? true,
            'created_at' => $overrides['created_at'] ?? $now,
            'updated_at' => $overrides['updated_at'] ?? $now,
            'deleted_at' => $overrides['deleted_at'] ?? null,
        ], $overrides));
    }

    protected function createParticular(array $overrides = []): int
    {
        $now = Carbon::now();

        return DB::table('particulars')->insertGetId(array_merge([
            'description' => $overrides['description'] ?? 'Particular '.Str::uuid(),
            'is_active' => $overrides['is_active'] ?? true,
            'created_at' => $overrides['created_at'] ?? $now,
            'updated_at' => $overrides['updated_at'] ?? $now,
            'deleted_at' => $overrides['deleted_at'] ?? null,
        ], $overrides));
    }

    protected function createFundType(array $overrides = []): int
    {
        $now = Carbon::now();

        return DB::table('fund_types')->insertGetId(array_merge([
            'name' => $overrides['name'] ?? 'General Fund',
            'abbreviation' => $overrides['abbreviation'] ?? Str::upper(Str::random(4)),
            'is_active' => $overrides['is_active'] ?? true,
            'created_at' => $overrides['created_at'] ?? $now,
            'updated_at' => $overrides['updated_at'] ?? $now,
            'deleted_at' => $overrides['deleted_at'] ?? null,
        ], $overrides));
    }

    protected function createSupplier(array $overrides = []): int
    {
        $now = Carbon::now();

        return DB::table('suppliers')->insertGetId(array_merge([
            'name' => $overrides['name'] ?? 'Supplier '.Str::uuid(),
            'address' => $overrides['address'] ?? '123 Supplier Street',
            'contact_person' => $overrides['contact_person'] ?? null,
            'contact_number' => $overrides['contact_number'] ?? null,
            'is_active' => $overrides['is_active'] ?? true,
            'created_at' => $overrides['created_at'] ?? $now,
            'updated_at' => $overrides['updated_at'] ?? $now,
            'deleted_at' => $overrides['deleted_at'] ?? null,
        ], $overrides));
    }

    protected function createUser(array $overrides = []): int
    {
        $now = Carbon::now();
        $officeId = $overrides['office_id'] ?? $this->createOffice();

        return DB::table('users')->insertGetId(array_merge([
            'name' => $overrides['name'] ?? 'User '.Str::uuid(),
            'email' => $overrides['email'] ?? Str::slug('user-'.Str::uuid()).'@example.com',
            'email_verified_at' => $overrides['email_verified_at'] ?? $now,
            'password' => $overrides['password'] ?? bcrypt('password'),
            'office_id' => $officeId,
            'is_active' => $overrides['is_active'] ?? true,
            'remember_token' => $overrides['remember_token'] ?? Str::random(10),
            'created_at' => $overrides['created_at'] ?? $now,
            'updated_at' => $overrides['updated_at'] ?? $now,
        ], $overrides));
    }

    protected function createWorkflow(string $category = 'PR', array $overrides = []): int
    {
        $now = Carbon::now();

        return DB::table('workflows')->insertGetId(array_merge([
            'category' => $category,
            'name' => $overrides['name'] ?? sprintf('%s Workflow %s', $category, Str::upper(Str::random(4))),
            'is_active' => $overrides['is_active'] ?? true,
            'created_at' => $now,
            'updated_at' => $now,
        ], $overrides));
    }

    protected function createProcurement(array $overrides = []): array
    {
        $now = Carbon::now();
        $endUserId = $overrides['end_user_id'] ?? $this->createOffice();
        $particularId = $overrides['particular_id'] ?? $this->createParticular();
        $creatorId = $overrides['created_by_user_id'] ?? $this->createUser(['office_id' => $endUserId]);

        $data = array_merge([
            'end_user_id' => $endUserId,
            'particular_id' => $particularId,
            'purpose' => $overrides['purpose'] ?? 'Purchase office supplies and related materials.',
            'abc_amount' => $overrides['abc_amount'] ?? 150000.00,
            'date_of_entry' => $overrides['date_of_entry'] ?? $now->copy()->subDays(10)->format('Y-m-d'),
            'status' => $overrides['status'] ?? 'Created',
            'created_by_user_id' => $creatorId,
            'created_at' => $overrides['created_at'] ?? $now->copy()->subDays(10),
            'updated_at' => $overrides['updated_at'] ?? $now->copy()->subDays(5),
            'deleted_at' => $overrides['deleted_at'] ?? null,
        ], $overrides);

        $data['id'] = DB::table('procurements')->insertGetId($data);

        return $data;
    }

    protected function createTransaction(array $overrides = []): array
    {
        $category = $overrides['category'] ?? 'PR';
        $workflowId = $overrides['workflow_id'] ?? $this->createWorkflow($category);
        $now = Carbon::now();

        $data = array_merge([
            'procurement_id' => $overrides['procurement_id'] ?? $this->createProcurement()['id'],
            'category' => $category,
            'reference_number' => $overrides['reference_number'] ?? sprintf('%s-%s', $category, Str::upper(Str::random(6))),
            'status' => $overrides['status'] ?? 'Created',
            'workflow_id' => $workflowId,
            'current_office_id' => $overrides['current_office_id'] ?? null,
            'current_user_id' => $overrides['current_user_id'] ?? null,
            'created_by_user_id' => $overrides['created_by_user_id'] ?? $this->createUser(),
            'created_at' => $overrides['created_at'] ?? $now,
            'updated_at' => $overrides['updated_at'] ?? $now,
            'deleted_at' => $overrides['deleted_at'] ?? null,
        ], $overrides);

        $data['id'] = DB::table('transactions')->insertGetId($data);

        return $data;
    }

    protected function createPurchaseRequest(int $transactionId, array $overrides = []): array
    {
        $now = Carbon::now();
        $supplierId = $overrides['supplier_id'] ?? $this->createSupplier();

        $data = array_merge([
            'transaction_id' => $transactionId,
            'supplier_id' => $supplierId,
            'purpose' => $overrides['purpose'] ?? 'Request for procurement of IT equipment.',
            'estimated_budget' => $overrides['estimated_budget'] ?? 250000.00,
            'date_of_pr' => $overrides['date_of_pr'] ?? $now->format('Y-m-d'),
            'created_at' => $overrides['created_at'] ?? $now,
            'updated_at' => $overrides['updated_at'] ?? $now,
        ], $overrides);

        $data['id'] = DB::table('purchase_requests')->insertGetId($data);

        return $data;
    }

    protected function createPurchaseOrder(int $transactionId, int $purchaseRequestId, array $overrides = []): array
    {
        $now = Carbon::now();
        $supplierId = $overrides['supplier_id'] ?? $this->createSupplier();
        $fundTypeId = $overrides['fund_type_id'] ?? $this->createFundType();

        $data = array_merge([
            'transaction_id' => $transactionId,
            'supplier_id' => $supplierId,
            'supplier_address' => $overrides['supplier_address'] ?? '456 Supplier Avenue',
            'purchase_request_id' => $purchaseRequestId,
            'particulars' => $overrides['particulars'] ?? 'Laptop computers and accessories.',
            'fund_type_id' => $fundTypeId,
            'total_cost' => $overrides['total_cost'] ?? 275000.00,
            'date_of_po' => $overrides['date_of_po'] ?? $now->format('Y-m-d'),
            'delivery_date' => $overrides['delivery_date'] ?? $now->copy()->addDays(14)->format('Y-m-d'),
            'delivery_term' => $overrides['delivery_term'] ?? 14,
            'payment_term' => $overrides['payment_term'] ?? 30,
            'amount_in_words' => $overrides['amount_in_words'] ?? 'Two Hundred Seventy Five Thousand Pesos Only',
            'mode_of_procurement' => $overrides['mode_of_procurement'] ?? 'Public Bidding',
            'created_at' => $overrides['created_at'] ?? $now,
            'updated_at' => $overrides['updated_at'] ?? $now,
        ], $overrides);

        $data['id'] = DB::table('purchase_orders')->insertGetId($data);

        return $data;
    }

    protected function createVoucher(int $transactionId, int $purchaseOrderId, array $overrides = []): array
    {
        $now = Carbon::now();
        $supplierId = $overrides['supplier_id'] ?? $this->createSupplier();

        $data = array_merge([
            'transaction_id' => $transactionId,
            'purchase_order_id' => $purchaseOrderId,
            'supplier_id' => $supplierId,
            'obr_number' => $overrides['obr_number'] ?? 'OBR-2025-001',
            'particulars' => $overrides['particulars'] ?? 'Payment for delivered IT equipment.',
            'gross_amount' => $overrides['gross_amount'] ?? 300000.00,
            'created_at' => $overrides['created_at'] ?? $now,
            'updated_at' => $overrides['updated_at'] ?? $now,
        ], $overrides);

        $data['id'] = DB::table('vouchers')->insertGetId($data);

        return $data;
    }
}
