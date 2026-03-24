<?php

declare(strict_types=1);

namespace Semitexa\Search\Parsing;

use Semitexa\Search\Enum\SearchFieldType;
use Semitexa\Search\Exception\SearchValidationException;
use Semitexa\Search\Index\SearchFieldDefinition;
use Semitexa\Search\Index\SearchIndexDefinition;

final class SearchFilterNormalizer
{
    /**
     * @param array<string, mixed> $filters
     * @return array<string, scalar|list<scalar>|array{from?: scalar, to?: scalar}>
     */
    public function normalize(SearchIndexDefinition $definition, array $filters): array
    {
        $violations = [];
        $normalized = [];

        foreach ($filters as $fieldName => $value) {
            $field = $definition->getField($fieldName);

            if ($field === null) {
                $violations[] = "Unknown filter field '{$fieldName}' for index '{$definition->name}'";
                continue;
            }

            if (!$field->filterable) {
                $violations[] = "Field '{$fieldName}' is not filterable on index '{$definition->name}'";
                continue;
            }

            $normalizedValue = $this->normalizeValue($field, $value, $violations);
            if ($normalizedValue !== null) {
                $normalized[$fieldName] = $normalizedValue;
            }
        }

        if (!empty($violations)) {
            throw SearchValidationException::fromViolations($violations);
        }

        return $normalized;
    }

    private function normalizeValue(
        SearchFieldDefinition $field,
        mixed $value,
        array &$violations,
    ): mixed {
        if (is_array($value) && $this->isRangeFilter($value)) {
            return $this->normalizeRange($field, $value, $violations);
        }

        if (is_array($value)) {
            return $this->normalizeList($field, $value, $violations);
        }

        if (is_scalar($value)) {
            return $this->normalizeScalar($field, $value, $violations);
        }

        $violations[] = "Filter value for '{$field->name}' must be scalar, list, or range";
        return null;
    }

    /**
     * @param array<mixed> $value
     */
    private function isRangeFilter(array $value): bool
    {
        return array_key_exists('from', $value) || array_key_exists('to', $value);
    }

    /**
     * @param array{from?: mixed, to?: mixed} $value
     * @return array{from?: scalar, to?: scalar}|null
     */
    private function normalizeRange(
        SearchFieldDefinition $field,
        array $value,
        array &$violations,
    ): ?array {
        $allowed = [SearchFieldType::Date, SearchFieldType::Number];

        if (!in_array($field->type, $allowed, true)) {
            $violations[] = "Range filter is not supported for field '{$field->name}' of type '{$field->type->value}'";
            return null;
        }

        $result = [];

        if (array_key_exists('from', $value)) {
            if (!is_scalar($value['from'])) {
                $violations[] = "Range 'from' value for '{$field->name}' must be scalar";
                return null;
            }
            $result['from'] = $value['from'];
        }

        if (array_key_exists('to', $value)) {
            if (!is_scalar($value['to'])) {
                $violations[] = "Range 'to' value for '{$field->name}' must be scalar";
                return null;
            }
            $result['to'] = $value['to'];
        }

        if (empty($result)) {
            $violations[] = "Range filter for '{$field->name}' must have at least 'from' or 'to'";
            return null;
        }

        return $result;
    }

    /**
     * @param list<mixed> $value
     * @return list<scalar>|null
     */
    private function normalizeList(
        SearchFieldDefinition $field,
        array $value,
        array &$violations,
    ): ?array {
        $normalized = [];
        foreach ($value as $item) {
            if (!is_scalar($item)) {
                $violations[] = "List filter value for '{$field->name}' contains a non-scalar element";
                return null;
            }
            $normalized[] = $item;
        }

        if (empty($normalized)) {
            $violations[] = "List filter for '{$field->name}' must not be empty";
            return null;
        }

        return $normalized;
    }

    private function normalizeScalar(
        SearchFieldDefinition $field,
        mixed $value,
        array &$violations,
    ): mixed {
        return match ($field->type) {
            SearchFieldType::Number => is_numeric($value)
                ? $value
                : (function () use ($field, $violations, &$value) {
                    $violations[] = "Filter value for '{$field->name}' must be numeric";
                    return null;
                })(),
            default => $value,
        };
    }
}
