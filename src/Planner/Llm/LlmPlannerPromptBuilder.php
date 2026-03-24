<?php

declare(strict_types=1);

namespace Semitexa\Search\Planner\Llm;

use Semitexa\Search\Contract\SearchPlannerPolicy;
use Semitexa\Search\Index\SearchIndexDefinition;

final class LlmPlannerPromptBuilder
{
    public function build(
        SearchIndexDefinition $definition,
        string $userQuery,
        SearchPlannerPolicy $policy,
    ): string {
        $manifest = $this->buildFieldManifest($definition);
        $operators = implode(', ', $policy->allowedOperators);

        return <<<PROMPT
        You are a search query planner. Your task is to convert natural language user input into a structured search query.

        Target index: {$definition->name}
        Document type: {$definition->documentType}

        Available fields:
        {$manifest}

        Allowed operators: {$operators}

        User query: "{$userQuery}"

        Respond with valid JSON only. No prose, no explanation.

        JSON schema:
        {
          "query": "string or null — remaining free-text after extracting filters",
          "filters": {
            "field_name": "value or [values] or {from: value, to: value}"
          },
          "sort": [
            {"field": "field_name", "direction": "ASC or DESC"}
          ],
          "confidence": 0.0 to 1.0,
          "warnings": ["string"]
        }

        Rules:
        - Only reference fields listed above
        - Only use allowed operators
        - Set confidence below 0.5 if uncertain
        - Include warnings for ambiguous interpretations
        - If the query is just free text with no extractable filters, return it as "query" with empty filters
        PROMPT;
    }

    private function buildFieldManifest(SearchIndexDefinition $definition): string
    {
        $lines = [];

        foreach ($definition->fields as $field) {
            $roles = [];
            if ($field->searchable) {
                $roles[] = 'searchable';
            }
            if ($field->filterable) {
                $roles[] = 'filterable';
            }
            if ($field->sortable) {
                $roles[] = 'sortable';
            }

            $rolesStr = implode(', ', $roles);
            $lines[] = "- {$field->name} (type: {$field->type->value}, roles: {$rolesStr})";
        }

        return implode("\n", $lines);
    }
}
