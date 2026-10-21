<?php

declare(strict_types=1);

namespace Semitexa\Search\Tests\Unit\Llm;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Semitexa\Search\Application\Service\Llm\LlmSearchQueryPlanner;
use Semitexa\Search\Domain\Contract\LlmPlannerBridgeInterface;
use Semitexa\Search\Domain\Enum\SearchFieldType;
use Semitexa\Search\Domain\Model\SearchFieldDefinition;
use Semitexa\Search\Domain\Model\SearchIndexDefinition;
use Semitexa\Search\Domain\Model\SearchPlannerPolicy;
use Semitexa\Search\Domain\Model\SearchRequest;

/**
 * The package shipped the prompt, the schema check and the bridge contract,
 * but SearchManager always used the no-op planner: the planner the README
 * promises never ran.
 */
final class LlmSearchQueryPlannerTest extends TestCase
{
    #[Test]
    public function a_model_answer_becomes_query_filters_and_sort(): void
    {
        $result = $this->planner("Sure:\n```json\n{\"query\": \"lamp\", \"filters\": {\"status\": \"active\"}, \"sort\": [{\"field\": \"price\", \"direction\": \"asc\"}], \"confidence\": 0.9}\n```")
            ->plan($this->definition(), new SearchRequest(index: 'products', query: 'cheapest active lamps'), new SearchPlannerPolicy());

        self::assertSame('lamp', $result->query);
        self::assertSame(['status' => 'active'], $result->filters);
        self::assertSame('price', $result->sort[0]->field);
        self::assertSame('ASC', $result->sort[0]->direction);
        self::assertTrue($result->isUsable(0.5));
    }

    #[Test]
    public function an_answer_naming_a_field_the_index_lacks_is_not_used(): void
    {
        $result = $this->planner('{"query": "lamp", "filters": {"secret_cost": 5}, "confidence": 0.95}')
            ->plan($this->definition(), new SearchRequest(index: 'products', query: 'lamp'), new SearchPlannerPolicy());

        self::assertFalse($result->isUsable(0.5));
        self::assertSame('lamp', $result->query, 'the query as typed');
        self::assertNotSame([], $result->warnings);
    }

    #[Test]
    public function a_filter_value_of_the_wrong_shape_is_refused_not_applied(): void
    {
        // A nested list bound "Array"; a range on an enum became status >= 'a'.
        foreach (['{"filters": {"price": [[1, 2]]}, "confidence": 0.9}', '{"filters": {"status": {"from": "a"}}, "confidence": 0.9}'] as $answer) {
            $result = $this->planner($answer)->plan($this->definition(), new SearchRequest(index: 'products', query: 'lamp'), new SearchPlannerPolicy());

            self::assertFalse($result->isUsable(0.5), $answer);
            self::assertSame([], $result->filters);
        }
    }

    #[Test]
    public function no_model_or_no_json_falls_back_to_the_query_as_typed(): void
    {
        foreach (['not json at all', new \RuntimeException('semitexa/llm is not installed')] as $answer) {
            $result = $this->planner($answer)->plan($this->definition(), new SearchRequest(index: 'products', query: 'desk lamp'), new SearchPlannerPolicy());

            self::assertFalse($result->isUsable(0.0));
            self::assertSame('desk lamp', $result->query);
            self::assertFalse($result->trace?->wasUsed);
        }
    }

