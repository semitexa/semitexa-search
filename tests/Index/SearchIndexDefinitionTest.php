<?php

declare(strict_types=1);

namespace Semitexa\Search\Tests\Index;

use PHPUnit\Framework\TestCase;
use Semitexa\Search\Enum\SearchFieldType;
use Semitexa\Search\Enum\SearchMatchStrategy;
use Semitexa\Search\Enum\SearchScope;
use Semitexa\Search\Index\SearchFieldDefinition;
use Semitexa\Search\Index\SearchIndexDefinition;

final class SearchIndexDefinitionTest extends TestCase
{
    public function testValidDefinition(): void
    {
        $definition = new SearchIndexDefinition(
            name: 'platform.users',
            documentType: 'user',
            fields: [
                new SearchFieldDefinition(
                    name: 'name',
                    type: SearchFieldType::Contains,
                    searchable: true,
                    sortable: true,
                    weight: 2.0,
                ),
                new SearchFieldDefinition(
                    name: 'email',
                    type: SearchFieldType::Contains,
                    searchable: true,
                    filterable: true,
                ),
                new SearchFieldDefinition(
                    name: 'is_active',
                    type: SearchFieldType::Exact,
                    filterable: true,
                ),
            ],
        );

        $this->assertSame('platform.users', $definition->name);
        $this->assertSame('user', $definition->documentType);
        $this->assertCount(3, $definition->fields);
        $this->assertTrue($definition->requiresTenantScope());
    }

    public function testSearchableFields(): void
    {
        $definition = $this->createUserDefinition();

        $searchable = $definition->searchableFields();
        $this->assertCount(2, $searchable);
        $this->assertSame('name', $searchable[0]->name);
        $this->assertSame('email', $searchable[1]->name);
    }

    public function testFilterableFields(): void
    {
        $definition = $this->createUserDefinition();

        $filterable = $definition->filterableFields();
        $this->assertCount(2, $filterable);
    }

    public function testSortableFields(): void
    {
        $definition = $this->createUserDefinition();

        $sortable = $definition->sortableFields();
        $this->assertCount(1, $sortable);
        $this->assertSame('name', $sortable[0]->name);
    }

    public function testGetField(): void
    {
        $definition = $this->createUserDefinition();

        $this->assertNotNull($definition->getField('name'));
        $this->assertNull($definition->getField('nonexistent'));
        $this->assertTrue($definition->hasField('email'));
        $this->assertFalse($definition->hasField('phone'));
    }

    public function testEmptyNameThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new SearchIndexDefinition(
            name: '',
            documentType: 'user',
            fields: [new SearchFieldDefinition(name: 'id', type: SearchFieldType::Exact)],
        );
    }

    public function testEmptyDocumentTypeThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new SearchIndexDefinition(
            name: 'test',
            documentType: '',
            fields: [new SearchFieldDefinition(name: 'id', type: SearchFieldType::Exact)],
        );
    }

    public function testEmptyFieldsThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new SearchIndexDefinition(name: 'test', documentType: 'user', fields: []);
    }

    public function testDuplicateFieldNameThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new SearchIndexDefinition(
            name: 'test',
            documentType: 'user',
            fields: [
                new SearchFieldDefinition(name: 'name', type: SearchFieldType::Contains),
                new SearchFieldDefinition(name: 'name', type: SearchFieldType::Exact),
            ],
        );
    }

    public function testGlobalScopeDoesNotRequireTenant(): void
    {
        $definition = new SearchIndexDefinition(
            name: 'global.index',
            documentType: 'item',
            fields: [new SearchFieldDefinition(name: 'id', type: SearchFieldType::Exact)],
            scopeMode: SearchScope::Global,
        );

        $this->assertFalse($definition->requiresTenantScope());
    }

    private function createUserDefinition(): SearchIndexDefinition
    {
        return new SearchIndexDefinition(
            name: 'platform.users',
            documentType: 'user',
            fields: [
                new SearchFieldDefinition(
                    name: 'name',
                    type: SearchFieldType::Contains,
                    searchable: true,
                    sortable: true,
                    weight: 2.0,
                ),
                new SearchFieldDefinition(
                    name: 'email',
                    type: SearchFieldType::Contains,
                    searchable: true,
                    filterable: true,
                ),
                new SearchFieldDefinition(
                    name: 'is_active',
                    type: SearchFieldType::Exact,
                    filterable: true,
                ),
            ],
        );
    }
}
