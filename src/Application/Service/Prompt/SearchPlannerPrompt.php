<?php

declare(strict_types=1);

namespace Semitexa\Search\Application\Service\Prompt;

use Semitexa\Prompt\Attribute\AsPrompt;
use Semitexa\Prompt\Domain\Contract\PromptDefinitionInterface;

/**
 * The LLM search-query-planner system prompt. Migrated out of the inline heredoc
 * in {@see \Semitexa\Search\Application\Service\Llm\LlmPlannerPromptBuilder}.
 *
 * Bound variables:
 *   - {{ index_name }}    target index name
 *   - {{ document_type }} document type
 *   - {{ fields }}        the assembled field manifest (one line per field)
 *   - {{ operators }}     comma-joined allowed operators
 *   - {{ user_query }}    the raw natural-language query
 *
 * The literal JSON schema below uses single braces and is passed through
 * untouched (the renderer only substitutes {{ ... }} tokens).
 */
#[AsPrompt(
    id: self::ID,
    channel: 'search',
    description: 'LLM search-query planner: natural language to a structured JSON query.',
)]
final class SearchPlannerPrompt implements PromptDefinitionInterface
{
    public const ID = 'search.query-planner';

    public function system(): string
    {
        return <<<'PROMPT'
        You are a search query planner. Your task is to convert natural language user input into a structured search query.

        Target index: {{ index_name }}
        Document type: {{ document_type }}

        Available fields:
        {{ fields }}

        Allowed operators: {{ operators }}

        User query: "{{ user_query }}"

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
}
