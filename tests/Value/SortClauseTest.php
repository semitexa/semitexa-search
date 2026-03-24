<?php

declare(strict_types=1);

namespace Semitexa\Search\Tests\Value;

use PHPUnit\Framework\TestCase;
use Semitexa\Search\Value\SortClause;

final class SortClauseTest extends TestCase
{
    public function testValidDirections(): void
    {
        $asc = new SortClause('name', 'ASC');
        $this->assertSame('ASC', $asc->normalizedDirection());

        $desc = new SortClause('name', 'desc');
        $this->assertSame('DESC', $desc->normalizedDirection());
    }

    public function testInvalidDirectionThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new SortClause('name', 'INVALID');
    }

    public function testDefaultDirectionIsAsc(): void
    {
        $clause = new SortClause('name');
        $this->assertSame('ASC', $clause->normalizedDirection());
    }
}
