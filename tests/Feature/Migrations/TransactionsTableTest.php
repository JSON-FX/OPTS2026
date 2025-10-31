<?php

namespace Tests\Feature\Migrations;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\MigrationTestHelper;
use Tests\TestCase;

class TransactionsTableTest extends TestCase
{
    use RefreshDatabase;
    use MigrationTestHelper;

    public function test_transactions_table_structure_and_indexes(): void
    {
        $this->assertTrue(Schema::hasTable('transactions'));

        $columns = [
            'id',
            'procurement_id',
            'category',
            'reference_number',
            'status',
            'workflow_id',
            'current_office_id',
            'current_user_id',
            'created_by_user_id',
            'created_at',
            'updated_at',
            'deleted_at',
        ];

        foreach ($columns as $column) {
            $this->assertTrue(
                Schema::hasColumn('transactions', $column),
                sprintf('Expected column %s to exist on transactions table.', $column)
            );
        }

        $indexes = collect(DB::select('PRAGMA index_list("transactions")'))->pluck('name');
        $this->assertTrue($indexes->contains('transactions_reference_number_unique'));
        $this->assertTrue($indexes->contains('transactions_procurement_id_index'));
        $this->assertTrue($indexes->contains('transactions_category_index'));
        $this->assertTrue($indexes->contains('transactions_status_index'));
        $this->assertTrue($indexes->contains('transactions_procurement_id_category_index'));
    }

    public function test_reference_number_must_be_unique(): void
    {
        $procurement = $this->createProcurement();
        $workflowId = $this->createWorkflow('PR');
        $creatorId = $procurement['created_by_user_id'];
        $reference = 'PR-REF-2025-0001';
        $now = Carbon::now();

        DB::table('transactions')->insert([
            'procurement_id' => $procurement['id'],
            'category' => 'PR',
            'reference_number' => $reference,
            'status' => 'Created',
            'workflow_id' => $workflowId,
            'current_office_id' => null,
            'current_user_id' => null,
            'created_by_user_id' => $creatorId,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);

        $this->expectException(QueryException::class);

        DB::table('transactions')->insert([
            'procurement_id' => $procurement['id'],
            'category' => 'PR',
            'reference_number' => $reference,
            'status' => 'Created',
            'workflow_id' => $workflowId,
            'current_office_id' => null,
            'current_user_id' => null,
            'created_by_user_id' => $creatorId,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
    }

    public function test_foreign_keys_restrict_parent_deletions(): void
    {
        $transaction = $this->createTransaction();

        $this->expectException(QueryException::class);
        DB::table('procurements')->where('id', $transaction['procurement_id'])->delete();
    }

    public function test_soft_deletes_field_exists(): void
    {
        $transaction = $this->createTransaction();

        DB::table('transactions')
            ->where('id', $transaction['id'])
            ->update(['deleted_at' => now()]);

        $this->assertNotNull(
            DB::table('transactions')->where('id', $transaction['id'])->value('deleted_at')
        );
    }
}

