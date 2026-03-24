<?php

declare(strict_types=1);

namespace Semitexa\Search\Tests\Value;

use PHPUnit\Framework\TestCase;
use Semitexa\Search\Value\SearchHit;
use Semitexa\Search\Value\SearchResult;

final class SearchResultTest extends TestCase
{
    public function testEmptyResult(): void
    {
        $result = new SearchResult(hits: [], total: 0);

        $this->assertTrue($result->isEmpty());
        $this->assertSame(0, $result->count());
        $this->assertSame(0, $result->total);
        $this->assertFalse($result->partial);
    }

    public function testPartialResult(): void
    {
        $hit = new SearchHit(
            documentId: '1',
            index: 'test',
            type: 'user',
            score: 1.0,
            fields: ['name' => 'John'],
        );

        $result = new SearchResult(hits: [$hit], total: 100, partial: true);

        $this->assertFalse($result->isEmpty());
        $this->assertSame(1, $result->count());
        $this->assertSame(100, $result->total);
        $this->assertTrue($result->partial);
    }

    public function testMetadata(): void
    {
        $result = new SearchResult(
            hits: [],
            total: 0,
            metadata: ['backend' => 'orm', 'latency_ms' => 12.5],
        );

        $this->assertSame('orm', $result->metadata['backend']);
        $this->assertSame(12.5, $result->metadata['latency_ms']);
    }
}