    #[Test]
    public function the_shipped_bridge_asks_the_provider_semitexa_llm_resolves(): void
    {
        $resolverClass = 'Semitexa\\Llm\\Application\\Service\\LlmProviderResolver';
        if (!class_exists($resolverClass)) {
            $this->expectExceptionMessage('semitexa/llm is not installed');
            (new \Semitexa\Search\Application\Service\Llm\LlmProviderPlannerBridge())->execute('prompt', 100);

            return;
        }
        // semitexa/llm's own test seam: no network.
        $resolver = \Semitexa\Core\Container\ContainerFactory::get()->get($resolverClass);
        $asked = null;
        $limits = null;
        $resolver->withProvider(new class ($asked, $limits) implements \Semitexa\Llm\Domain\Contract\LlmProviderInterface {
            public function __construct(private mixed &$asked, private mixed &$limits) {}
            public function withLimits(int $timeoutSeconds, int $maxRetries, ?int $maxTokens = null, bool $thinking = true): self
            {
                $this->limits = [$timeoutSeconds, $maxRetries, $maxTokens, $thinking];

                return $this;
            }
            public function name(): string { return 'fake'; }
            public function baseUrl(): string { return ''; }
            public function model(): string { return 'fake'; }
            public function healthCheck(): bool { return true; }
            public function complete(\Semitexa\Llm\Domain\Model\LlmRequest $request): \Semitexa\Llm\Domain\Model\LlmResponse
            {
                $this->asked = $request->systemPrompt;

                return new \Semitexa\Llm\Domain\Model\LlmResponse(content: '{"query": "lamp", "confidence": 0.8}', success: true);
            }
        });
        try {
            $bridge = new \Semitexa\Search\Application\Service\Llm\LlmProviderPlannerBridge();
            (new \ReflectionProperty($bridge, 'container'))->setValue($bridge, \Semitexa\Core\Container\ContainerFactory::get());
            $answer = $bridge->execute('the planner prompt', 2500);
        } finally {
            (new \ReflectionProperty($resolver, 'override'))->setValue($resolver, null);
        }

        self::assertSame('{"query": "lamp", "confidence": 0.8}', $answer);
        self::assertSame('the planner prompt', $asked);
        self::assertSame([3, 0, 512, false], $limits, 'the planner timeout in seconds, no retries, a short answer, no thinking');
    }

    #[Test]
    public function a_provider_that_cannot_be_limited_is_not_asked(): void
    {
        // The local Ollama provider waited LLM_TIMEOUT times its retries.
        $resolverClass = 'Semitexa\\Llm\\Application\\Service\\LlmProviderResolver';
        if (!class_exists($resolverClass)) {
            self::markTestSkipped('needs semitexa/llm');
        }
        $resolver = \Semitexa\Core\Container\ContainerFactory::get()->get($resolverClass);
        $resolver->withProvider(new class implements \Semitexa\Llm\Domain\Contract\LlmProviderInterface {
            public function name(): string { return 'unlimited'; }
            public function baseUrl(): string { return ''; }
            public function model(): string { return 'x'; }
            public function healthCheck(): bool { return true; }
            public function complete(\Semitexa\Llm\Domain\Model\LlmRequest $request): \Semitexa\Llm\Domain\Model\LlmResponse
            {
                throw new \LogicException('asked');
            }
        });
        try {
            $bridge = new \Semitexa\Search\Application\Service\Llm\LlmProviderPlannerBridge();
            (new \ReflectionProperty($bridge, 'container'))->setValue($bridge, \Semitexa\Core\Container\ContainerFactory::get());
            $this->expectExceptionMessage('cannot be limited');
            $bridge->execute('prompt', 1000);
        } finally {
            (new \ReflectionProperty($resolver, 'override'))->setValue($resolver, null);
        }
    }

    private function planner(string|\Throwable $answer): LlmSearchQueryPlanner
    {
        $planner = new LlmSearchQueryPlanner();
        (new \ReflectionProperty($planner, 'bridge'))->setValue($planner, new class ($answer) implements LlmPlannerBridgeInterface {
            public function __construct(private string|\Throwable $answer) {}

            public function execute(string $prompt, int $timeoutMs): string
            {
                if ($this->answer instanceof \Throwable) {
                    throw $this->answer;
                }

                return $this->answer;
            }
        });

        return $planner;
    }

    private function definition(): SearchIndexDefinition
    {
        return new SearchIndexDefinition(
            name: 'products',
            documentType: 'product',
            fields: [
                new SearchFieldDefinition(name: 'title', type: SearchFieldType::Contains, searchable: true),
                new SearchFieldDefinition(name: 'status', type: SearchFieldType::Enum, filterable: true),
                new SearchFieldDefinition(name: 'price', type: SearchFieldType::Number, filterable: true, sortable: true),
            ],
            plannerEnabled: true,
        );
    }
}
