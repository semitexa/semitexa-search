<?php

declare(strict_types=1);

namespace Semitexa\Search\Tests\Backend\Orm;

use PHPUnit\Framework\TestCase;
use Semitexa\Search\Application\Service\Orm\OrmSearchBackend;
use Semitexa\Search\Domain\Contract\OrmSearchQueryInterface;
use Semitexa\Search\Domain\Contract\OrmSearchQueryFactoryInterface;
use Semitexa\Search\Domain\Enum\SearchFieldType;
use Semitexa\Search\Exception\SearchBackendException;
use Semitexa\Search\Domain\Model\SearchFieldDefinition;
use Semitexa\Search\Domain\Model\SearchIndexDefinition;
use Semitexa\Search\Domain\Model\SearchRequest;

final class OrmSearchBackendTest extends TestCase
{
    public function testSupportsReturnsFalseWhenQueryFactoryIsMissing(): void
    {
        $backend = new OrmSearchBackend();

        self::assertFalse($backend->supports($this->definition()));
    }

    public function testSearchThrowsDeterministicExceptionWhenQueryFactoryIsMissing(): void
    {
        $backend = new OrmSearchBackend();

        $this->expectException(SearchBackendException::class);
        $this->expectExceptionMessage("ORM backend is not available for index 'products' because no query factory is configured.");

        $backend->search($this->definition(), new SearchRequest(index: 'products'));
    }

    public function testSupportsReturnsTrueWhenOrmFactoryIsConfigured(): void
    {
        $query = $this->createMock(OrmSearchQueryInterface::class);
        $factory = $this->createMock(OrmSearchQueryFactoryInterface::class);
        $factory->method('createQuery')->willReturn($query);

        $backend = new OrmSearchBackend();
        $property = new \ReflectionProperty($backend, 'queryFactory');
        $property->setValue($backend, $factory);

        self::assertTrue($backend->supports($this->definition()));
    }

    private function definition(): SearchIndexDefinition
    {
        return new SearchIndexDefinition(
            name: 'products',
            documentType: 'product',
            fields: [
                new SearchFieldDefinition(
                    name: 'title',
                    type: SearchFieldType::Contains,
                    searchable: true,
                ),
            ],
        );
    }
}
