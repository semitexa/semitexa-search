<?php

declare(strict_types=1);

namespace Semitexa\Search\Value;

final readonly class SearchPlanningResult
{
    /**
     * @param array<string, scalar|list<scalar>|array{from?: scalar, to?: scalar}> $filters
     * @param list<SortClause> $sort
     * @param list<string> $warnings
     */
    public function __construct(
        public ?string $query,
        public array $filters = [],
        public array $sort = [],
        public float $confidence = 0.0,
        public array $warnings = [],
        public string $plannerName = '',
        public ?SearchPlannerTrace $trace = null,
    ) {}

    public function isUsable(float $minConfidence): bool
    {
        return $this->confidence >= $minConfidence && empty($this->warnings);
    }
}
