<?php

declare(strict_types=1);

namespace Tests\Unit\Migration;

use App\Services\Migration\ReferenceChainResolver;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;

class ReferenceChainResolverTest extends TestCase
{
    private ReferenceChainResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = new ReferenceChainResolver();
    }

    public function test_resolves_pr_po_vch_chain(): void
    {
        $transactions = new Collection([
            (object) ['id' => 1, 'category' => 'PR', 'reference_id' => 'PR-2024-001', 'sub_reference_id' => null],
            (object) ['id' => 2, 'category' => 'PO', 'reference_id' => 'PO-2024-001', 'sub_reference_id' => 'PR-2024-001'],
            (object) ['id' => 3, 'category' => 'VCH', 'reference_id' => 'VCH-2024-001', 'sub_reference_id' => 'PO-2024-001'],
        ]);

        $groups = $this->resolver->resolve($transactions);

        $this->assertCount(1, $groups);
        $this->assertNotNull($groups[0]['pr']);
        $this->assertEquals('PR-2024-001', $groups[0]['pr']->reference_id);
        $this->assertCount(1, $groups[0]['pos']);
        $this->assertCount(1, $groups[0]['vchs']);
    }

    public function test_orphaned_po_creates_standalone_group(): void
    {
        $transactions = new Collection([
            (object) ['id' => 1, 'category' => 'PO', 'reference_id' => 'PO-2024-001', 'sub_reference_id' => 'PR-MISSING'],
        ]);

        $groups = $this->resolver->resolve($transactions);

        $this->assertCount(1, $groups);
        $this->assertNull($groups[0]['pr']);
        $this->assertCount(1, $groups[0]['pos']);
    }

    public function test_orphaned_vch_creates_standalone_group(): void
    {
        $transactions = new Collection([
            (object) ['id' => 1, 'category' => 'VCH', 'reference_id' => 'VCH-2024-001', 'sub_reference_id' => 'PO-MISSING'],
        ]);

        $groups = $this->resolver->resolve($transactions);

        $this->assertCount(1, $groups);
        $this->assertNull($groups[0]['pr']);
        $this->assertCount(0, $groups[0]['pos']);
        $this->assertCount(1, $groups[0]['vchs']);
    }

    public function test_multiple_pos_linked_to_same_pr(): void
    {
        $transactions = new Collection([
            (object) ['id' => 1, 'category' => 'PR', 'reference_id' => 'PR-2024-001', 'sub_reference_id' => null],
            (object) ['id' => 2, 'category' => 'PO', 'reference_id' => 'PO-2024-001', 'sub_reference_id' => 'PR-2024-001'],
            (object) ['id' => 3, 'category' => 'PO', 'reference_id' => 'PO-2024-002', 'sub_reference_id' => 'PR-2024-001'],
        ]);

        $groups = $this->resolver->resolve($transactions);

        $this->assertCount(1, $groups);
        $this->assertCount(2, $groups[0]['pos']);
    }

    public function test_empty_collection_returns_empty_groups(): void
    {
        $groups = $this->resolver->resolve(new Collection([]));
        $this->assertCount(0, $groups);
    }

    public function test_pr_only_creates_group_without_pos_or_vchs(): void
    {
        $transactions = new Collection([
            (object) ['id' => 1, 'category' => 'PR', 'reference_id' => 'PR-2024-001', 'sub_reference_id' => null],
        ]);

        $groups = $this->resolver->resolve($transactions);

        $this->assertCount(1, $groups);
        $this->assertNotNull($groups[0]['pr']);
        $this->assertCount(0, $groups[0]['pos']);
        $this->assertCount(0, $groups[0]['vchs']);
    }
}
