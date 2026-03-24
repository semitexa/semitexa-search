<?php

declare(strict_types=1);

namespace Semitexa\Search\Value;

final readonly class SearchResult
{
    /**
     * @param list<SearchHit> $hits
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public array $hits,
        public int $total,
        public bool $partial = false,
        public array $metadata = [],
    ) {}

    public function isEmpty(): bool
    {
        return $this->total === 0;
    }

    public function count(): int
    {
        return count($this->hits);
    }
}
