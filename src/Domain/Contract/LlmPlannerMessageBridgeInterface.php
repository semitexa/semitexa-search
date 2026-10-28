<?php

declare(strict_types=1);

namespace Semitexa\Search\Domain\Contract;

/**
 * A bridge that sends the visitor's query as the user message. In the system
 * prompt, a query such as "ignore the rules above" read to the model as one
 * more instruction from the application.
 */
interface LlmPlannerMessageBridgeInterface extends LlmPlannerBridgeInterface
{
    /** The system prompt and the user's query as separate messages; returns the raw answer. */
    public function executeMessage(string $systemPrompt, string $userMessage, int $timeoutMs): string;
}
