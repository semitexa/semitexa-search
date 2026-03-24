<?php

declare(strict_types=1);

namespace Semitexa\Search\Index;

use Semitexa\Search\Enum\SearchScope;

final readonly class SearchIndexDefinition
{
    /** @var array<string, SearchFieldDefinition> */
    public array $fieldMap;

    /**
     * @param list<SearchFieldDefinition> $fields
     * @param list<string> $defaultSort
     */
    public function __construct(
        public string $name,
        public string $documentType,
        public array $fields,
        public SearchScope $scopeMode = SearchScope::Tenant,
        public string $backend = 'orm',
        public ?string $repositoryClass = null,
        public ?string $entityClass = null,
        public array $defaultSort = [],
        public bool $plannerEnabled = false,
    ) {
        if ($this->name === '') {
            throw new \InvalidArgumentException('Search index name must not be empty');
        }

        if ($this->documentType === '') {
            throw new \InvalidArgumentException(
                "Search index '{$this->name}' must declare a document type"
            );
        }

        if (empty($this->fields)) {
            throw new \InvalidArgumentException(
                "Search index '{$this->name}' must declare at least one field"
            );
        }

        $map = [];
        foreach ($this->fields as $field) {
            if (isset($map[$field->name])) {
                throw new \InvalidArgumentException(
                    "Search index '{$this->name}' has duplicate field '{$field->name}'"
                );
            }
            $map[$field->name] = $field;
        }
        $this->fieldMap = $map;
    }

    /**
     * @return list<SearchFieldDefinition>
     */
    public function searchableFields(): array
    {
        return array_values(array_filter($this->fields, fn(SearchFieldDefinition $f) => $f->searchable));
    }

    /**
     * @return list<SearchFieldDefinition>
     */
    public function filterableFields(): array
    {
        return array_values(array_filter($this->fields, fn(SearchFieldDefinition $f) => $f->filterable));
    }

    /**
     * @return list<SearchFieldDefinition>
     */
    public function sortableFields(): array
    {
        return array_values(array_filter($this->fields, fn(SearchFieldDefinition $f) => $f->sortable));
    }

    public function getField(string $name): ?SearchFieldDefinition
    {
        return $this->fieldMap[$name] ?? null;
    }

    public function hasField(string $name): bool
    {
        return isset($this->fieldMap[$name]);
    }

    public function requiresTenantScope(): bool
    {
        return $this->scopeMode === SearchScope::Tenant;
    }
}
