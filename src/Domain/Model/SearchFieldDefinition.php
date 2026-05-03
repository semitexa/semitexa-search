<?php

declare(strict_types=1);

namespace Semitexa\Search\Domain\Model;

use Semitexa\Search\Domain\Enum\SearchFieldType;
use Semitexa\Search\Domain\Enum\SearchMatchStrategy;

final readonly class SearchFieldDefinition
{
    public function __construct(
        public string $name,
        public SearchFieldType $type,
        public bool $searchable = false,
        public bool $filterable = false,
        public bool $sortable = false,
        public SearchMatchStrategy $matchStrategy = SearchMatchStrategy::Contains,
        public float $weight = 1.0,
        public ?string $column = null,
    ) {
        if ($this->name === '') {
            throw new \InvalidArgumentException('Search field name must not be empty');
        }

        if ($this->weight < 0.0) {
            throw new \InvalidArgumentException(
                "Search field weight must be non-negative, got {$this->weight} for field '{$this->name}'"
            );
        }
    }

    public function resolvedColumn(): string
    {
        return $this->column ?? $this->name;
    }
}
