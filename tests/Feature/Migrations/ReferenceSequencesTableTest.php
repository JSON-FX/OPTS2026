<?php

namespace Tests\Feature\Migrations;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ReferenceSequencesTableTest extends TestCase
{
    use RefreshDatabase;

    public function test_reference_sequences_table_structure_and_indexes(): void
    {
        $this->assertTrue(Schema::hasTable('reference_sequences'));

        $columns = [
            'id',
            'category',
            'year',
            'last_sequence',
            'created_at',
            'updated_at',
        ];

        foreach ($columns as $column) {
            $this->assertTrue(Schema::hasColumn('reference_sequences', $column));
        }

        $indexes = collect(DB::select('PRAGMA index_list("reference_sequences")'))->pluck('name');
        $this->assertTrue($indexes->contains('reference_sequences_category_year_unique'));
    }

    public function test_category_year_combination_must_be_unique(): void
    {
        $now = Carbon::now();

        DB::table('reference_sequences')->insert([
            'category' => 'PR',
            'year' => (int) $now->format('Y'),
            'last_sequence' => 10,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->expectException(QueryException::class);

        DB::table('reference_sequences')->insert([
            'category' => 'PR',
            'year' => (int) $now->format('Y'),
            'last_sequence' => 15,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
}

