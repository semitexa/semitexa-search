<?php

declare(strict_types=1);

namespace Semitexa\Search\Observability;

use Semitexa\Search\Value\SearchRequest;
use Semitexa\Search\Value\SearchResult;

final class SearchLogger
{
    /**
     * Build a structured log entry for a completed search execution.
     *
     * @return array<string, mixed>
     */
    public function buildLogEntry(SearchRequest $request, SearchResult $result): array
    {
        $entry = [
            'index' => $request->index,
            'scope' => $request->scope->value,
            'has_query' => $request->query !== null,
            'filter_count' => count($request->filters),
            'limit' => $request->limit,
            'offset' => $request->offset,
            'result_count' => $result->count(),
            'total' => $result->total,
            'partial' => $result->partial,
        ];

        if (isset($result->metadata['latency_ms'])) {
            $entry['latency_ms'] = $result->metadata['latency_ms'];
        }

        if (isset($result->metadata['backend'])) {
            $entry['backend'] = $result->metadata['backend'];
        }

        if (isset($result->metadata['ranking_mode'])) {
            $entry['ranking_mode'] = $result->metadata['ranking_mode'];
        }

        if ($request->plannerTrace !== null) {
            $entry['planner'] = [
                'name' => $request->plannerTrace->plannerName,
                'was_used' => $request->plannerTrace->wasUsed,
                'confidence' => $request->plannerTrace->confidence,
                'latency_ms' => $request->plannerTrace->latencyMs,
            ];

            if ($request->plannerTrace->fallbackReason !== null) {
                $entry['planner']['fallback_reason'] = $request->plannerTrace->fallbackReason;
            }

            if (!empty($request->plannerTrace->warnings)) {
                $entry['planner']['warning_count'] = count($request->plannerTrace->warnings);
            }
        }

        if ($result->isEmpty()) {
            $entry['zero_results'] = true;
        }

        return $entry;
    }

    /**
     * Build a structured log entry for a failed search execution.
     *
     * @return array<string, mixed>
     */
    public function buildErrorEntry(SearchRequest $request, \Throwable $error): array
    {
        return [
            'index' => $request->index,
            'scope' => $request->scope->value,
            'error' => $error->getMessage(),
            'error_class' => $error::class,
            'has_query' => $request->query !== null,
            'filter_count' => count($request->filters),
        ];
    }
}
