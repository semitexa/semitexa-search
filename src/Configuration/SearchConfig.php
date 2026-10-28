<?php

declare(strict_types=1);

namespace Semitexa\Search\Configuration;

use Semitexa\Core\Environment;

final readonly class SearchConfig
{
    public function __construct(
        public int $defaultLimit = 20,
        public int $maxQueryLength = 500,
        public int $maxFilterCount = 20,
        public int $maxResultLimit = 100,
        public bool $plannerEnabled = false,
        public int $plannerTimeoutMs = 3000,
        public float $plannerMinConfidence = 0.5,
        public bool $observabilityEnabled = true,
        public int $plannerRateLimit = 20,
    ) {}

    public static function fromEnvironment(): self
    {
        $defaultLimit = (int) Environment::getEnvValue('SEARCH_DEFAULT_LIMIT', '20');
        $maxQueryLength = (int) Environment::getEnvValue('SEARCH_MAX_QUERY_LENGTH', '500');
        $maxFilterCount = (int) Environment::getEnvValue('SEARCH_MAX_FILTER_COUNT', '20');
        $maxResultLimit = (int) Environment::getEnvValue('SEARCH_MAX_RESULT_LIMIT', '100');
        $plannerEnabled = Environment::getEnvValue('SEARCH_PLANNER_ENABLED', '0') === '1';
        $plannerTimeoutMs = (int) Environment::getEnvValue('SEARCH_PLANNER_TIMEOUT_MS', '3000');
        $plannerMinConfidence = (float) Environment::getEnvValue('SEARCH_PLANNER_MIN_CONFIDENCE', '0.5');
        $observabilityEnabled = Environment::getEnvValue('SEARCH_OBSERVABILITY_ENABLED', '1') === '1';
        // Read strictly: `(int)` made a typo ("20/min", "twenty") into 0,
        // which here means "no limit" — the cap on paid model calls was gone
        // and nothing said so. Set but blank is the default.
        $rawRateLimit = trim((string) Environment::getEnvValue('SEARCH_PLANNER_RATE_LIMIT', '20'));
        $plannerRateLimit = filter_var($rawRateLimit === '' ? '20' : $rawRateLimit, FILTER_VALIDATE_INT);
        if ($plannerRateLimit === false) {
            throw new \InvalidArgumentException(
                "SEARCH_PLANNER_RATE_LIMIT must be a whole number (0 = no limit), got '{$rawRateLimit}'"
            );
        }

        if ($defaultLimit < 1 || $defaultLimit > $maxResultLimit) {
            throw new \InvalidArgumentException(
                "SEARCH_DEFAULT_LIMIT must be between 1 and {$maxResultLimit}, got {$defaultLimit}"
            );
        }

        if ($maxQueryLength < 1) {
            throw new \InvalidArgumentException(
                "SEARCH_MAX_QUERY_LENGTH must be positive, got {$maxQueryLength}"
            );
        }

        if ($maxFilterCount < 1) {
            throw new \InvalidArgumentException(
                "SEARCH_MAX_FILTER_COUNT must be positive, got {$maxFilterCount}"
            );
        }

        if ($plannerMinConfidence < 0.0 || $plannerMinConfidence > 1.0) {
            throw new \InvalidArgumentException(
                "SEARCH_PLANNER_MIN_CONFIDENCE must be between 0.0 and 1.0, got {$plannerMinConfidence}"
            );
        }

        if ($plannerRateLimit < 0) {
            throw new \InvalidArgumentException(
                "SEARCH_PLANNER_RATE_LIMIT must be 0 (no limit) or positive, got {$plannerRateLimit}"
            );
        }

        return new self(
            defaultLimit: $defaultLimit,
            maxQueryLength: $maxQueryLength,
            maxFilterCount: $maxFilterCount,
            maxResultLimit: $maxResultLimit,
            plannerEnabled: $plannerEnabled,
            plannerTimeoutMs: $plannerTimeoutMs,
            plannerMinConfidence: $plannerMinConfidence,
            observabilityEnabled: $observabilityEnabled,
            plannerRateLimit: $plannerRateLimit,
        );
    }
}
