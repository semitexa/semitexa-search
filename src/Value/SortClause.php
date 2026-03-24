<?php

declare(strict_types=1);

namespace Semitexa\Search\Value;

final readonly class SortClause
{
    public function __construct(
        public string $field,
        public string $direction = 'ASC',
    ) {
        $normalized = strtoupper($this->direction);
        if (!in_array($normalized, ['ASC', 'DESC'], true)) {
            throw new \InvalidArgumentException(
                "Sort direction must be ASC or DESC, got '{$this->direction}'"
            );
        }
    }

    public function normalizedDirection(): string
    {
        return strtoupper($this->direction);
    }
}
