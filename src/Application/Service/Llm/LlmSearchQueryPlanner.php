<?php

declare(strict_types=1);

namespace Semitexa\Search\Application\Service\Llm;

use Semitexa\Core\Attribute\InjectAsReadonly;
use Semitexa\Core\Attribute\SatisfiesServiceContract;
use Psr\Container\ContainerInterface;
use Semitexa\Core\Log\StaticLoggerBridge;
use Semitexa\Core\RateLimit\RateLimiter;
use Semitexa\Core\RateLimit\RateLimitKey;
use Semitexa\Search\Application\Service\SearchFilterNormalizer;
use Semitexa\Search\Exception\SearchValidationException;
use Semitexa\Search\Domain\Contract\LlmPlannerBridgeInterface;
use Semitexa\Search\Domain\Contract\LlmPlannerMessageBridgeInterface;
use Semitexa\Search\Domain\Contract\SearchQueryPlannerInterface;
use Semitexa\Search\Domain\Model\SearchIndexDefinition;
use Semitexa\Search\Domain\Model\SearchPlannerPolicy;
use Semitexa\Search\Domain\Model\SearchPlannerTrace;
use Semitexa\Search\Domain\Model\SearchPlanningResult;
use Semitexa\Search\Domain\Model\SearchRequest;
use Semitexa\Search\Domain\Model\SortClause;

/**
 * Turns a natural-language query into structured search: the prompt from
 * LlmPlannerPromptBuilder goes to the model through the bridge, and the JSON
 * that comes back is checked by LlmPlannerSchemaValidator before any of it is
 * used. Anything off (no model, a timeout, invalid JSON, a field the index
 * does not have) is a warning, and SearchManager then searches the query as
 * typed. Used only when SEARCH_PLANNER_ENABLED=1 and the index has
 * plannerEnabled; the package shipped every piece of this but the planner.
 */
#[SatisfiesServiceContract(of: SearchQueryPlannerInterface::class)]
final class LlmSearchQueryPlanner implements SearchQueryPlannerInterface
{
    #[InjectAsReadonly]
    protected LlmPlannerBridgeInterface $bridge;

    // The per-execution RateLimiter is taken from it per call: this planner
    // lives as long as the worker.
    #[InjectAsReadonly]
    protected ContainerInterface $container;

    private ?LlmPlannerPromptBuilder $promptBuilder = null;
    private ?LlmPlannerSchemaValidator $validator = null;

    private const CACHE_SECONDS = 600;
    private const CACHE_ENTRIES = 256;

    /**
     * Answers this worker already paid for: the same query on the same index
     * asked again (a reload, a next page) made the same model call again.
     *
     * @var array<string, array{at: int, result: SearchPlanningResult}>
     */
    private array $answered = [];

    public function plan(SearchIndexDefinition $definition, SearchRequest $request, SearchPlannerPolicy $policy): SearchPlanningResult
    {
        $key = sha1($definition->name . "\0" . mb_strtolower(trim((string) $request->query)) . "\0" . serialize($policy));
        $cached = $this->answered[$key] ?? null;
        if ($cached !== null && time() - $cached['at'] < self::CACHE_SECONDS) {
            return $cached['result'];
        }
        // After the cache: an answer already paid for costs nothing. Refused,
        // the query is searched as typed, and not remembered for the others.
        $limiter = $decision = null;
        if ($policy->perClientPerMinute > 0 && isset($this->container)) {
            try {
                $limiter = $this->container->get(RateLimiter::class);
            } catch (\Throwable) {
                // No request (a command, a job): no client to count, and the
                // planner kept working there before the limit.
            }
            $decision = $limiter?->attempt('search-planner', $policy->perClientPerMinute, 60, RateLimitKey::User);
            if ($decision !== null && !$decision->allowed) {
                return $this->limited($request, $decision->retryAfter);
            }
        }
        $result = $this->planAnew($definition, $request, $policy);
        // A call that reached no model (none installed, a provider error) is
        // not counted: after 20 the trace said "rate limit" and hid why.
        if ($decision !== null && str_starts_with((string) $result->trace?->fallbackReason, 'Planner failed')) {
            $limiter?->release($decision);
        }
        // A model that could not be reached is asked again next time.
        if ($result->trace?->fallbackReason === null || !str_starts_with($result->trace->fallbackReason, 'Planner failed')) {
            if (count($this->answered) >= self::CACHE_ENTRIES) {
                array_shift($this->answered);
            }
            $this->answered[$key] = ['at' => time(), 'result' => $result];
        }

        return $result;
    }

