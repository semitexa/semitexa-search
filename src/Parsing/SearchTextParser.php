<?php

declare(strict_types=1);

namespace Semitexa\Search\Parsing;

use Semitexa\Search\Index\SearchIndexDefinition;

final class SearchTextParser
{
    /**
     * Parse raw user input into a query string and explicit field filters.
     *
     * Supports syntax like: "john status:active role:admin" which extracts
     * field filters from the query string when those fields exist in the index.
     *
     * @return array{query: ?string, filters: array<string, scalar|list<scalar>>}
     */
    public function parse(SearchIndexDefinition $definition, string $rawInput): array
    {
        $rawInput = trim($rawInput);

        if ($rawInput === '') {
            return ['query' => null, 'filters' => []];
        }

        $filters = [];
        $queryParts = [];

        $tokens = preg_split('/\s+/', $rawInput);

        foreach ($tokens as $token) {
            if (str_contains($token, ':')) {
                [$fieldName, $value] = explode(':', $token, 2);

                $field = $definition->getField($fieldName);

                if ($field !== null && $field->filterable && $value !== '') {
                    if (str_contains($value, ',')) {
                        $filters[$fieldName] = array_map('trim', explode(',', $value));
                    } else {
                        $filters[$fieldName] = $value;
                    }
                    continue;
                }
            }

            $queryParts[] = $token;
        }

        $query = implode(' ', $queryParts);

        return [
            'query' => $query !== '' ? $query : null,
            'filters' => $filters,
        ];
    }
}
