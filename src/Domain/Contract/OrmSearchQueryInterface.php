<?php

declare(strict_types=1);

namespace Semitexa\Search\Domain\Contract;

interface OrmSearchQueryInterface
{
    public function where(string $column, string $operator, mixed $value): static;

    public function orWhere(string $column, string $operator, mixed $value): static;

    /**
     * One group, AND-ed with the conditions before it: `(a LIKE :pa OR b LIKE :pb …)`.
     * The text match is this group; as loose orWhere() calls it ended up
     * OR-ed beside the tenant and filter conditions.
     *
     * @param non-empty-array<string, string> $patternByColumn column => LIKE pattern
     */
    public function whereAnyLike(array $patternByColumn): static;

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