    private function planAnew(SearchIndexDefinition $definition, SearchRequest $request, SearchPlannerPolicy $policy): SearchPlanningResult
    {
        $started = hrtime(true);
        $query = (string) $request->query;
        // An empty user message is refused by the providers, and a failed
        // plan is not remembered: every such search paid for a call.
        if (trim($query) === '') {
            return $this->fallback($query, 'No query to plan', $started);
        }
        try {
            $builder = $this->promptBuilder ??= new LlmPlannerPromptBuilder();
            // The query as the user message: in the system prompt it read to
            // the model as an instruction. A bridge of a project's own that
            // only knows execute() still gets it the old way.
            $raw = $this->bridge instanceof LlmPlannerMessageBridgeInterface
                ? $this->bridge->executeMessage($builder->build($definition, null, $policy), $query, $policy->timeoutMs)
                : $this->bridge->execute($builder->build($definition, $query, $policy), $policy->timeoutMs);
            $parsed = json_decode(self::jsonPart($raw), true, 32, JSON_THROW_ON_ERROR);
            if (!is_array($parsed)) {
                throw new \UnexpectedValueException('the model did not answer with a JSON object');
            }
        } catch (\Throwable $e) {
            return $this->fallback($query, 'Planner failed: ' . $e->getMessage(), $started);
        }

        $violations = ($this->validator ??= new LlmPlannerSchemaValidator())->validate($parsed, $definition, $policy);
        if ($violations !== []) {
            return $this->fallback($query, 'Planner answer refused', $started, $violations);
        }
        // The plan's filters go through the same checks as a caller's: types,
        // ranges only on range fields, list shapes. They were merged as given.
        try {
            $filters = (new SearchFilterNormalizer())->normalize($definition, is_array($parsed['filters'] ?? null) ? $parsed['filters'] : []);
        } catch (SearchValidationException $e) {
            return $this->fallback($query, 'Planner filters refused', $started, [$e->getMessage()]);
        }

        $sort = [];
        foreach (is_array($parsed['sort'] ?? null) ? $parsed['sort'] : [] as $item) {
            $sort[] = new SortClause($item['field'], is_string($item['direction'] ?? null) ? strtoupper($item['direction']) : 'ASC');
        }
        $confidence = (float) ($parsed['confidence'] ?? 0.0);
        $warnings = array_values(array_filter(is_array($parsed['warnings'] ?? null) ? $parsed['warnings'] : [], 'is_string'));
        if ($confidence < $policy->minConfidence || $warnings !== []) {
            // Discarded by SearchManager: said here, with why.
            return $this->fallback($query, $warnings !== [] ? 'Planner answer carried warnings' : "Planner confidence {$confidence} below {$policy->minConfidence}", $started, $warnings);
        }

        return new SearchPlanningResult(
            query: is_string($parsed['query'] ?? null) && trim($parsed['query']) !== '' ? $parsed['query'] : null,
            filters: $filters,
            sort: $sort,
            confidence: $confidence,
            warnings: $warnings,
            plannerName: 'llm',
            trace: new SearchPlannerTrace(
                plannerName: 'llm',
                confidence: $confidence,
                wasUsed: true,
                latencyMs: (hrtime(true) - $started) / 1_000_000,
            ),
        );
    }

    /** Not logged: a client hammering the search would flood the log. */
    private function limited(SearchRequest $request, int $retryAfter): SearchPlanningResult
    {
        $reason = "Planner rate limit for this client: retry in {$retryAfter} s";

        return new SearchPlanningResult(
            query: (string) $request->query,
            confidence: 0.0,
            warnings: [$reason],
            plannerName: 'llm',
            trace: new SearchPlannerTrace(plannerName: 'llm', confidence: 0.0, wasUsed: false, fallbackReason: $reason),
        );
    }

    /** @param list<string> $warnings */
    private function fallback(string $query, string $reason, int $started, array $warnings = []): SearchPlanningResult
    {
        // Said: the trace stays on the request, out of the caller's sight,
        // and a misconfigured model otherwise failed (and was paid) silently.
        StaticLoggerBridge::warning('search', "Search planner fell back to the query as typed: {$reason}", ['warnings' => $warnings]);

        return new SearchPlanningResult(
            query: $query,
            confidence: 0.0,
            warnings: $warnings !== [] ? $warnings : [$reason],
            plannerName: 'llm',
            trace: new SearchPlannerTrace(
                plannerName: 'llm',
                confidence: 0.0,
                wasUsed: false,
                fallbackReason: $reason,
                warnings: $warnings,
                latencyMs: (hrtime(true) - $started) / 1_000_000,
            ),
        );
    }

    /** The JSON object in a reply, without a fenced code block or prose around it. */
    private static function jsonPart(string $raw): string
    {
        $start = strpos($raw, '{');
        $end = strrpos($raw, '}');

        return $start !== false && $end !== false && $end > $start ? substr($raw, $start, $end - $start + 1) : $raw;
    }
}
