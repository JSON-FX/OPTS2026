<?php

declare(strict_types=1);

namespace Tests\Unit\Migration;

use Tests\TestCase;

class EttsMapperTest extends TestCase
{
    public function test_status_mapping_returns_correct_values(): void
    {
        // Test the config-based status mapping directly
        $mapping = config('etts_migration.status_mapping');

        $this->assertEquals('Created', $mapping[1]);
        $this->assertEquals('In Progress', $mapping[2]);
        $this->assertEquals('Cancelled', $mapping[3]);
        $this->assertEquals('Completed', $mapping[4]);
    }

    public function test_office_mapping_config_exists(): void
    {
        $mapping = config('etts_migration.office_mapping');

        $this->assertIsArray($mapping);
        $this->assertArrayHasKey('PBO', $mapping);
        $this->assertEquals('PBO', $mapping['PBO']);
    }

    public function test_mmo_consolidation_mapping(): void
    {
        $mapping = config('etts_migration.office_mapping');

        $this->assertEquals('MMO', $mapping['MMO-A']);
        $this->assertEquals('MMO', $mapping['MMO-B']);
        $this->assertEquals('MMO', $mapping['MMO-C']);
    }

    public function test_fund_type_prefixes_config(): void
    {
        $prefixes = config('etts_migration.fund_type_prefixes');

        $this->assertContains('GF', $prefixes);
        $this->assertContains('TF', $prefixes);
        $this->assertContains('SEF', $prefixes);
    }
}
