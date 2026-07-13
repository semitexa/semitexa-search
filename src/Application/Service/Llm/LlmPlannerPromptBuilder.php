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
            'fields' => $this->buildFieldManifest($definition),
            'operators' => implode(', ', $policy->allowedOperators),
            'user_query' => $userQuery,
        ])->system;
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
