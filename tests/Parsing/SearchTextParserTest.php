<?php

declare(strict_types=1);

namespace Semitexa\Search\Tests\Parsing;

use PHPUnit\Framework\TestCase;
use Semitexa\Search\Enum\SearchFieldType;
use Semitexa\Search\Index\SearchFieldDefinition;
use Semitexa\Search\Index\SearchIndexDefinition;
use Semitexa\Search\Parsing\SearchTextParser;

final class SearchTextParserTest extends TestCase
{
    private SearchTextParser $parser;
    private SearchIndexDefinition $definition;

    protected function setUp(): void
    {
        $this->parser = new SearchTextParser();
        $this->definition = new SearchIndexDefinition(
            name: 'test',
            documentType: 'entity',
            fields: [
                new SearchFieldDefinition(
                    name: 'name',
                    type: SearchFieldType::Contains,
                    searchable: true,
                ),
                new SearchFieldDefinition(
                    name: 'status',
                    type: SearchFieldType::Enum,
                    filterable: true,
                ),
                new SearchFieldDefinition(
                    name: 'role',
                    type: SearchFieldType::Keyword,
                    filterable: true,
                ),
            ],
        );
    }

    public function testPlainTextQuery(): void
    {
        $result = $this->parser->parse($this->definition, 'john doe');

        $this->assertSame('john doe', $result['query']);
        $this->assertSame([], $result['filters']);
    }

    public function testFieldFilter(): void
    {
        $result = $this->parser->parse($this->definition, 'john status:active');

        $this->assertSame('john', $result['query']);
        $this->assertSame(['status' => 'active'], $result['filters']);
    }

    public function testMultiValueFilter(): void
    {
        $result = $this->parser->parse($this->definition, 'role:admin,editor');

        $this->assertNull($result['query']);
        $this->assertSame(['role' => ['admin', 'editor']], $result['filters']);
    }

    public function testUnknownFieldKeptInQuery(): void
    {
        $result = $this->parser->parse($this->definition, 'john unknown:value');

        $this->assertSame('john unknown:value', $result['query']);
        $this->assertSame([], $result['filters']);
    }

    public function testEmptyInput(): void
    {
        $result = $this->parser->parse($this->definition, '');

        $this->assertNull($result['query']);
        $this->assertSame([], $result['filters']);
    }

    public function testOnlyFilters(): void
    {
        $result = $this->parser->parse($this->definition, 'status:active role:admin');

        $this->assertNull($result['query']);
        $this->assertSame(['status' => 'active', 'role' => 'admin'], $result['filters']);
    }

    public function testNonFilterableFieldKeptInQuery(): void
    {
        $result = $this->parser->parse($this->definition, 'name:john');

        $this->assertSame('name:john', $result['query']);
        $this->assertSame([], $result['filters']);
    }
}
