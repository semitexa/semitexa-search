<?php

declare(strict_types=1);

namespace Semitexa\Search\Planner\Llm;

interface LlmPlannerBridge
{
    /**
     * Execute a structured prompt against an LLM provider and return raw JSON response.
     */
    public function execute(string $prompt, int $timeoutMs): string;
}
