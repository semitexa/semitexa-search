<?php

declare(strict_types=1);

namespace Semitexa\Search\Application\Prompt;

use Semitexa\Prompt\Attribute\AsPrompt;

/**
 * Thin prompt definition — the body lives in resources/prompts/search.query-planner.twig.
 */
#[AsPrompt(
    id: self::ID,
    channel: 'search',
    description: 'LLM search-query planner: natural language to a structured JSON query.',
)]
final class SearchPlannerPrompt
{
    public const ID = 'search.query-planner';
}
