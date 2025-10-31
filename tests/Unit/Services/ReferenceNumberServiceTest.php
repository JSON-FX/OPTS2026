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
}
