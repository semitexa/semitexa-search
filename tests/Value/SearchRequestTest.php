<?php

declare(strict_types=1);

namespace Semitexa\Search\Tests\Value;

use PHPUnit\Framework\TestCase;
use Semitexa\Search\Enum\SearchScope;
use Semitexa\Search\Value\SearchRequest;
use Semitexa\Search\Value\SortClause;

final class SearchRequestTest extends TestCase
{
    public function testCreateMinimalRequest(): void
    {
        $request = new SearchRequest(index: 'platform.users');

        $this->assertSame('platform.users', $request->index);
        $this->assertNull($request->query);
        $this->assertSame([], $request->filters);
        $this->assertSame([], $request->sort);
        $this->assertSame(20, $request->limit);
        $this->assertSame(0, $request->offset);
        $this->assertSame(SearchScope::Tenant, $request->scope);
        $this->assertNull($request->tenantId);
        $this->assertNull($request->plannerTrace);
    }

    public function testCreateFullRequest(): void
    {
        $request = new SearchRequest(
            index: 'platform.users',
            query: 'john',
            filters: ['is_active' => true],
            sort: [new SortClause('name', 'ASC')],
            limit: 50,
            offset: 10,
            scope: SearchScope::Global,
        );

        $this->assertSame('john', $request->query);
        $this->assertSame(['is_active' => true], $request->filters);
        $this->assertCount(1, $request->sort);
        $this->assertSame(50, $request->limit);
        $this->assertSame(10, $request->offset);
        $this->assertSame(SearchScope::Global, $request->scope);
    }

    public function testEmptyIndexThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new SearchRequest(index: '');
    }

    public function testZeroLimitThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new SearchRequest(index: 'test', limit: 0);
    }

    public function testNegativeOffsetThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new SearchRequest(index: 'test', offset: -1);
    }

    public function testEmptyStringQueryThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new SearchRequest(index: 'test', query: '');
    }

    public function testWithReturnsNewInstance(): void
    {
        $original = new SearchRequest(index: 'test', query: 'hello');
        $modified = $original->with(query: 'world', limit: 50);

        $this->assertSame('hello', $original->query);
        $this->assertSame(20, $original->limit);

        $this->assertSame('world', $modified->query);
        $this->assertSame(50, $modified->limit);
        $this->assertSame('test', $modified->index);
    }
}
