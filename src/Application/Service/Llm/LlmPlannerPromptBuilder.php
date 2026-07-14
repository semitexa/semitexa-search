<?php

declare(strict_types=1);

namespace Semitexa\Search\Application\Service\Llm;

use Semitexa\Prompt\Application\Service\PromptRenderer;
use Semitexa\Search\Application\Prompt\SearchPlannerPrompt;
use Semitexa\Search\Domain\Model\SearchFieldDefinition;
use Semitexa\Search\Domain\Model\SearchPlannerPolicy;
use Semitexa\Search\Domain\Model\SearchIndexDefinition;

final class LlmPlannerPromptBuilder
{
    private ?PromptRenderer $renderer = null;

    public function build(
        SearchIndexDefinition $definition,
        string $userQuery,
        SearchPlannerPolicy $policy,
    ): string {
        $this->renderer ??= new PromptRenderer();

        // Raw field data — the template's {% for %} loop formats the manifest.
        $fields = array_map(static fn(SearchFieldDefinition $f): array => [
            'name' => $f->name,
            'type' => $f->type->value,
            'searchable' => $f->searchable,
            'filterable' => $f->filterable,
            'sortable' => $f->sortable,
        ], $definition->fields);

        return $this->renderer->render((new SearchPlannerPrompt())->withData(
            $definition->name,
            $definition->documentType,
            $fields,
            implode(', ', $policy->allowedOperators),
            $userQuery,
        ))->system;
    }
}
