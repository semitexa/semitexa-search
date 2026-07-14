<?php

declare(strict_types=1);

namespace Semitexa\Search\Application\Prompt;

use Semitexa\Prompt\Attribute\AsPrompt;
use Semitexa\Prompt\Domain\Contract\BoundPromptInterface;

/**
 * Thin, self-binding prompt — body in resources/prompts/search.query-planner.twig.
 */
#[AsPrompt(
    id: self::ID,
    channel: 'search',
    template: 'resources/prompts/search.query-planner.twig',
    description: 'LLM search-query planner: natural language to a structured JSON query.',
)]
final class SearchPlannerPrompt implements BoundPromptInterface
{
    public const ID = 'search.query-planner';

    /**
     * @param list<array{name: string, type: string, searchable: bool, filterable: bool, sortable: bool}> $fields
     */
    public function __construct(
        private readonly ?string $indexName = null,
        private readonly ?string $documentType = null,
        private readonly array $fields = [],
        private readonly ?string $operators = null,
        private readonly ?string $userQuery = null,
    ) {}

    /**
     * @param list<array{name: string, type: string, searchable: bool, filterable: bool, sortable: bool}> $fields
     */
    public function withData(string $indexName, string $documentType, array $fields, string $operators, string $userQuery): self
    {
        return new self($indexName, $documentType, $fields, $operators, $userQuery);
    }

    public function promptId(): string
    {
        return self::ID;
    }

    public function indexName(): string
    {
        return (string) $this->indexName;
    }

    public function documentType(): string
    {
        return (string) $this->documentType;
    }

    /** @return list<array{name: string, type: string, searchable: bool, filterable: bool, sortable: bool}> */
    public function fields(): array
    {
        return $this->fields;
    }

    public function operators(): string
    {
        return (string) $this->operators;
    }

    public function userQuery(): string
    {
        return (string) $this->userQuery;
    }
}
