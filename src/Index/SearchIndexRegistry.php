<?php

declare(strict_types=1);

namespace Semitexa\Search\Index;

use Semitexa\Core\Attribute\SatisfiesServiceContract;
use Semitexa\Search\Contract\SearchIndexRegistryInterface;
use Semitexa\Search\Exception\SearchIndexNotFoundException;

#[SatisfiesServiceContract(of: SearchIndexRegistryInterface::class)]
final class SearchIndexRegistry implements SearchIndexRegistryInterface
{
    /** @var array<string, SearchIndexDefinition> */
    private array $indexes = [];

    public function get(string $indexName): SearchIndexDefinition
    {
        if (!isset($this->indexes[$indexName])) {
            throw new SearchIndexNotFoundException($indexName);
        }

        return $this->indexes[$indexName];
    }

    public function has(string $indexName): bool
    {
        return isset($this->indexes[$indexName]);
    }

    /**
     * @return list<SearchIndexDefinition>
     */
    public function all(): array
    {
        return array_values($this->indexes);
    }

    public function register(SearchIndexDefinition $definition): void
    {
        if (isset($this->indexes[$definition->name])) {
            throw new \InvalidArgumentException(
                "Search index '{$definition->name}' is already registered"
            );
        }

        $this->indexes[$definition->name] = $definition;
    }
}
