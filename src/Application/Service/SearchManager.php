<?php

declare(strict_types=1);

namespace Semitexa\Search\Application\Service;

use Semitexa\Core\Attribute\InjectAsReadonly;
use Semitexa\Core\Attribute\SatisfiesServiceContract;
use Semitexa\Search\Configuration\SearchConfig;
use Semitexa\Search\Domain\Contract\SearchBackendInterface;
use Semitexa\Search\Domain\Contract\SearchIndexRegistryInterface;
use Semitexa\Search\Domain\Contract\SearchManagerInterface;
use Semitexa\Search\Domain\Model\SearchPlannerPolicy;
use Semitexa\Search\Domain\Contract\SearchQueryPlannerInterface;
use Semitexa\Search\Domain\Enum\SearchScope;
use Semitexa\Search\Exception\SearchBackendException;
use Semitexa\Search\Exception\SearchValidationException;
use Semitexa\Search\Application\Service\SearchFilterNormalizer;
use Semitexa\Search\Application\Service\SearchTextParser;
use Semitexa\Search\Domain\Model\SearchPlannerTrace;
use Semitexa\Search\Domain\Model\SearchRequest;
use Semitexa\Search\Domain\Model\SearchResult;
use Semitexa\Tenancy\Context\TenantContext;

#[SatisfiesServiceContract(of: SearchManagerInterface::class)]
final class SearchManager implements SearchManagerInterface
{
    #[InjectAsReadonly]
    protected SearchIndexRegistryInterface $registry;

    #[InjectAsReadonly]
    protected SearchBackendInterface $backend;

    private ?SearchConfig $config = null;
    private ?SearchFilterNormalizer $filterNormalizer = null;
    private ?SearchTextParser $textParser = null;
    private ?SearchRequestFactory $requestFactory = null;
    // Injected (the LLM planner): it was always the no-op, so the planner
    // the README promises never ran.
    #[InjectAsReadonly]
    protected SearchQueryPlannerInterface $planner;

