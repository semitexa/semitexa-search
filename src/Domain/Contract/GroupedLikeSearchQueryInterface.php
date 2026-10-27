<?php

declare(strict_types=1);

namespace Semitexa\Search\Domain\Contract;

/**
 * A query that can match text across several columns as one group. Kept
 * apart from OrmSearchQueryInterface so a project's own query class does not
 * break; without it, a text search over more than one column is refused
 * rather than OR-ed beside the tenant condition.
 */
interface GroupedLikeSearchQueryInterface
{
    /**
     * One group, AND-ed with the conditions before it: `(a LIKE :pa OR b LIKE :pb …)`.
     * The text match is this group; as loose orWhere() calls it ended up
     * OR-ed beside the tenant and filter conditions.
     *
     * @param non-empty-array<string, string> $patternByColumn column => LIKE pattern
     */
    public function whereAnyLike(array $patternByColumn): static;
}
