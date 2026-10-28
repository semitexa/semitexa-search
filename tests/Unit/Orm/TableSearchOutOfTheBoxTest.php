<?php

declare(strict_types=1);

namespace Semitexa\Search\Tests\Unit\Orm;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Semitexa\Orm\Attribute\FromTable;
use Semitexa\Orm\Domain\Model\ConnectionConfig;
use Semitexa\Orm\OrmManager;
use Semitexa\Search\Application\Service\Orm\OrmSearchBackend;
use Semitexa\Search\Application\Service\Orm\OrmTableSearchQueryFactory;
use Semitexa\Search\Domain\Enum\SearchFieldType;
use Semitexa\Search\Domain\Model\SearchFieldDefinition;
use Semitexa\Search\Domain\Model\SearchIndexDefinition;
use Semitexa\Search\Domain\Model\SearchRequest;
use Semitexa\Search\Exception\SearchBackendException;

/**
 * Nothing implemented OrmSearchQueryFactoryInterface and the backend's
 * property had no injection attribute: every search failed out of the box.
 * The shipped factory searches the entity's #[FromTable] table.
 */
final class TableSearchOutOfTheBoxTest extends TestCase
{
    private OrmManager $orm;

    protected function setUp(): void
    {
        $this->orm = new OrmManager(config: new ConnectionConfig(driver: 'sqlite', sqliteMemory: true));
        $db = $this->orm->getQueryAdapter();
        $db->execute('CREATE TABLE search_fixture_products (id TEXT PRIMARY KEY, tenant_id TEXT, title TEXT, status TEXT, price INTEGER)');
        foreach ([
            ['p1', 'acme', 'Desk lamp', 'active', 40],
            ['p2', 'acme', 'Floor lamp', 'archived', 90],
            ['p3', 'acme', 'Office chair', 'active', 120],
            ['p4', 'other', 'Desk lamp', 'active', 35],
        ] as [$id, $tenant, $title, $status, $price]) {
            $db->execute('INSERT INTO search_fixture_products VALUES (:id, :t, :title, :s, :p)', ['id' => $id, 't' => $tenant, 'title' => $title, 's' => $status, 'p' => $price]);
        }
    }

    #[Test]
    public function a_search_runs_against_the_entitys_table_with_tenant_filters_and_paging(): void
    {
        $backend = $this->backend();

        $result = $backend->search($this->definition(SearchFixtureProduct::class), new SearchRequest(index: 'products', query: 'lamp', filters: ['status' => 'active'], tenantId: 'acme'));

        self::assertSame(['p1'], array_map(static fn ($h) => $h->documentId, $result->hits), 'acme only, active only, matching "lamp"');
        self::assertSame(1, $result->total);
        self::assertSame('Desk lamp', $result->hits[0]->fields['title']);

        $all = $backend->search($this->definition(SearchFixtureProduct::class), new SearchRequest(index: 'products', query: 'lamp', tenantId: 'acme', limit: 1));
        self::assertCount(1, $all->hits);
        self::assertSame(2, $all->total, 'the count ignores the page');
        self::assertTrue($all->partial);
    }

    #[Test]
    public function an_index_without_a_table_says_what_is_missing(): void
    {
        $this->expectException(SearchBackendException::class);
        $this->expectExceptionMessage('#[FromTable]');

        $this->backend()->search($this->definition(null), new SearchRequest(index: 'products', query: 'lamp', tenantId: 'acme'));
    }

    #[Test]
    public function percent_and_underscore_in_a_query_match_literally_on_sqlite(): void
    {
        // The translator escapes with a backslash; SQLite has no default
        // escape character, so "50%" matched nothing.
        $this->orm->getQueryAdapter()->execute("INSERT INTO search_fixture_products VALUES ('p5', 'acme', 'Lamp 50% off', 'active', 10), ('p6', 'acme', 'Lamp 50 cents', 'active', 10)");

        $result = $this->backend()->search($this->definition(SearchFixtureProduct::class), new SearchRequest(index: 'products', query: '50%', tenantId: 'acme'));

        self::assertSame(['p5'], array_map(static fn ($h) => $h->documentId, $result->hits));
    }

    #[Test]
    public function soft_deleted_rows_are_not_found_and_binary_ids_come_back_as_uuids(): void
    {
        $db = $this->orm->getQueryAdapter();
        $db->execute('CREATE TABLE search_fixture_notes (id BLOB PRIMARY KEY, tenant_ref TEXT, title TEXT, removed_at TEXT)');
        $kept = \Semitexa\Orm\Application\Service\Uuid7::generate();
        $db->execute("INSERT INTO search_fixture_notes VALUES (:a, 'acme', 'lamp note', NULL), (:b, 'acme', 'lamp gone', '2026-10-01')", [
            'a' => \Semitexa\Orm\Application\Service\Uuid7::toBytes($kept),
            'b' => \Semitexa\Orm\Application\Service\Uuid7::toBytes(\Semitexa\Orm\Application\Service\Uuid7::generate()),
        ]);

        $result = $this->backend()->search($this->definition(SearchFixtureNote::class), new SearchRequest(index: 'products', query: 'lamp', tenantId: 'acme'));

        self::assertSame([$kept], array_map(static fn ($h) => $h->documentId, $result->hits), 'the deleted note is not found; the id is a UUID, the tenant column the model\'s own');
    }

