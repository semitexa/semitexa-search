<?php

declare(strict_types=1);

namespace Semitexa\Search\Contract;

use Semitexa\Search\Value\SearchHit;

interface DocumentMapperInterface
{
    /**
     * @param array<string, mixed> $row
     */
    public function mapToHit(string $index, array $row, float $score = 0.0): SearchHit;
}
