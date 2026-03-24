<?php

declare(strict_types=1);

namespace Semitexa\Search\Service;

use Semitexa\Core\Attributes\SatisfiesServiceContract;
use Semitexa\Search\Configuration\SearchConfig;
use Semitexa\Search\Contract\SearchBackendInterface;
use Semitexa\Search\Contract\SearchIndexRegistryInterface;
use Semitexa\Search\Contract\SearchManagerInterface;
use Semitexa\Search\Contract\SearchPlannerPolicy;
use Semitexa\Search\Contract\SearchQueryPlannerInterface;
use Semitexa\Search\Enum\SearchScope;
use Semitexa\Search\Exception\SearchBackendException;
use Semitexa\Search\Parsing\SearchFilterNormalizer;
use Semitexa\Search\Parsing\SearchTextParser;
use Semitexa\Search\Value\SearchPlannerTrace;
use Semitexa\Search\Value\SearchRequest;
use Semitexa\Search\Value\SearchResult;
use Semitexa\Tenancy\Context\TenantContext;

#[SatisfiesServiceContract(of: SearchManagerInterface::class)]
final class SearchManager implements SearchManagerInterface
{
    private SearchConfig $config;
    private SearchFilterNormalizer $filterNormalizer;
    private SearchTextParser $textParser;
    private SearchRequestFactory $requestFactory;
    private SearchQueryPlannerInterface $planner;

    public function __construct(
        private SearchIndexRegistryInterface $registry,
        private SearchBackendInterface $backend,
        ?SearchQueryPlannerInterface $planner = null,
        ?SearchConfig $config = null,
    ) {
        $this->config = $config ?? SearchConfig::fromEnvironment();
        $this->planner = $planner ?? new NoOpSearchQueryPlanner();
        $this->filterNormalizer = new SearchFilterNormalizer();
        $this->textParser = new SearchTextParser();
        $this->requestFactory = new SearchRequestFactory($this->config);
    }

    public function search(SearchRequest $request): SearchResult
    {
        $definition = $this->registry->get($request->index);

        $request = $this->enforceTenantScope($request, $definition->requiresTenantScope());

        $normalizedFilters = $this->filterNormalizer->normalize($definition, $request->filters);
        $request = $request->with(filters: $normalizedFilters);

        if ($this->shouldUsePlanner($definition, $request)) {
            $request = $this->applyPlanner($definition, $request);
        }

        if (!$this->backend->supports($definition)) {
            throw new SearchBackendException(
                "Backend does not support index '{$definition->name}' (backend: '{$definition->backend}')"
            );
        }

        return $this->backend->search($definition, $request);
    }

    /**
     * @param array<string, scalar|list<scalar>|array{from?: scalar, to?: scalar}> $filters
     */
    public function searchText(
        string $index,
        string $rawQuery,
        int $limit = 20,
        array $filters = [],
    ): SearchResult {
        $definition = $this->registry->get($index);

        $parsed = $this->textParser->parse($definition, $rawQuery);

        $mergedFilters = array_merge($parsed['filters'], $filters);

        $request = $this->requestFactory->create(
            index: $index,
            query: $parsed['query'],
            filters: $mergedFilters,
            limit: $limit,
        );

        return $this->search($request);
    }

    private function enforceTenantScope(SearchRequest $request, bool $requiresTenant): SearchRequest
    {
        if (!$requiresTenant) {
            return $request;
        }

        if ($request->scope !== SearchScope::Tenant) {
            return $request->with(scope: SearchScope::Tenant);
        }

        if ($request->tenantId !== null) {
            return $request;
        }

        $tenantContext = TenantContext::get();
        $tenantId = $tenantContext?->getTenantId() ?? 'default';

        return $request->with(tenantId: $tenantId);
    }

    private function shouldUsePlanner(
        \Semitexa\Search\Index\SearchIndexDefinition $definition,
        SearchRequest $request,
    ): bool {
        if (!$this->config->plannerEnabled) {
            return false;
        }

        if (!$definition->plannerEnabled) {
            return false;
        }

        if ($request->query === null) {
            return false;
        }

        return true;
    }

    private function applyPlanner(
        \Semitexa\Search\Index\SearchIndexDefinition $definition,
        SearchRequest $request,
    ): SearchRequest {
        $policy = new SearchPlannerPolicy(
            minConfidence: $this->config->plannerMinConfidence,
            timeoutMs: $this->config->plannerTimeoutMs,
        );

        try {
            $result = $this->planner->plan($definition, $request, $policy);
        } catch (\Throwable $e) {
            return $request->with(
                plannerTrace: new SearchPlannerTrace(
                    plannerName: 'unknown',
                    confidence: 0.0,
                    wasUsed: false,
                    fallbackReason: 'Planner error: ' . $e->getMessage(),
                ),
            );
        }

        if (!$result->isUsable($this->config->plannerMinConfidence)) {
            $trace = $result->trace ?? new SearchPlannerTrace(
                plannerName: $result->plannerName,
                confidence: $result->confidence,
                wasUsed: false,
                fallbackReason: 'Below minimum confidence threshold',
                warnings: $result->warnings,
            );

            return $request->with(plannerTrace: $trace);
        }

        $mergedFilters = array_merge($request->filters, $result->filters);
        $sort = !empty($result->sort) ? $result->sort : $request->sort;

        $trace = $result->trace ?? new SearchPlannerTrace(
            plannerName: $result->plannerName,
            confidence: $result->confidence,
            wasUsed: true,
        );

        return $request->with(
            query: $result->query,
            filters: $mergedFilters,
            sort: $sort,
            plannerTrace: $trace,
        );
    }
}
