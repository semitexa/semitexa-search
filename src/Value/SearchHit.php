<?php

declare(strict_types=1);

namespace Semitexa\Search\Value;

final readonly class SearchHit
{
    /**
     * @param array<string, scalar|array|null> $fields
     */
    public function __construct(
        public string $documentId,
        public string $index,
        public string $type,
        public float $score,
        public array $fields,
        public ?string $snippet = null,
    ) {}
}
