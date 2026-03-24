<?php

declare(strict_types=1);

namespace Semitexa\Search\Contract;

use Semitexa\Search\Index\SearchIndexDefinition;

interface SearchIndexRegistryInterface
{
    public function get(string $indexName): SearchIndexDefinition;

    public function has(string $indexName): bool;

    /**
     * @return list<SearchIndexDefinition>
     */
    public function all(): array;

    public function register(SearchIndexDefinition $definition): void;
}
