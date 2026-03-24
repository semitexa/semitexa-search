<?php

declare(strict_types=1);

namespace Semitexa\Search\Value;

use Semitexa\Search\Enum\SearchScope;

final readonly class SearchRequest
{
    /**
     * @param array<string, scalar|list<scalar>|array{from?: scalar, to?: scalar}> $filters
     * @param list<SortClause> $sort
     */
    public function __construct(
        public string $index,
        public ?string $query = null,
        public array $filters = [],
        public array $sort = [],
        public int $limit = 20,
        public int $offset = 0,
        public SearchScope $scope = SearchScope::Tenant,
        public ?string $tenantId = null,
        public ?SearchPlannerTrace $plannerTrace = null,
    ) {
        if ($this->limit < 1) {
            throw new \InvalidArgumentException("Search limit must be at least 1, got {$this->limit}");
        }

        if ($this->offset < 0) {
            throw new \InvalidArgumentException("Search offset must be non-negative, got {$this->offset}");
        }

        if ($this->query !== null && mb_strlen($this->query) === 0) {
            throw new \InvalidArgumentException('Search query must be null or non-empty');
        }

        if ($this->index === '') {
            throw new \InvalidArgumentException('Search index name must not be empty');
        }
    }

    /**
     * @param array<string, scalar|list<scalar>|array{from?: scalar, to?: scalar}> $filters
     * @param list<SortClause> $sort
     */
    public function with(
        ?string $query = null,
        ?array $filters = null,
        ?array $sort = null,
        ?int $limit = null,
        ?int $offset = null,
        ?SearchScope $scope = null,
        ?string $tenantId = null,
        ?SearchPlannerTrace $plannerTrace = null,
    ): self {
        return new self(
            index: $this->index,
            query: $query ?? $this->query,
            filters: $filters ?? $this->filters,
            sort: $sort ?? $this->sort,
            limit: $limit ?? $this->limit,
            offset: $offset ?? $this->offset,
            scope: $scope ?? $this->scope,
            tenantId: $tenantId ?? $this->tenantId,
            plannerTrace: $plannerTrace ?? $this->plannerTrace,
        );
    }
}
