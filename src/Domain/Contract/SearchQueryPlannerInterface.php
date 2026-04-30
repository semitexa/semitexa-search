<?php

declare(strict_types=1);

namespace Semitexa\Search\Domain\Contract;

use Semitexa\Search\Domain\Model\SearchPlannerPolicy;
use Semitexa\Search\Domain\Model\SearchIndexDefinition;
use Semitexa\Search\Domain\Model\SearchPlanningResult;
use Semitexa\Search\Domain\Model\SearchRequest;

interface SearchQueryPlannerInterface
{
    public function plan(
        SearchIndexDefinition $definition,
        SearchRequest $request,
        SearchPlannerPolicy $policy,
    ): SearchPlanningResult;
}
