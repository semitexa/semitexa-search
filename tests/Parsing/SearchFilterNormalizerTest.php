<?php

declare(strict_types=1);

namespace Semitexa\Search\Tests\Parsing;

use PHPUnit\Framework\TestCase;
use Semitexa\Search\Domain\Enum\SearchFieldType;
use Semitexa\Search\Exception\SearchValidationException;
use Semitexa\Search\Domain\Model\SearchFieldDefinition;
use Semitexa\Search\Domain\Model\SearchIndexDefinition;
use Semitexa\Search\Application\Service\SearchFilterNormalizer;

final class SearchFilterNormalizerTest extends TestCase
{
    private SearchFilterNormalizer $normalizer;

    protected function setUp(): void
    {
        $this->normalizer = new SearchFilterNormalizer();
    }

    public function testNormalizeScalarFilter(): void
    {
        $definition = $this->createDefinition();
        $result = $this->normalizer->normalize($definition, ['is_active' => true]);

        $this->assertSame(['is_active' => true], $result);
    }

    public function testNormalizeListFilter(): void
    {
        $definition = $this->createDefinition();
        $result = $this->normalizer->normalize($definition, ['status' => ['active', 'pending']]);

        $this->assertSame(['status' => ['active', 'pending']], $result);
    }

    public function testNormalizeRangeFilter(): void
    {
        $definition = $this->createDefinition();
        $result = $this->normalizer->normalize($definition, [
            'created_at' => ['from' => '2025-01-01', 'to' => '2025-12-31'],
        ]);

        $this->assertSame(['created_at' => ['from' => '2025-01-01', 'to' => '2025-12-31']], $result);
    }

    public function testRejectUnknownField(): void
    {
        $definition = $this->createDefinition();

        $this->expectException(SearchValidationException::class);
        $this->normalizer->normalize($definition, ['nonexistent' => 'value']);
    }

    public function testRejectNonFilterableField(): void
    {
        $definition = $this->createDefinition();

        $this->expectException(SearchValidationException::class);
        $this->normalizer->normalize($definition, ['name' => 'john']);
    }

    public function testRejectRangeOnNonRangeableField(): void
    {
        $definition = $this->createDefinition();

        $this->expectException(SearchValidationException::class);
        $this->normalizer->normalize($definition, ['is_active' => ['from' => 0, 'to' => 1]]);
    }

    public function testRejectEmptyList(): void
    {
        $definition = $this->createDefinition();

        $this->expectException(SearchValidationException::class);
        $this->normalizer->normalize($definition, ['status' => []]);
    }

    private function createDefinition(): SearchIndexDefinition
    {
        return new SearchIndexDefinition(
            name: 'test',
            documentType: 'entity',
            fields: [
                new SearchFieldDefinition(
                    name: 'name',
                    type: SearchFieldType::Contains,
                    searchable: true,
                ),
                new SearchFieldDefinition(
                    name: 'is_active',
                    type: SearchFieldType::Exact,
                    filterable: true,
                ),
                new SearchFieldDefinition(
                    name: 'status',
                    type: SearchFieldType::Enum,
                    filterable: true,
                ),
                new SearchFieldDefinition(
                    name: 'created_at',
                    type: SearchFieldType::Date,
                    filterable: true,
                    sortable: true,
                ),
            ],
        );
    }
}
