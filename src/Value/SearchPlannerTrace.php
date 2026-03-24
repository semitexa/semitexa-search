<?php

declare(strict_types=1);

namespace Semitexa\Search\Value;

final readonly class SearchPlannerTrace
{
    /**
     * @param array<string, mixed> $debugMetadata
     * @param list<string> $warnings
     */
    public function __construct(
        public string $plannerName,
        public float $confidence,
        public bool $wasUsed,
        public ?string $fallbackReason = null,
        public array $warnings = [],
        public float $latencyMs = 0.0,
        public array $debugMetadata = [],
    ) {}
}
