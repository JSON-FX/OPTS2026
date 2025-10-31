<?php

namespace Tests\Feature\Migrations;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\MigrationTestHelper;
use Tests\TestCase;

class ProcurementsTableTest extends TestCase
{
    use RefreshDatabase;
    use MigrationTestHelper;

    public function test_procurements_table_structure(): void
    {
        $this->assertTrue(Schema::hasTable('procurements'));

        $columns = [
            'id',
            'end_user_id',
            'particular_id',
            'purpose',
            'abc_amount',
            'date_of_entry',
            'status',
            'created_by_user_id',
            'created_at',
            'updated_at',
            'deleted_at',
        ];

        foreach ($columns as $column) {
            $this->assertTrue(
                Schema::hasColumn('procurements', $column),
                sprintf('Expected column %s to exist on procurements table.', $column)
            );
        }

        $indexes = collect(DB::select('PRAGMA index_list("procurements")'))->pluck('name');
        $this->assertTrue($indexes->contains('procurements_status_index'));
        $this->assertTrue($indexes->contains('procurements_date_of_entry_index'));
    }

    public function test_foreign_keys_restrict_deletion(): void
    {
        $procurement = $this->createProcurement();

        $this->expectException(QueryException::class);
        DB::table('users')->where('id', $procurement['created_by_user_id'])->delete();
    }

    public function test_soft_deletes_field_exists(): void
    {
        $procurement = $this->createProcurement();

        DB::table('procurements')
            ->where('id', $procurement['id'])
            ->update(['deleted_at' => now()]);

        $this->assertNotNull(
            DB::table('procurements')->where('id', $procurement['id'])->value('deleted_at')
        );
    }
}

