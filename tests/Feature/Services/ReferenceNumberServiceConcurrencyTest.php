<?php

namespace Tests\Feature\Services;

use App\Services\ReferenceNumberService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReferenceNumberServiceConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        parent::tearDown();
        Carbon::setTestNow();
    }

    public function test_generating_many_numbers_remains_unique_and_sequential(): void
    {
        Carbon::setTestNow('2025-02-01 10:00:00');
        $service = app(ReferenceNumberService::class);

        $references = collect(range(1, 100))->map(function () use ($service) {
            return $service->generateReferenceNumber('PO');
        });

        $this->assertCount(100, $references);
        $this->assertCount(100, $references->unique());
        $this->assertSame('PO-2025-000001', $references->first());
        $this->assertSame('PO-2025-000100', $references->last());
    }
}
