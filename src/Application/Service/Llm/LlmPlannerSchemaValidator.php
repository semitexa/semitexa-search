<?php

declare(strict_types=1);

namespace Semitexa\Search\Application\Service\Llm;

use Semitexa\Search\Domain\Model\SearchPlannerPolicy;
use Semitexa\Search\Domain\Model\SearchIndexDefinition;

final class LlmPlannerSchemaValidator
{
    /**
     * Validate parsed planner output against the index definition and policy.
     *
     * @param array<string, mixed> $parsed
     * @return list<string>
     */
    public function validate(
        array $parsed,
        SearchIndexDefinition $definition,
        SearchPlannerPolicy $policy,
    ): array {
        $violations = [];

        if (isset($parsed['query']) && !is_string($parsed['query']) && $parsed['query'] !== null) {
            $violations[] = 'query must be a string or null';
        }

        if (isset($parsed['confidence'])) {
            if (!is_numeric($parsed['confidence'])) {
                $violations[] = 'confidence must be numeric';
            } elseif ($parsed['confidence'] < 0.0 || $parsed['confidence'] > 1.0) {
                $violations[] = 'confidence must be between 0.0 and 1.0';
            }
        }

        if (isset($parsed['filters']) && is_array($parsed['filters'])) {
            // A model's JSON is checked, never trusted to have the right
            // types: a list for filters, a number for a field or direction
            // threw a TypeError instead of coming back as violations.
            foreach ($parsed['filters'] as $fieldName => $value) {
                if (!is_string($fieldName)) {
                    $violations[] = 'filters must be an object keyed by field name';
                    continue;
                }
                if (!$definition->hasField($fieldName)) {
                    $violations[] = "Planner referenced unknown field '{$fieldName}'";
                    continue;
                }

                $field = $definition->getField($fieldName);
                if ($field !== null && !$field->filterable) {
                    $violations[] = "Planner referenced non-filterable field '{$fieldName}'";
                }
            }
        }

        if (isset($parsed['sort']) && is_array($parsed['sort'])) {
            foreach ($parsed['sort'] as $sortItem) {
                if (!is_array($sortItem) || !isset($sortItem['field']) || !is_string($sortItem['field'])) {
                    $violations[] = 'Each sort entry must have a field key';
                    continue;
                }

                $field = $definition->getField($sortItem['field']);
                if ($field === null) {
                    $violations[] = "Planner referenced unknown sort field '{$sortItem['field']}'";
                } elseif (!$field->sortable) {
                    $violations[] = "Planner referenced non-sortable field '{$sortItem['field']}'";
                }

                if (isset($sortItem['direction'])) {
                    if (!is_string($sortItem['direction']) || !in_array(strtoupper($sortItem['direction']), ['ASC', 'DESC'], true)) {
                        $violations[] = 'Invalid sort direction: use ASC or DESC';
                    }
                }
            }
        }

        if (isset($parsed['warnings']) && !is_array($parsed['warnings'])) {
            $violations[] = 'warnings must be an array';
        }

        return $violations;
    }
}
