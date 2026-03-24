<?php

declare(strict_types=1);

namespace Semitexa\Search\Backend\Orm;

use Semitexa\Search\Enum\SearchMatchStrategy;
use Semitexa\Search\Index\SearchFieldDefinition;
use Semitexa\Search\Index\SearchIndexDefinition;
use Semitexa\Search\Value\SearchHit;

final class OrmRankingStrategy
{
    /**
     * Score a row against a query string based on field definitions.
     *
     * @param array<string, mixed> $row
     */
    public function score(
        SearchIndexDefinition $definition,
        array $row,
        ?string $query,
    ): float {
        if ($query === null) {
            return 0.0;
        }

        $queryLower = mb_strtolower($query);
        $totalScore = 0.0;

        foreach ($definition->searchableFields() as $field) {
            $column = $field->resolvedColumn();
            $value = $row[$column] ?? null;

            if ($value === null || !is_scalar($value)) {
                continue;
            }

            $valueLower = mb_strtolower((string) $value);

            $fieldScore = $this->scoreField($field, $valueLower, $queryLower);
            $totalScore += $fieldScore * $field->weight;
        }

        return $totalScore;
    }

    private function scoreField(
        SearchFieldDefinition $field,
        string $valueLower,
        string $queryLower,
    ): float {
        if ($valueLower === $queryLower) {
            return 3.0;
        }

        if (str_starts_with($valueLower, $queryLower)) {
            return 2.0;
        }

        if (str_contains($valueLower, $queryLower)) {
            return 1.0;
        }

        return 0.0;
    }

    /**
     * Sort hits by score descending, then by position for stability.
     *
     * @param list<SearchHit> $hits
     * @return list<SearchHit>
     */
    public function sortByRelevance(array $hits): array
    {
        usort($hits, function (SearchHit $a, SearchHit $b): int {
            $scoreDiff = $b->score <=> $a->score;
            if ($scoreDiff !== 0) {
                return $scoreDiff;
            }
            return $a->documentId <=> $b->documentId;
        });

        return $hits;
    }
}
