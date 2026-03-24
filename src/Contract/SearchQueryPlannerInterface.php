<?php

declare(strict_types=1);

namespace Semitexa\Search\Contract;

use Semitexa\Search\Index\SearchIndexDefinition;
use Semitexa\Search\Value\SearchPlanningResult;
use Semitexa\Search\Value\SearchRequest;

interface SearchQueryPlannerInterface
{
    public function plan(
        SearchIndexDefinition $definition,
        SearchRequest $request,
        SearchPlannerPolicy $policy,
    ): SearchPlanningResult;
}
