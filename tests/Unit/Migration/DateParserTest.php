<?php

declare(strict_types=1);

namespace Tests\Unit\Migration;

use App\Services\Migration\DateParser;
use Tests\TestCase;

class DateParserTest extends TestCase
{
    private DateParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new DateParser();
    }

    public function test_parses_iso_format(): void
    {
        $result = $this->parser->parse('2024-01-15');
        $this->assertNotNull($result);
        $this->assertEquals('2024-01-15', $result->toDateString());
    }

    public function test_parses_iso_datetime_format(): void
    {
        $result = $this->parser->parse('2024-01-15 10:30:00');
        $this->assertNotNull($result);
        $this->assertEquals('2024-01-15', $result->toDateString());
    }

    public function test_parses_us_format(): void
    {
        $result = $this->parser->parse('01/15/2024');
        $this->assertNotNull($result);
        $this->assertEquals('2024-01-15', $result->toDateString());
    }

    public function test_parses_named_month_format(): void
    {
        $result = $this->parser->parse('Jan 15, 2024');
        $this->assertNotNull($result);
        $this->assertEquals('2024-01-15', $result->toDateString());
    }

    public function test_returns_null_for_empty_string(): void
    {
        $this->assertNull($this->parser->parse(''));
    }

    public function test_returns_null_for_null(): void
    {
        $this->assertNull($this->parser->parse(null));
    }

    public function test_returns_null_for_garbage(): void
    {
        $this->assertNull($this->parser->parse('not-a-date'));
    }

    public function test_handles_whitespace(): void
    {
        $result = $this->parser->parse('  2024-01-15  ');
        $this->assertNotNull($result);
        $this->assertEquals('2024-01-15', $result->toDateString());
    }
}
