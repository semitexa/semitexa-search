<?php

declare(strict_types=1);

namespace Semitexa\Search\Application\Service\Llm;

use Semitexa\Core\Attribute\SatisfiesServiceContract;
use Psr\Container\ContainerInterface;
use Semitexa\Core\Attribute\InjectAsReadonly;
use Semitexa\Search\Domain\Contract\LlmPlannerBridgeInterface;
use Semitexa\Search\Domain\Contract\LlmPlannerMessageBridgeInterface;

/**
 * The planner's way to a model: semitexa/llm's LlmProviderResolver, which
 * picks the provider from LLM_BACKEND. semitexa/llm is only suggested, so it
 * is looked up when used: without it, every plan falls back to the query as
 * typed. The call goes to the resolver's decider model with the planner's
 * timeout, no retries, a short answer and no thinking, where the provider
 * allows it: a full-size call per search took up to minutes and paid for it.
 */
#[SatisfiesServiceContract(of: LlmPlannerBridgeInterface::class)]
final class LlmProviderPlannerBridge implements LlmPlannerMessageBridgeInterface
{
    private const RESOLVER = 'Semitexa\\Llm\\Application\\Service\\LlmProviderResolver';
    private const REQUEST = 'Semitexa\\Llm\\Domain\\Model\\LlmRequest';

    #[InjectAsReadonly]
    protected ContainerInterface $container;

    public function execute(string $prompt, int $timeoutMs): string
    {
        return $this->executeMessage($prompt, 'Plan the search. Answer with the JSON object only.', $timeoutMs);
    }

    public function executeMessage(string $systemPrompt, string $userMessage, int $timeoutMs): string
    {
        if (!class_exists(self::RESOLVER) || !class_exists(self::REQUEST)) {
            throw new \RuntimeException('semitexa/llm is not installed: the search planner has no model to ask.');
        }
        $resolver = $this->container->get(self::RESOLVER);
        $request = self::REQUEST;
        $provider = method_exists($resolver, 'deciderProvider') ? $resolver->deciderProvider() : $resolver->provider();
        // A provider that cannot be held to the planner's timeout is not asked:
        // the local Ollama provider waited LLM_TIMEOUT × its retries (two
        // minutes by default) on every search.
        if (!method_exists($provider, 'withLimits')) {
            throw new \RuntimeException('The ' . $provider->name() . ' provider cannot be limited to the planner timeout: use a backend that can (LLM_BACKEND), or bind your own LlmPlannerBridgeInterface.');
        }
        $provider = $provider->withLimits(max(1, (int) ceil($timeoutMs / 1000)), 0, maxTokens: 512, thinking: false);
        $response = $provider->complete(new $request(
            systemPrompt: $systemPrompt,
            userMessage: $userMessage,
        ));
        if (!$response->success) {
            throw new \RuntimeException('The model call failed: ' . ($response->error ?? 'no reason given'));
        }

        return $response->content;
    }
}
