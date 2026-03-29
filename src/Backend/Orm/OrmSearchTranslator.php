<?php

declare(strict_types=1);

namespace Semitexa\Search\Backend\Orm;

use Semitexa\Search\Enum\SearchFieldType;
use Semitexa\Search\Enum\SearchMatchStrategy;
use Semitexa\Search\Enum\SearchScope;
use Semitexa\Search\Index\SearchFieldDefinition;
use Semitexa\Search\Index\SearchIndexDefinition;
use Semitexa\Search\Value\SearchRequest;

final class OrmSearchTranslator
{
    /**
     * Apply tenant scope, filters, text query predicates, sorting, and pagination
     * to an ORM query based on the search request and index definition.
     */
    public function apply(
        OrmSearchQueryInterface $query,
        SearchIndexDefinition $definition,
        SearchRequest $request,
    ): OrmSearchQueryInterface {
        $query = $this->applyTenantScope($query, $request);
        $query = $this->applyFilters($query, $definition, $request);
        $query = $this->applyTextQuery($query, $definition, $request);
        $query = $this->applySorting($query, $definition, $request);
        $query = $this->applyPagination($query, $request);

        return $query;
    }

    private function applyTenantScope(OrmSearchQueryInterface $query, SearchRequest $request): OrmSearchQueryInterface
    {
        if ($request->scope === SearchScope::Tenant && $request->tenantId !== null) {
            $query = $query->where('tenant_id', '=', $request->tenantId);
        }

        return $query;
    }

    private function applyFilters(
        OrmSearchQueryInterface $query,
        SearchIndexDefinition $definition,
        SearchRequest $request,
    ): OrmSearchQueryInterface {
        foreach ($request->filters as $fieldName => $value) {
            $field = $definition->getField($fieldName);

            if ($field === null || !$field->filterable) {
                continue;
            }

            $column = $field->resolvedColumn();
            $query = $this->applyFilterValue($query, $column, $field, $value);
        }

        return $query;
    }

    private function applyFilterValue(
        OrmSearchQueryInterface $query,
        string $column,
        SearchFieldDefinition $field,
        mixed $value,
    ): OrmSearchQueryInterface {
        if (is_array($value) && (array_key_exists('from', $value) || array_key_exists('to', $value))) {
            return $this->applyRangeFilter($query, $column, $value);
        }

        if (is_array($value)) {
            return $query->whereIn($column, $value);
        }

        return $query->where($column, '=', $value);
    }

    /**
     * @param array{from?: scalar, to?: scalar} $range
     */
    private function applyRangeFilter(OrmSearchQueryInterface $query, string $column, array $range): OrmSearchQueryInterface
    {
        if (isset($range['from']) && isset($range['to'])) {
            return $query->whereBetween($column, $range['from'], $range['to']);
        }

        if (isset($range['from'])) {
            return $query->where($column, '>=', $range['from']);
        }

        if (isset($range['to'])) {
            return $query->where($column, '<=', $range['to']);
        }

        return $query;
    }

    private function applyTextQuery(
        OrmSearchQueryInterface $query,
        SearchIndexDefinition $definition,
        SearchRequest $request,
    ): OrmSearchQueryInterface {
        if ($request->query === null) {
            return $query;
        }

        $searchableFields = $definition->searchableFields();

        if (empty($searchableFields)) {
            return $query;
        }

        foreach ($searchableFields as $field) {
            $column = $field->resolvedColumn();
            $pattern = $this->buildLikePattern($field->matchStrategy, $request->query);
            $query = $query->orWhere($column, 'LIKE', $pattern);
        }

        return $query;
    }

    private function buildLikePattern(SearchMatchStrategy $strategy, string $query): string
    {
        $escaped = str_replace(['%', '_'], ['\\%', '\\_'], $query);

        return match ($strategy) {
            SearchMatchStrategy::Exact => $escaped,
            SearchMatchStrategy::Prefix => $escaped . '%',
            SearchMatchStrategy::Contains => '%' . $escaped . '%',
        };
    }

    private function applySorting(
        OrmSearchQueryInterface $query,
        SearchIndexDefinition $definition,
        SearchRequest $request,
    ): OrmSearchQueryInterface {
        if (!empty($request->sort)) {
            foreach ($request->sort as $clause) {
                $field = $definition->getField($clause->field);
                if ($field !== null && $field->sortable) {
                    $query = $query->orderBy($field->resolvedColumn(), $clause->normalizedDirection());
                }
            }
            return $query;
        }

        if (!empty($definition->defaultSort)) {
            foreach ($definition->defaultSort as $column) {
                $query = $query->orderBy($column, 'ASC');
            }
        }

        return $query;
    }

    private function applyPagination(OrmSearchQueryInterface $query, SearchRequest $request): OrmSearchQueryInterface
    {
        return $query->limit($request->limit)->offset($request->offset);
    }
}
