<?php

declare(strict_types=1);

namespace Semitexa\Search\Application\Service\Llm;

use Semitexa\Prompt\Application\Service\PromptRegistry;
use Semitexa\Prompt\Application\Service\PromptRenderer;
use Semitexa\Prompt\Domain\Model\PromptTemplate;
use Semitexa\Search\Application\Prompt\SearchPlannerPrompt;
use Semitexa\Search\Domain\Model\SearchPlannerPolicy;
use Semitexa\Search\Domain\Model\SearchIndexDefinition;

final class LlmPlannerPromptBuilder
{
    private ?PromptRenderer $renderer = null;
    private ?PromptTemplate $template = null;

    public function build(
        SearchIndexDefinition $definition,
        string $userQuery,
        SearchPlannerPolicy $policy,
    ): string {
        $this->renderer ??= new PromptRenderer();
        $this->template ??= (new PromptRegistry())
            ->buildFromClasses([SearchPlannerPrompt::class])[SearchPlannerPrompt::ID];

        return $this->renderer->renderTemplate($this->template, [
            'index_name' => $definition->name,
            'document_type' => $definition->documentType,
            // Raw field data — the template's {% for %} loop formats the manifest.
            'fields' => array_map(static fn($f): array => [
                'name' => $f->name,
                'type' => $f->type->value,
                'searchable' => $f->searchable,
                'filterable' => $f->filterable,
                'sortable' => $f->sortable,
            ], $definition->fields),
            'operators' => implode(', ', $policy->allowedOperators),
            'user_query' => $userQuery,
        ])->system;
    }

}
