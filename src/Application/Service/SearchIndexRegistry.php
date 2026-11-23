<?php

declare(strict_types=1);

namespace Semitexa\Search\Application\Service;

use Semitexa\Core\Attribute\SatisfiesServiceContract;
use Semitexa\Core\Attribute\WorkerState;
use Semitexa\Search\Domain\Contract\SearchIndexRegistryInterface;
use Semitexa\Search\Domain\Model\SearchIndexDefinition;
use Semitexa\Search\Exception\SearchIndexNotFoundException;

#[SatisfiesServiceContract(of: SearchIndexRegistryInterface::class)]
final class SearchIndexRegistry implements SearchIndexRegistryInterface
{
    /** @var array<string, SearchIndexDefinition> */
    #[WorkerState('Index definitions keyed by index name: schema only (fields, scope mode, backend), no tenant id or row data; an application registers them at boot.')]
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