    /** A Tenant index (the default) over a table without the column failed with the database's own error. */
    #[Test]
    public function a_tenant_index_over_a_table_with_no_tenant_column_says_what_to_do(): void
    {
        $this->orm->getQueryAdapter()->execute('CREATE TABLE search_fixture_plain (id TEXT PRIMARY KEY, title TEXT)');

        $this->expectException(SearchBackendException::class);
        $this->expectExceptionMessage('its table has no tenant_id column: make the model #[TenantScoped]');
        $this->backend()->search($this->definition(SearchFixturePlain::class), new SearchRequest(index: 'products', query: 'lamp', tenantId: 'acme'));
    }

    #[Test]
    public function a_global_index_over_tenant_scoped_data_is_refused(): void
    {
        // A repository scopes a #[TenantScoped] model; raw SQL did not, and a
        // Global index read every tenant's rows.
        $this->expectException(SearchBackendException::class);
        $this->expectExceptionMessage('#[TenantScoped]');

        $this->backend()->search(new SearchIndexDefinition(
            name: 'notes', documentType: 'note',
            fields: [new SearchFieldDefinition(name: 'title', type: SearchFieldType::Contains, searchable: true)],
            scopeMode: \Semitexa\Search\Domain\Enum\SearchScope::Global,
            entityClass: SearchFixtureNote::class,
        ), new SearchRequest(index: 'notes', query: 'lamp', scope: \Semitexa\Search\Domain\Enum\SearchScope::Global));
    }

    #[Test]
    public function or_where_is_refused_rather_than_bypassing_the_tenant(): void
    {
        // `tenant AND status OR title` let other tenants' rows through.
        $this->expectException(\LogicException::class);

        (new \Semitexa\Search\Application\Service\Orm\TableSearchQuery($this->orm->getQueryAdapter(), 'search_fixture_products'))
            ->where('tenant_id', '=', 'acme')
            ->orWhere('title', '=', 'Desk lamp');
    }

    #[Test]
    public function a_backslash_in_a_query_matches_literally(): void
    {
        $this->orm->getQueryAdapter()->execute("INSERT INTO search_fixture_products VALUES ('p7', 'acme', 'C:\\lamps', 'active', 1)");

        $result = $this->backend()->search($this->definition(SearchFixtureProduct::class), new SearchRequest(index: 'products', query: 'C:\\', tenantId: 'acme'));

        self::assertSame(['p7'], array_map(static fn ($h) => $h->documentId, $result->hits));
    }

    private function backend(): OrmSearchBackend
    {
        $factory = new OrmTableSearchQueryFactory();
        (new \ReflectionProperty($factory, 'orm'))->setValue($factory, $this->orm);
        $backend = new OrmSearchBackend();
        (new \ReflectionProperty($backend, 'queryFactory'))->setValue($backend, $factory);

        return $backend;
    }

    /** @param class-string|null $entity */
    private function definition(?string $entity): SearchIndexDefinition
    {
        return new SearchIndexDefinition(
            name: 'products',
            documentType: 'product',
            fields: [
                new SearchFieldDefinition(name: 'title', type: SearchFieldType::Contains, searchable: true),
                new SearchFieldDefinition(name: 'status', type: SearchFieldType::Enum, filterable: true),
                new SearchFieldDefinition(name: 'price', type: SearchFieldType::Number, filterable: true, sortable: true),
            ],
            entityClass: $entity,
        );
    }
}

#[FromTable(name: 'search_fixture_products')]
final class SearchFixtureProduct
{
}

#[FromTable(name: 'search_fixture_notes')]
#[\Semitexa\Orm\Attribute\TenantScoped(strategy: 'same_storage', column: 'tenantRef')]
#[\Semitexa\Orm\Attribute\SoftDelete(column: 'removedAt')]
final class SearchFixtureNote
{
    public function __construct(
        #[\Semitexa\Orm\Attribute\Column(name: 'tenant_ref', type: \Semitexa\Orm\Adapter\MySqlType::Varchar, length: 64)]
        public string $tenantRef = '',
        #[\Semitexa\Orm\Attribute\Column(name: 'removed_at', type: \Semitexa\Orm\Adapter\MySqlType::Datetime, nullable: true)]
        public ?string $removedAt = null,
    ) {}
}

#[FromTable(name: 'search_fixture_plain')]
final class SearchFixturePlain
{
}
