<?php

declare(strict_types=1);

namespace Semitexa\Search\Service;

use Semitexa\Search\Contract\SearchPlannerPolicy;
use Semitexa\Search\Contract\SearchQueryPlannerInterface;
use Semitexa\Search\Index\SearchIndexDefinition;
use Semitexa\Search\Value\SearchPlannerTrace;
use Semitexa\Search\Value\SearchPlanningResult;
use Semitexa\Search\Value\SearchRequest;

final class NoOpSearchQueryPlanner implements SearchQueryPlannerInterface
{
    public function plan(
        SearchIndexDefinition $definition,
        SearchRequest $request,
        SearchPlannerPolicy $policy,
    ): SearchPlanningResult {
        return new SearchPlanningResult(
            query: $request->query,
            filters: $request->filters,
            sort: $request->sort,
            confidence: 1.0,
            plannerName: 'noop',
            trace: new SearchPlannerTrace(
                plannerName: 'noop',
                confidence: 1.0,
                wasUsed: false,
                fallbackReason: 'No planner configured',
            ),
        );
    }
}
