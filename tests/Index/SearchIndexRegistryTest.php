<?php

declare(strict_types=1);

namespace Semitexa\Search\Tests\Index;

use PHPUnit\Framework\TestCase;
use Semitexa\Search\Enum\SearchFieldType;
use Semitexa\Search\Exception\SearchIndexNotFoundException;
use Semitexa\Search\Index\SearchFieldDefinition;
use Semitexa\Search\Index\SearchIndexDefinition;
use Semitexa\Search\Index\SearchIndexRegistry;

final class SearchIndexRegistryTest extends TestCase
{
    public function testRegisterAndGet(): void
    {
        $registry = new SearchIndexRegistry();
        $definition = $this->createDefinition('platform.users');

        $registry->register($definition);

        $this->assertTrue($registry->has('platform.users'));
        $this->assertSame($definition, $registry->get('platform.users'));
    }

    public function testGetUnknownIndexThrows(): void
    {
        $registry = new SearchIndexRegistry();

        $this->expectException(SearchIndexNotFoundException::class);
        $registry->get('nonexistent');
    }

    public function testDuplicateRegistrationThrows(): void
    {
        $registry = new SearchIndexRegistry();
        $definition = $this->createDefinition('test');

        $registry->register($definition);

        $this->expectException(\InvalidArgumentException::class);
        $registry->register($definition);
    }

    public function testAll(): void
    {
        $registry = new SearchIndexRegistry();
        $registry->register($this->createDefinition('index.a'));
        $registry->register($this->createDefinition('index.b'));

        $all = $registry->all();
        $this->assertCount(2, $all);
    }

    private function createDefinition(string $name): SearchIndexDefinition
    {
        return new SearchIndexDefinition(
            name: $name,
            documentType: 'user',
            fields: [
                new SearchFieldDefinition(name: 'id', type: SearchFieldType::Exact),
            ],
        );
    }
}
