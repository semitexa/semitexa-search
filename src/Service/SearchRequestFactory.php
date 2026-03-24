<?php

declare(strict_types=1);

namespace Semitexa\Search\Service;

use Semitexa\Search\Configuration\SearchConfig;
use Semitexa\Search\Enum\SearchScope;
use Semitexa\Search\Exception\SearchValidationException;
use Semitexa\Search\Value\SearchRequest;
use Semitexa\Search\Value\SortClause;

final class SearchRequestFactory
{
    private SearchConfig $config;

    public function __construct(?SearchConfig $config = null)
    {
        $this->config = $config ?? SearchConfig::fromEnvironment();
    }

    /**
     * @param array<string, scalar|list<scalar>|array{from?: scalar, to?: scalar}> $filters
     * @param list<SortClause> $sort
     */
    public function create(
        string $index,
        ?string $query = null,
        array $filters = [],
        array $sort = [],
        ?int $limit = null,
        int $offset = 0,
        SearchScope $scope = SearchScope::Tenant,
        ?string $tenantId = null,
    ): SearchRequest {
        $violations = [];

        if ($query !== null && mb_strlen($query) > $this->config->maxQueryLength) {
            $violations[] = "Query exceeds maximum length of {$this->config->maxQueryLength} characters";
        }

        if (count($filters) > $this->config->maxFilterCount) {
            $violations[] = "Filter count exceeds maximum of {$this->config->maxFilterCount}";
        }

        $effectiveLimit = $limit ?? $this->config->defaultLimit;

        if ($effectiveLimit > $this->config->maxResultLimit) {
            $violations[] = "Limit exceeds maximum of {$this->config->maxResultLimit}";
        }

        if (!empty($violations)) {
            throw SearchValidationException::fromViolations($violations);
        }

        return new SearchRequest(
            index: $index,
            query: $query,
            filters: $filters,
            sort: $sort,
            limit: $effectiveLimit,
            offset: $offset,
            scope: $scope,
            tenantId: $tenantId,
        );
    }
}