    public function search(SearchRequest $request): SearchResult
    {
        $config = $this->config ??= SearchConfig::fromEnvironment();
        $filterNormalizer = $this->filterNormalizer ??= new SearchFilterNormalizer();
        $planner = isset($this->planner) ? $this->planner : new NoOpSearchQueryPlanner();
        $definition = $this->registry->get($request->index);
        self::assertWithinLimits($request, $config);

        $request = $this->enforceTenantScope($request, $definition->requiresTenantScope());

        $normalizedFilters = $filterNormalizer->normalize($definition, $request->filters);
        $request = $request->with(filters: $normalizedFilters);

        if ($this->shouldUsePlanner($definition, $request, $config)) {
            $request = $this->applyPlanner($definition, $request, $config, $planner);
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
        $textParser = $this->textParser ??= new SearchTextParser();
        $requestFactory = $this->requestFactory ??= new SearchRequestFactory($this->config ??= SearchConfig::fromEnvironment());
        $definition = $this->registry->get($index);

        $parsed = $textParser->parse($definition, $rawQuery);

        $mergedFilters = array_merge($parsed['filters'], $filters);

        $request = $requestFactory->create(
            index: $index,
            query: $parsed['query'],
            filters: $mergedFilters,
            limit: $limit,
        );

        return $this->search($request);
    }

    /** Deeper than this, an offset scans more rows than any page of results is worth. */
    private const MAX_OFFSET = 10_000;

    /** Values one filter may list (`whereIn`). */
    private const MAX_FILTER_VALUES = 1_000;

    /**
     * The limits SearchRequestFactory checks, here as well: a request built
     * by hand and passed to search() was taken with `limit: 1000000`, any
     * offset and any length of query or value list.
     */
    private static function assertWithinLimits(SearchRequest $request, SearchConfig $config): void
    {
        $violations = [];
        if ($request->query !== null && mb_strlen($request->query) > $config->maxQueryLength) {
            $violations[] = "Query exceeds maximum length of {$config->maxQueryLength} characters";
        }
        if (count($request->filters) > $config->maxFilterCount) {
            $violations[] = "Filter count exceeds maximum of {$config->maxFilterCount}";
        }
        foreach ($request->filters as $field => $value) {
            if (is_array($value) && array_is_list($value) && count($value) > self::MAX_FILTER_VALUES) {
                $violations[] = "Filter '{$field}' lists more than " . self::MAX_FILTER_VALUES . ' values';
            }
        }
        if ($request->limit > $config->maxResultLimit) {
            $violations[] = "Limit exceeds maximum of {$config->maxResultLimit}";
        }
        if ($request->offset > self::MAX_OFFSET) {
            $violations[] = 'Offset exceeds maximum of ' . self::MAX_OFFSET;
        }
        if ($violations !== []) {
            throw SearchValidationException::fromViolations($violations);
        }
    }

    private function enforceTenantScope(SearchRequest $request, bool $requiresTenant): SearchRequest
    {
        if (!$requiresTenant) {
            return $request;
        }

        // Reset to the tenant scope AND filled in below: returning here left the
        // tenant id empty, the translator added no tenant condition, and a
        // Global request on a tenant index searched every tenant.
        if ($request->scope !== SearchScope::Tenant) {
            $request = $request->with(scope: SearchScope::Tenant);
        }

        if ($request->tenantId !== null) {
            return $request;
        }

        $tenantContext = TenantContext::get();
        $tenantId = $tenantContext?->getTenantId() ?? 'default';

        return $request->with(tenantId: $tenantId);
    }

    private function shouldUsePlanner(
        \Semitexa\Search\Domain\Model\SearchIndexDefinition $definition,
        SearchRequest $request,
        SearchConfig $config,
    ): bool {
        if (!$config->plannerEnabled) {
            return false;
        }

        if (!$definition->plannerEnabled) {
            return false;
        }

        if ($request->query === null) {
            return false;
        }

        // A model call (up to 3 s, paid) for every search: a single word has
        // no filter or sort in it to find, and a public box made each one a call.
        $words = preg_split('/\s+/u', trim($request->query), -1, PREG_SPLIT_NO_EMPTY);

        return is_array($words) && count($words) >= 2;
    }

    private function applyPlanner(
        \Semitexa\Search\Domain\Model\SearchIndexDefinition $definition,
        SearchRequest $request,
        SearchConfig $config,
        SearchQueryPlannerInterface $planner,
    ): SearchRequest {
        $policy = new SearchPlannerPolicy(
            minConfidence: $config->plannerMinConfidence,
            timeoutMs: $config->plannerTimeoutMs,
        );

        try {
            $result = $planner->plan($definition, $request, $policy);
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

        if (!$result->isUsable($config->plannerMinConfidence)) {
            $trace = $result->trace ?? new SearchPlannerTrace(
                plannerName: $result->plannerName,
                confidence: $result->confidence,
                wasUsed: false,
                fallbackReason: 'Below minimum confidence threshold',
                warnings: $result->warnings,
            );

            return $request->with(plannerTrace: $trace);
        }

        // The request's own filters win: the app's `status: published` was
        // replaced by a model that read "show drafts" in the query.
        $mergedFilters = array_merge($result->filters, $request->filters);
        // The request's own sort wins too, as its filters do.
        $sort = $request->sort !== [] ? $request->sort : $result->sort;

        $trace = $result->trace ?? new SearchPlannerTrace(
            plannerName: $result->plannerName,
            confidence: $result->confidence,
            wasUsed: true,
        );

        // A null query from the plan means "filters only": with() would have
        // kept the natural-language text and matched nothing. Unless the plan
        // adds no filter of its own: then the text is all there is, and
        // dropping it listed everything.
        $addsFilters = array_diff_key($result->filters, $request->filters) !== [];
        $query = $result->query ?? ($addsFilters ? null : $request->query);

        return $request->with(
            filters: $mergedFilters,
            sort: $sort,
            plannerTrace: $trace,
        )->withQuery($query);
    }
}
