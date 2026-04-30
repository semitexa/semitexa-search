<?php

declare(strict_types=1);

namespace Semitexa\Search\Application\Service;

use Semitexa\Search\Domain\Model\SearchPlannerPolicy;
use Semitexa\Search\Domain\Contract\SearchQueryPlannerInterface;
use Semitexa\Search\Domain\Model\SearchIndexDefinition;
use Semitexa\Search\Domain\Model\SearchPlannerTrace;
use Semitexa\Search\Domain\Model\SearchPlanningResult;
use Semitexa\Search\Domain\Model\SearchRequest;

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
