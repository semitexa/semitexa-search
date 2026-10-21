<?php

declare(strict_types=1);

namespace Semitexa\Search\Tests\Unit\Llm;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Semitexa\Search\Application\Service\SearchIndexRegistry;
use Semitexa\Search\Application\Service\SearchManager;
use Semitexa\Search\Configuration\SearchConfig;
use Semitexa\Search\Domain\Contract\SearchBackendInterface;
use Semitexa\Search\Domain\Contract\SearchQueryPlannerInterface;
use Semitexa\Search\Domain\Enum\SearchFieldType;
use Semitexa\Search\Domain\Enum\SearchScope;
use Semitexa\Search\Domain\Model\SearchFieldDefinition;
use Semitexa\Search\Domain\Model\SearchIndexDefinition;
use Semitexa\Search\Domain\Model\SearchPlannerPolicy;
use Semitexa\Search\Domain\Model\SearchPlanningResult;
use Semitexa\Search\Domain\Model\SearchRequest;
use Semitexa\Search\Domain\Model\SearchResult;

/** How SearchManager applies a usable plan. */
final class SearchManagerPlannerTest extends TestCase
{
    private ?SearchRequest $searched = null;

    #[Test]
    public function the_apps_own_filters_win_over_the_plans(): void
    {
        // The app's `status: published` was replaced by a model that read
        // "show drafts" in the query.
        $this->manager(new SearchPlanningResult(query: 'post', filters: ['status' => 'draft'], confidence: 0.9))
            ->search(new SearchRequest(index: 'posts', query: 'show drafts', filters: ['status' => 'published'], scope: SearchScope::Global));

        self::assertSame(['status' => 'published'], $this->searched?->filters);
        self::assertSame('post', $this->searched?->query);
    }

    #[Test]
    public function a_plan_with_no_query_searches_by_its_filters_alone(): void
    {
        $this->manager(new SearchPlanningResult(query: null, filters: ['status' => 'draft'], confidence: 0.9))
            ->search(new SearchRequest(index: 'posts', query: 'show me every draft', scope: SearchScope::Global));

        self::assertNull($this->searched?->query, 'the natural-language text matched nothing');
        self::assertSame(['status' => 'draft'], $this->searched?->filters);
    }

    #[Test]
    public function a_plan_with_no_query_and_no_filter_of_its_own_keeps_the_text(): void
    {
        // It dropped the text and added nothing: the search listed everything.
        $this->manager(new SearchPlanningResult(query: null, filters: ['status' => 'draft'], confidence: 0.9))
            ->search(new SearchRequest(index: 'posts', query: 'lamp', filters: ['status' => 'published'], scope: SearchScope::Global));

        self::assertSame('lamp', $this->searched?->query);
    }

    #[Test]
    public function the_apps_own_sort_wins_over_the_plans(): void
    {
        $this->manager(new SearchPlanningResult(query: 'post', sort: [new \Semitexa\Search\Domain\Model\SortClause('title', 'DESC')], confidence: 0.9))
            ->search(new SearchRequest(index: 'posts', query: 'x', sort: [new \Semitexa\Search\Domain\Model\SortClause('title', 'ASC')], scope: SearchScope::Global));

        self::assertSame('ASC', $this->searched?->sort[0]->direction);
    }

    #[Test]
    public function the_planner_is_never_asked_while_it_is_off(): void
    {
        $this->manager(null, plannerOn: false)->search(new SearchRequest(index: 'posts', query: 'anything', scope: SearchScope::Global));

        self::assertSame('anything', $this->searched?->query);
    }

    private function manager(?SearchPlanningResult $plan, bool $plannerOn = true): SearchManager
    {
        $registry = new SearchIndexRegistry();
        $registry->register(new SearchIndexDefinition(
            name: 'posts',
            documentType: 'post',
            fields: [
                new SearchFieldDefinition(name: 'title', type: SearchFieldType::Contains, searchable: true),
                new SearchFieldDefinition(name: 'status', type: SearchFieldType::Enum, filterable: true),
            ],
            scopeMode: SearchScope::Global,
            plannerEnabled: true,
        ));
        $test = $this;
        $backend = new class ($test) implements SearchBackendInterface {
            public function __construct(private SearchManagerPlannerTest $test) {}
            public function supports(SearchIndexDefinition $definition): bool { return true; }
            public function search(SearchIndexDefinition $definition, SearchRequest $request): SearchResult
            {
                $this->test->record($request);

                return new SearchResult(hits: [], total: 0);
            }
        };
        $planner = new class ($plan) implements SearchQueryPlannerInterface {
            public function __construct(private ?SearchPlanningResult $plan) {}
            public function plan(SearchIndexDefinition $definition, SearchRequest $request, SearchPlannerPolicy $policy): SearchPlanningResult
            {
                return $this->plan ?? throw new \LogicException('the planner was asked while it is off');
            }
        };
        $manager = new SearchManager();
        foreach (['registry' => $registry, 'backend' => $backend, 'planner' => $planner, 'config' => new SearchConfig(plannerEnabled: $plannerOn)] as $property => $value) {
            (new \ReflectionProperty($manager, $property))->setValue($manager, $value);
        }

        return $manager;
    }

    public function record(SearchRequest $request): void
    {
        $this->searched = $request;
    }
}
