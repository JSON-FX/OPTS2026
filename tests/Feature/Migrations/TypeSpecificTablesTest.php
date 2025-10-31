<?php

namespace Tests\Feature\Migrations;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\MigrationTestHelper;
use Tests\TestCase;

class TypeSpecificTablesTest extends TestCase
{
    use RefreshDatabase;
    use MigrationTestHelper;

    public function test_purchase_requests_table_structure_and_constraints(): void
    {
        $this->assertTrue(Schema::hasTable('purchase_requests'));

        $columns = [
            'id',
            'transaction_id',
            'supplier_id',
            'purpose',
            'estimated_budget',
            'date_of_pr',
            'created_at',
            'updated_at',
        ];

        foreach ($columns as $column) {
            $this->assertTrue(Schema::hasColumn('purchase_requests', $column));
        }

        $hasUniqueIndex = collect(DB::select('PRAGMA index_list("purchase_requests")'))
            ->contains(function ($index): bool {
                if ((int) $index->unique !== 1) {
                    return false;
                }

                $columns = collect(DB::select(sprintf('PRAGMA index_info("%s")', $index->name)))->pluck('name');

                return $columns->count() === 1 && $columns->contains('transaction_id');
            });
        $this->assertTrue($hasUniqueIndex);

        $transaction = $this->createTransaction(['category' => 'PR']);
        $purchaseRequest = $this->createPurchaseRequest($transaction['id']);

        $this->expectException(QueryException::class);
        $this->createPurchaseRequest($transaction['id'], ['supplier_id' => $this->createSupplier()]);

        $this->expectException(QueryException::class);
        DB::table('transactions')->where('id', $purchaseRequest['transaction_id'])->delete();
    }

    public function test_purchase_orders_table_structure_and_constraints(): void
    {
        $this->assertTrue(Schema::hasTable('purchase_orders'));

        $columns = [
            'id',
            'transaction_id',
            'supplier_id',
            'supplier_address',
            'purchase_request_id',
            'particulars',
            'fund_type_id',
            'total_cost',
            'date_of_po',
            'delivery_date',
            'delivery_term',
            'payment_term',
            'amount_in_words',
            'mode_of_procurement',
            'created_at',
            'updated_at',
        ];

        foreach ($columns as $column) {
            $this->assertTrue(Schema::hasColumn('purchase_orders', $column));
        }

        $hasUniqueIndex = collect(DB::select('PRAGMA index_list("purchase_orders")'))
            ->contains(function ($index): bool {
                if ((int) $index->unique !== 1) {
                    return false;
                }

                $columns = collect(DB::select(sprintf('PRAGMA index_info("%s")', $index->name)))->pluck('name');

                return $columns->count() === 1 && $columns->contains('transaction_id');
            });
        $this->assertTrue($hasUniqueIndex);

        $procurement = $this->createProcurement();
        $prTransaction = $this->createTransaction(['procurement_id' => $procurement['id'], 'category' => 'PR']);
        $purchaseRequest = $this->createPurchaseRequest($prTransaction['id']);
        $poTransaction = $this->createTransaction(['procurement_id' => $procurement['id'], 'category' => 'PO']);
        $purchaseOrder = $this->createPurchaseOrder(
            $poTransaction['id'],
            $purchaseRequest['id'],
            ['supplier_id' => $purchaseRequest['supplier_id']]
        );

        $this->expectException(QueryException::class);
        $this->createPurchaseOrder(
            $poTransaction['id'],
            $purchaseRequest['id'],
            [
                'supplier_id' => $purchaseRequest['supplier_id'],
                'fund_type_id' => $purchaseOrder['fund_type_id'],
            ]
        );

        $this->expectException(QueryException::class);
        DB::table('purchase_requests')->where('id', $purchaseOrder['purchase_request_id'])->delete();
    }

    public function test_vouchers_table_structure_and_constraints(): void
    {
        $this->assertTrue(Schema::hasTable('vouchers'));

        $columns = [
            'id',
            'transaction_id',
            'purchase_order_id',
            'supplier_id',
            'obr_number',
            'particulars',
            'gross_amount',
            'created_at',
            'updated_at',
        ];

        foreach ($columns as $column) {
            $this->assertTrue(Schema::hasColumn('vouchers', $column));
        }

        $hasUniqueIndex = collect(DB::select('PRAGMA index_list("vouchers")'))
            ->contains(function ($index): bool {
                if ((int) $index->unique !== 1) {
                    return false;
                }

                $columns = collect(DB::select(sprintf('PRAGMA index_info("%s")', $index->name)))->pluck('name');

                return $columns->count() === 1 && $columns->contains('transaction_id');
            });
        $this->assertTrue($hasUniqueIndex);

        $procurement = $this->createProcurement();
        $prTransaction = $this->createTransaction(['procurement_id' => $procurement['id'], 'category' => 'PR']);
        $purchaseRequest = $this->createPurchaseRequest($prTransaction['id']);
        $poTransaction = $this->createTransaction(['procurement_id' => $procurement['id'], 'category' => 'PO']);
        $purchaseOrder = $this->createPurchaseOrder(
            $poTransaction['id'],
            $purchaseRequest['id'],
            ['supplier_id' => $purchaseRequest['supplier_id']]
        );
        $voucherTransaction = $this->createTransaction(['procurement_id' => $procurement['id'], 'category' => 'VCH']);
        $voucher = $this->createVoucher(
            $voucherTransaction['id'],
            $purchaseOrder['id'],
            ['supplier_id' => $purchaseOrder['supplier_id']]
        );

        $this->expectException(QueryException::class);
        $this->createVoucher(
            $voucherTransaction['id'],
            $purchaseOrder['id'],
            ['supplier_id' => $purchaseOrder['supplier_id']]
        );

        $this->expectException(QueryException::class);
        DB::table('purchase_orders')->where('id', $voucher['purchase_order_id'])->delete();
    }
}
