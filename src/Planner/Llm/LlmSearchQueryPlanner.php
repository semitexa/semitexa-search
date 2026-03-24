<?php

declare(strict_types=1);

namespace Semitexa\Search\Planner\Llm;

use Semitexa\Search\Contract\SearchPlannerPolicy;
use Semitexa\Search\Contract\SearchQueryPlannerInterface;
use Semitexa\Search\Index\SearchIndexDefinition;
use Semitexa\Search\Value\SearchPlannerTrace;
use Semitexa\Search\Value\SearchPlanningResult;
use Semitexa\Search\Value\SearchRequest;
use Semitexa\Search\Value\SortClause;

final class LlmSearchQueryPlanner implements SearchQueryPlannerInterface
{
    public function __construct(
        private LlmPlannerPromptBuilder $promptBuilder,
        private LlmPlannerSchemaValidator $schemaValidator,
        private LlmPlannerBridge $bridge,
    ) {}

    public function plan(
        SearchIndexDefinition $definition,
        SearchRequest $request,
        SearchPlannerPolicy $policy,
    ): SearchPlanningResult {
        if ($request->query === null) {
            return $this->noOpResult($request, 'No query text to plan');
        }

        $startTime = hrtime(true);

        try {
            $prompt = $this->promptBuilder->build($definition, $request->query, $policy);
            $rawResponse = $this->bridge->execute($prompt, $policy->timeoutMs);
            $elapsedMs = (hrtime(true) - $startTime) / 1_000_000;

            $parsed = json_decode($rawResponse, true, 512, JSON_THROW_ON_ERROR);

            $violations = $this->schemaValidator->validate($parsed, $definition, $policy);

            if (!empty($violations)) {
                return $this->fallbackResult(
                    $request,
                    'Schema validation failed: ' . implode('; ', $violations),
                    $elapsedMs,
                    $violations,
                );
            }

            $sort = [];
            foreach (($parsed['sort'] ?? []) as $sortItem) {
                $sort[] = new SortClause(
                    field: $sortItem['field'],
                    direction: $sortItem['direction'] ?? 'ASC',
                );
            }

            return new SearchPlanningResult(
                query: $parsed['query'] ?? null,
                filters: $parsed['filters'] ?? [],
                sort: $sort,
                confidence: (float) ($parsed['confidence'] ?? 0.0),
                warnings: $parsed['warnings'] ?? [],
                plannerName: 'llm',
                trace: new SearchPlannerTrace(
                    plannerName: 'llm',
                    confidence: (float) ($parsed['confidence'] ?? 0.0),
                    wasUsed: true,
                    latencyMs: round($elapsedMs, 2),
                ),
            );
        } catch (\JsonException $e) {
            $elapsedMs = (hrtime(true) - $startTime) / 1_000_000;
            return $this->fallbackResult($request, 'Invalid JSON response: ' . $e->getMessage(), $elapsedMs);
        } catch (\Throwable $e) {
            $elapsedMs = (hrtime(true) - $startTime) / 1_000_000;
            return $this->fallbackResult($request, 'Planner error: ' . $e->getMessage(), $elapsedMs);
        }
    }

    private function noOpResult(SearchRequest $request, string $reason): SearchPlanningResult
    {
        return new SearchPlanningResult(
            query: $request->query,
            filters: $request->filters,
            sort: $request->sort,
            confidence: 1.0,
            plannerName: 'llm',
            trace: new SearchPlannerTrace(
                plannerName: 'llm',
                confidence: 1.0,
                wasUsed: false,
                fallbackReason: $reason,
            ),
        );
    }

    /**
     * @param list<string> $warnings
     */
    private function fallbackResult(
        SearchRequest $request,
        string $reason,
        float $latencyMs,
        array $warnings = [],
    ): SearchPlanningResult {
        return new SearchPlanningResult(
            query: $request->query,
            filters: $request->filters,
            sort: $request->sort,
            confidence: 0.0,
            warnings: $warnings,
            plannerName: 'llm',
            trace: new SearchPlannerTrace(
                plannerName: 'llm',
                confidence: 0.0,
                wasUsed: false,
                fallbackReason: $reason,
                warnings: $warnings,
                latencyMs: round($latencyMs, 2),
            ),
        );
    }
}
