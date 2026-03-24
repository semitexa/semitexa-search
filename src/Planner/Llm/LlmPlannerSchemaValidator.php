<?php

declare(strict_types=1);

namespace Semitexa\Search\Planner\Llm;

use Semitexa\Search\Contract\SearchPlannerPolicy;
use Semitexa\Search\Index\SearchIndexDefinition;

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
            foreach ($parsed['filters'] as $fieldName => $value) {
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
                if (!is_array($sortItem) || !isset($sortItem['field'])) {
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
                    $dir = strtoupper($sortItem['direction']);
                    if (!in_array($dir, ['ASC', 'DESC'], true)) {
                        $violations[] = "Invalid sort direction '{$sortItem['direction']}'";
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
