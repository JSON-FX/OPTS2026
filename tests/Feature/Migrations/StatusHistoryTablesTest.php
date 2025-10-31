<?php

namespace Tests\Feature\Migrations;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\MigrationTestHelper;
use Tests\TestCase;

class StatusHistoryTablesTest extends TestCase
{
    use MigrationTestHelper;
    use RefreshDatabase;

    public function test_procurement_status_history_table_structure(): void
    {
        $this->assertTrue(Schema::hasTable('procurement_status_history'));

        $columns = [
            'id',
            'procurement_id',
            'old_status',
            'new_status',
            'reason',
            'changed_by_user_id',
            'created_at',
        ];

        foreach ($columns as $column) {
            $this->assertTrue(Schema::hasColumn('procurement_status_history', $column));
        }

        $indexes = collect(DB::select('PRAGMA index_list("procurement_status_history")'))->pluck('name');
        $this->assertTrue($indexes->contains('procurement_status_history_procurement_id_created_at_index'));
    }

    public function test_transaction_status_history_table_structure(): void
    {
        $this->assertTrue(Schema::hasTable('transaction_status_history'));

        $columns = [
            'id',
            'transaction_id',
            'old_status',
            'new_status',
            'reason',
            'changed_by_user_id',
            'created_at',
        ];

        foreach ($columns as $column) {
            $this->assertTrue(Schema::hasColumn('transaction_status_history', $column));
        }

        $indexes = collect(DB::select('PRAGMA index_list("transaction_status_history")'))->pluck('name');
        $this->assertTrue($indexes->contains('transaction_status_history_transaction_id_created_at_index'));
    }

    public function test_procurement_history_rows_cascade_on_delete(): void
    {
        $procurement = $this->createProcurement();
        $userId = $this->createUser();
        $timestamp = Carbon::now();

        $historyId = DB::table('procurement_status_history')->insertGetId([
            'procurement_id' => $procurement['id'],
            'old_status' => null,
            'new_status' => 'Created',
            'reason' => null,
            'changed_by_user_id' => $userId,
            'created_at' => $timestamp,
        ]);

        DB::table('procurements')->where('id', $procurement['id'])->delete();

        $this->assertDatabaseMissing('procurement_status_history', ['id' => $historyId]);
    }

    public function test_transaction_history_rows_cascade_on_delete(): void
    {
        $transaction = $this->createTransaction();
        $userId = $this->createUser();
        $timestamp = Carbon::now();

        $historyId = DB::table('transaction_status_history')->insertGetId([
            'transaction_id' => $transaction['id'],
            'old_status' => null,
            'new_status' => 'Created',
            'reason' => null,
            'changed_by_user_id' => $userId,
            'created_at' => $timestamp,
        ]);

        DB::table('transactions')->where('id', $transaction['id'])->delete();

        $this->assertDatabaseMissing('transaction_status_history', ['id' => $historyId]);
    }
}
