<?php

namespace Tests\Unit\Services;

use App\Exceptions\ReferenceNumberException;
use App\Services\ReferenceNumberService;
use Carbon\Carbon;
use Exception;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\TestCase;

class ReferenceNumberServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        Mockery::close();
        parent::tearDown();
    }

    public function test_it_generates_sequential_numbers_with_padding(): void
    {
        Carbon::setTestNow('2025-01-01 08:00:00');

        $service = app(ReferenceNumberService::class);

        $first = $service->generateReferenceNumber('pr');
        $second = $service->generateReferenceNumber('PR');

        $this->assertSame('PR-2025-000001', $first);
        $this->assertSame('PR-2025-000002', $second);

        $this->assertDatabaseHas('reference_sequences', [
            'category' => 'PR',
            'year' => 2025,
            'last_sequence' => 2,
        ]);
    }

    public function test_it_resets_sequence_for_new_year(): void
    {
        Carbon::setTestNow('2025-12-31 23:55:00');
        $service = app(ReferenceNumberService::class);
        $service->generateReferenceNumber('PO');

        $this->assertDatabaseHas('reference_sequences', [
            'category' => 'PO',
            'year' => 2025,
            'last_sequence' => 1,
        ]);

        Carbon::setTestNow('2026-01-01 00:05:00');
        $next = $service->generateReferenceNumber('PO');

        $this->assertSame('PO-2026-000001', $next);
        $this->assertDatabaseHas('reference_sequences', [
            'category' => 'PO',
            'year' => 2026,
            'last_sequence' => 1,
        ]);
    }

    public function test_it_extends_padding_when_sequence_exceeds_base_digits(): void
    {
        Carbon::setTestNow('2025-01-01 09:00:00');

        DB::table('reference_sequences')->insert([
            'category' => 'VCH',
            'year' => 2025,
            'last_sequence' => 999999,
            'created_at' => now()->subMinute(),
            'updated_at' => now()->subMinute(),
        ]);

        $service = app(ReferenceNumberService::class);
        $referenceNumber = $service->generateReferenceNumber('VCH');

        $this->assertSame('VCH-2025-1000000', $referenceNumber);
        $this->assertDatabaseHas('reference_sequences', [
            'category' => 'VCH',
            'year' => 2025,
            'last_sequence' => 1000000,
        ]);
    }

    public function test_it_throws_reference_number_exception_when_transaction_fails(): void
    {
        $connection = Mockery::mock(ConnectionInterface::class);
        $exception = new QueryException(
            DB::getDefaultConnection(),
            'select 1',
            [],
            new Exception('deadlock')
        );

        $connection->shouldReceive('transaction')
            ->once()
            ->andThrow($exception);

        $service = new ReferenceNumberService($connection);

        $this->expectException(ReferenceNumberException::class);
        $this->expectExceptionMessage('Unable to generate reference number due to database contention.');

        $service->generateReferenceNumber('PR');
    }

    public function test_validate_unique_reference_returns_true_for_available_reference(): void
    {
        $service = app(ReferenceNumberService::class);

        $isUnique = $service->validateUniqueReference('PR-GAA-2025-10-001');

        $this->assertTrue($isUnique);
    }

    public function test_validate_unique_reference_returns_false_for_existing_reference(): void
    {
        $procurement = \App\Models\Procurement::factory()->create();
        $user = \App\Models\User::factory()->create();

        \App\Models\Transaction::create([
            'procurement_id' => $procurement->id,
            'category' => 'PR',
            'reference_number' => 'PR-GAA-2025-10-001',
            'is_continuation' => false,
            'status' => 'Created',
            'created_by_user_id' => $user->id,
        ]);

        $service = app(ReferenceNumberService::class);

        $isUnique = $service->validateUniqueReference('PR-GAA-2025-10-001');

        $this->assertFalse($isUnique);
    }

    public function test_build_pr_reference_number_formats_correctly_without_continuation(): void
    {
        $service = app(ReferenceNumberService::class);

        $result = $service->buildPRReferenceNumber('GAA', '2025', '10', '001', false);

        $this->assertSame('PR-GAA-2025-10-001', $result);
    }

    public function test_build_pr_reference_number_formats_correctly_with_continuation(): void
    {
        $service = app(ReferenceNumberService::class);

        $result = $service->buildPRReferenceNumber('SEF', '2024', '12', '9999', true);

        $this->assertSame('CONT-PR-SEF-2024-12-9999', $result);
    }

    public function test_build_po_reference_number_formats_correctly_without_continuation(): void
    {
        $service = app(ReferenceNumberService::class);

        $result = $service->buildPOReferenceNumber('2025', '10', '001', false);

        $this->assertSame('PO-2025-10-001', $result);
    }

    public function test_build_po_reference_number_formats_correctly_with_continuation(): void
    {
        $service = app(ReferenceNumberService::class);

        $result = $service->buildPOReferenceNumber('2024', '12', '9999', true);

        $this->assertSame('CONT-PO-2024-12-9999', $result);
    }

    public function test_document_allocation_resets_years_and_keeps_category_counters_independent(): void
    {
        $service = app(ReferenceNumberService::class);
        $this->assertSame('PR-GF-2026-01-001', $service->allocateDocumentReference('PR', '2026', '01', 'GF'));
        $this->assertSame('CONT-PR-SEF-2026-12-002', $service->allocateDocumentReference('PR', '2026', '12', 'SEF', true));
        $this->assertSame('PR-GF-2027-01-001', $service->allocateDocumentReference('PR', '2027', '01', 'GF'));
        $this->assertSame('PO-2026-10-001', $service->allocateDocumentReference('PO', '2026', '10'));
        $this->assertSame('VCH-2026-10-001', $service->allocateDocumentReference('VCH', '2026', '10'));
    }

    public function test_estimate_does_not_reserve_and_allocation_reads_deleted_historical_numbers(): void
    {
        $service = app(ReferenceNumberService::class);
        \App\Models\Transaction::factory()->create(['category' => 'PR', 'reference_number' => 'CONT-PR-SEF-2026-01-145'])->delete();
        \App\Models\Transaction::factory()->create(['category' => 'PR', 'reference_number' => 'PR-GF-2026-02-EXTERNAL']);
        \App\Models\Transaction::factory()->create(['category' => 'PR', 'reference_number' => 'PR-GF-2025-12-999']);
        \App\Models\Transaction::factory()->create(['category' => 'PO', 'reference_number' => 'PO-2026-12-999']);
        $this->assertSame('146', $service->previewSequence('PR', 2026));
        $this->assertSame('146', $service->previewSequence('PR', 2026));
        $this->assertDatabaseCount('reference_sequences', 0);
        $this->assertSame('PR-GF-2026-10-146', $service->allocateDocumentReference('PR', '2026', '10', 'GF'));
    }

    public function test_existing_counter_and_legacy_numeric_references_are_both_respected(): void
    {
        $service = app(ReferenceNumberService::class);
        DB::table('reference_sequences')->insert(['category' => 'VCH', 'year' => 2026, 'last_sequence' => 999, 'created_at' => now(), 'updated_at' => now()]);
        $this->assertSame('VCH-2026-10-1000', $service->allocateDocumentReference('VCH', '2026', '10'));
        \App\Models\Transaction::factory()->create(['category' => 'VCH', 'reference_number' => 'VCH-2026-009999']);
        $this->assertSame('VCH-2026-11-10000', $service->allocateDocumentReference('VCH', '2026', '11'));
    }

    public function test_outer_transaction_rollback_restores_first_use_and_existing_sequence(): void
    {
        $service = app(ReferenceNumberService::class);
        foreach ([false, true] as $existing) {
            if ($existing) {
                $service->allocateDocumentReference('PO', '2026', '10');
            }
            DB::beginTransaction();
            $service->allocateDocumentReference('PO', '2026', '10');
            DB::rollBack();
            $this->assertSame($existing ? '002' : '001', $service->previewSequence('PO', 2026));
        }
    }

    public function test_nonstandard_manual_references_do_not_consume_numbers_and_recognized_ones_do(): void
    {
        $service = app(ReferenceNumberService::class);
        $service->trackManualReference('PO', 'OFFICE/2026/400');
        $this->assertDatabaseCount('reference_sequences', 0);
        $service->trackManualReference('PO', 'CONT-PO-2026-01-200');
        $this->assertSame('PO-2026-10-201', $service->allocateDocumentReference('PO', '2026', '10'));
    }

    public function test_case_insensitive_manual_format_advances_sequence_and_unsigned_limit_never_wraps(): void
    {
        $service = app(ReferenceNumberService::class);
        $service->trackManualReference('PO', 'cont-po-2026-01-100');
        $this->assertSame('PO-2026-02-101', $service->allocateDocumentReference('PO', '2026', '02'));
        DB::table('reference_sequences')->where('category', 'PO')->where('year', 2026)->update(['last_sequence' => 4294967295]);
        $this->expectException(ReferenceNumberException::class);
        $this->expectExceptionMessage('This yearly reference sequence is exhausted.');
        $service->allocateDocumentReference('PO', '2026', '02');
    }

    public function test_global_historical_references_count_even_when_owned_by_another_category(): void
    {
        $service = app(ReferenceNumberService::class);
        \App\Models\Transaction::factory()->create(['category' => 'PO', 'reference_number' => 'pr-gf-2026-10-147'])->delete();
        \App\Models\Transaction::factory()->create(['category' => 'VCH', 'reference_number' => 'PO-2026-10-250']);
        $this->assertSame('148', $service->previewSequence('PR', 2026));
        $this->assertSame('PR-GF-2026-11-148', $service->allocateDocumentReference('PR', '2026', '11', 'GF'));
        $this->assertSame('PO-2026-11-251', $service->allocateDocumentReference('PO', '2026', '11'));
    }

    public function test_manual_references_lock_and_advance_their_prefix_category(): void
    {
        $service = app(ReferenceNumberService::class);
        $service->trackManualReference('PO', 'pr-gf-2026-10-001');
        $this->assertDatabaseHas('reference_sequences', ['category' => 'PR', 'year' => 2026, 'last_sequence' => 1]);
        $this->assertDatabaseMissing('reference_sequences', ['category' => 'PO']);
        $this->assertSame('PR-SEF-2026-11-002', $service->allocateDocumentReference('PR', '2026', '11', 'SEF'));
        $service->trackManualReference('VCH', 'CONT-po-2026-10-100');
        $this->assertSame('PO-2026-12-101', $service->allocateDocumentReference('PO', '2026', '12'));
        $service->trackManualReference('PR', 'vch-2026-10-200');
        $this->assertSame('VCH-2026-12-201', $service->allocateDocumentReference('VCH', '2026', '12'));
    }
}
