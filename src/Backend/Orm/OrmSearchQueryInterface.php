<?php

declare(strict_types=1);

namespace Semitexa\Search\Backend\Orm;

interface OrmSearchQueryInterface
{
    public function where(string $column, string $operator, mixed $value): static;

    public function orWhere(string $column, string $operator, mixed $value): static;

    /**
     * @param array<int, scalar|null> $values
     */
    public function whereIn(string $column, array $values): static;

    public function whereBetween(string $column, mixed $from, mixed $to): static;

    public function orderBy(string $column, string $direction = 'ASC'): static;

    public function limit(int $limit): static;

    public function offset(int $offset): static;

    /**
     * @return array<object>
     */
    public function fetchAll(): array;

    public function count(): int;
}
