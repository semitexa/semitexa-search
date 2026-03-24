<?php

declare(strict_types=1);

namespace Semitexa\Search\Contract;

use Semitexa\Search\Index\SearchIndexDefinition;
use Semitexa\Search\Value\SearchRequest;
use Semitexa\Search\Value\SearchResult;

interface SearchBackendInterface
{
    public function supports(SearchIndexDefinition $definition): bool;

    public function search(SearchIndexDefinition $definition, SearchRequest $request): SearchResult;
}
