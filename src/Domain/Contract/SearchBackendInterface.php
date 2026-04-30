<?php

declare(strict_types=1);

namespace Semitexa\Search\Domain\Contract;

use Semitexa\Search\Domain\Model\SearchIndexDefinition;
use Semitexa\Search\Domain\Model\SearchRequest;
use Semitexa\Search\Domain\Model\SearchResult;

interface SearchBackendInterface
{
    public function supports(SearchIndexDefinition $definition): bool;

    public function search(SearchIndexDefinition $definition, SearchRequest $request): SearchResult;
}
