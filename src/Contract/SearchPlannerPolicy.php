<?php

declare(strict_types=1);

namespace Semitexa\Search\Contract;

final readonly class SearchPlannerPolicy
{
    /**
     * @param list<string> $allowedOperators
     */
    public function __construct(
        public float $minConfidence = 0.5,
        public int $timeoutMs = 3000,
        public array $allowedOperators = ['=', 'in', 'between', 'like'],
    ) {}
}
