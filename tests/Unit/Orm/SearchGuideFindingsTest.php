<?php

declare(strict_types=1);

namespace Semitexa\Search\Tests\Unit\Orm;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Semitexa\Search\Application\Service\Orm\OrmSearchTranslator;
use Semitexa\Search\Application\Service\SearchFilterNormalizer;
use Semitexa\Search\Application\Service\SearchManager;
use Semitexa\Search\Domain\Contract\OrmSearchQueryInterface;
use Semitexa\Search\Domain\Enum\SearchFieldType;
use Semitexa\Search\Domain\Enum\SearchMatchStrategy;
use Semitexa\Search\Domain\Enum\SearchScope;
use Semitexa\Search\Domain\Model\SearchFieldDefinition;
use Semitexa\Search\Domain\Model\SearchIndexDefinition;
use Semitexa\Search\Domain\Model\SearchRequest;
use Semitexa\Search\Exception\SearchValidationException;

/** Found while writing the search guide (2026-10-21). */
final class SearchGuideFindingsTest extends TestCase
{
    #[Test]
    public function the_text_match_is_one_group_beside_the_filters(): void
    {
        // As one orWhere per field, `lamp status:active` read
        // (tenant AND status) OR title LIKE: inactive lamps came back.
        $query = new RecordingSearchQuery();

        (new OrmSearchTranslator())->apply($query, $this->definition(), new SearchRequest(
            index: 'products', query: 'lamp', filters: ['status' => 'active'], tenantId: 'acme',
        ));

        self::assertSame([], $query->orWheres, 'the text match was OR-ed beside the filters');
        self::assertSame([['title' => '%lamp%', 'sku' => 'lamp%']], $query->anyLike);
        self::assertContains(['tenant_id', '=', 'acme'], $query->wheres);
    }

    #[Test]
    public function a_global_request_on_a_tenant_index_is_held_to_the_tenant(): void
    {
        // The scope was reset to Tenant and the tenant id left empty: the
        // translator then added no tenant condition and searched everyone.
        \Semitexa\Tenancy\Context\TenantContextStore::shared()->set(\Semitexa\Tenancy\Context\TenantContext::fromResolution('acme', 'test'));
        try {
            $scoped = (new \ReflectionMethod(SearchManager::class, 'enforceTenantScope'))
                ->invoke((new \ReflectionClass(SearchManager::class))->newInstanceWithoutConstructor(), new SearchRequest(index: 'products', scope: SearchScope::Global), true);
        } finally {
            \Semitexa\Tenancy\Context\TenantContextStore::shared()->clear();
        }

        self::assertSame(SearchScope::Tenant, $scoped->scope);
        self::assertSame('acme', $scoped->tenantId, 'a Global request on a tenant index searched every tenant');
    }

    #[Test]
    public function a_non_numeric_value_for_a_number_filter_is_refused(): void
    {
        $this->expectException(SearchValidationException::class);
        (new SearchFilterNormalizer())->normalize($this->definition(), ['price' => 'abc']);
    }

    #[Test]
    public function a_tenant_index_reached_without_a_tenant_fails_closed(): void
    {
        $this->expectException(\LogicException::class);
        (new OrmSearchTranslator())->apply(new RecordingSearchQuery(), $this->definition(), new SearchRequest(index: 'products', query: 'lamp', scope: SearchScope::Global));
    }

    #[Test]
    public function every_item_of_a_number_list_is_checked(): void
    {
        $this->expectException(SearchValidationException::class);
        (new SearchFilterNormalizer())->normalize($this->definition(), ['price' => ['1', 'abc']]);
    }

    #[Test]
    public function an_explicit_sort_is_not_re_sorted_by_relevance(): void
    {
        // Sorted by price, a query re-sorted the page by relevance anyway.
        $query = new RecordingSearchQuery();
        $query->rows = [
            (object) ['id' => 'cheap', 'title' => 'desk', 'sku' => 'd1', 'price' => 5],
            (object) ['id' => 'dear', 'title' => 'lamp', 'sku' => 'lamp-1', 'price' => 90],
        ];
        $backend = new \Semitexa\Search\Application\Service\Orm\OrmSearchBackend();
        (new \ReflectionProperty($backend, 'queryFactory'))->setValue($backend, new class ($query) implements \Semitexa\Search\Domain\Contract\OrmSearchQueryFactoryInterface {
            public function __construct(private RecordingSearchQuery $query) {}
            public function createQuery(SearchIndexDefinition $definition): OrmSearchQueryInterface { return $this->query; }
        });
        $definition = new SearchIndexDefinition(
            name: 'products', documentType: 'product',
            fields: [
                new SearchFieldDefinition(name: 'title', type: SearchFieldType::Contains, searchable: true),
                new SearchFieldDefinition(name: 'sku', type: SearchFieldType::Contains, searchable: true),
                new SearchFieldDefinition(name: 'price', type: SearchFieldType::Number, sortable: true),
            ],
        );

        $sorted = $backend->search($definition, new SearchRequest(index: 'products', query: 'lamp', sort: [new \Semitexa\Search\Domain\Model\SortClause('price', 'ASC')], tenantId: 'acme'));
        $ranked = $backend->search($definition, new SearchRequest(index: 'products', query: 'lamp', tenantId: 'acme'));

        self::assertSame(['cheap', 'dear'], array_map(static fn ($h) => $h->documentId, $sorted->hits));
        self::assertSame('dear', $ranked->hits[0]->documentId, 'without a sort, relevance still orders the page');
    }

    private function definition(): SearchIndexDefinition
    {
        return new SearchIndexDefinition(
            name: 'products',
            documentType: 'product',
            fields: [
                new SearchFieldDefinition(name: 'title', type: SearchFieldType::Contains, searchable: true),
                new SearchFieldDefinition(name: 'sku', type: SearchFieldType::Contains, searchable: true, matchStrategy: SearchMatchStrategy::Prefix),
                new SearchFieldDefinition(name: 'status', type: SearchFieldType::Enum, filterable: true),
                new SearchFieldDefinition(name: 'price', type: SearchFieldType::Number, filterable: true),
            ],
        );
    }
}

final class RecordingSearchQuery implements OrmSearchQueryInterface
{
    /** @var list<array{string, string, mixed}> */
    public array $wheres = [];
    /** @var list<array{string, string, mixed}> */
    public array $orWheres = [];
    /** @var list<array<string, string>> */
    public array $anyLike = [];
    /** @var list<object> */
    public array $rows = [];

    public function where(string $column, string $operator, mixed $value): static { $this->wheres[] = [$column, $operator, $value]; return $this; }
    public function orWhere(string $column, string $operator, mixed $value): static { $this->orWheres[] = [$column, $operator, $value]; return $this; }
    public function whereAnyLike(array $patternByColumn): static { $this->anyLike[] = $patternByColumn; return $this; }
    public function whereIn(string $column, array $values): static { return $this; }
    public function whereBetween(string $column, mixed $from, mixed $to): static { return $this; }
    public function orderBy(string $column, string $direction = 'ASC'): static { return $this; }
    public function limit(int $limit): static { return $this; }
    public function offset(int $offset): static { return $this; }
    public function fetchAll(): array { return $this->rows; }
    public function count(): int { return count($this->rows); }
}
