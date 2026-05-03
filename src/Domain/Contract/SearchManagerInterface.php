<?php

declare(strict_types=1);

namespace Semitexa\Search\Domain\Contract;

use Semitexa\Search\Domain\Model\SearchRequest;
use Semitexa\Search\Domain\Model\SearchResult;

interface SearchManagerInterface
{
    public function search(SearchRequest $request): SearchResult;

    /**
     * @param array<string, scalar|list<scalar>|array{from?: scalar, to?: scalar}> $filters
     */
    public function searchText(
        string $index,
        string $rawQuery,
        int $limit = 20,
        array $filters = [],
    ): SearchResult;
}
