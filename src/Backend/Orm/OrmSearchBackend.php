<?php

declare(strict_types=1);

namespace Semitexa\Search\Backend\Orm;

use Semitexa\Core\Attributes\SatisfiesServiceContract;
use Semitexa\Search\Contract\SearchBackendInterface;
use Semitexa\Search\Exception\SearchBackendException;
use Semitexa\Search\Index\SearchIndexDefinition;
use Semitexa\Search\Value\SearchHit;
use Semitexa\Search\Value\SearchRequest;
use Semitexa\Search\Value\SearchResult;

#[SatisfiesServiceContract(of: SearchBackendInterface::class)]
final class OrmSearchBackend implements SearchBackendInterface
{
    private OrmSearchTranslator $translator;
    private OrmRankingStrategy $rankingStrategy;

    public function __construct(
        private ?OrmSearchQueryFactoryInterface $queryFactory = null,
    ) {
        $this->translator = new OrmSearchTranslator();
        $this->rankingStrategy = new OrmRankingStrategy();
    }

    public function supports(SearchIndexDefinition $definition): bool
    {
        return $definition->backend === 'orm' && $this->queryFactory !== null;
    }

    public function search(SearchIndexDefinition $definition, SearchRequest $request): SearchResult
    {
        $startTime = hrtime(true);

        try {
            if ($this->queryFactory === null) {
                throw new SearchBackendException(
                    "ORM backend is not available for index '{$definition->name}' because no query factory is configured.",
                );
            }

            $query = $this->queryFactory->createQuery($definition);
            $query = $this->translator->apply($query, $definition, $request);

            $rows = $query->fetchAll();
            $total = $query->count();

            $hits = $this->mapHits($definition, $rows, $request->query);

            if ($request->query !== null) {
                $hits = $this->rankingStrategy->sortByRelevance($hits);
            }

            $elapsedMs = (hrtime(true) - $startTime) / 1_000_000;

            return new SearchResult(
                hits: $hits,
                total: $total,
                partial: count($rows) < $total,
                metadata: [
                    'backend' => 'orm',
                    'index' => $definition->name,
                    'latency_ms' => round($elapsedMs, 2),
                    'query_used' => $request->query,
                    'filter_count' => count($request->filters),
                    'ranking_mode' => $request->query !== null ? 'relevance' : 'default',
                ],
            );
        } catch (\Throwable $e) {
            if ($e instanceof SearchBackendException) {
                throw $e;
            }

            throw new SearchBackendException(
                "ORM backend search failed for index '{$definition->name}': {$e->getMessage()}",
                $e,
            );
        }
    }

    /**
     * @param array<object> $rows
     * @return list<SearchHit>
     */
    private function mapHits(
        SearchIndexDefinition $definition,
        array $rows,
        ?string $query,
    ): array {
        $hits = [];

        foreach ($rows as $row) {
            $rowArray = $this->objectToArray($row);
            $score = $this->rankingStrategy->score($definition, $rowArray, $query);

            $fields = [];
            foreach ($definition->fields as $field) {
                $column = $field->resolvedColumn();
                if (array_key_exists($column, $rowArray)) {
                    $fields[$field->name] = $rowArray[$column];
                }
            }

            $documentId = (string) ($rowArray['id'] ?? $rowArray['uuid'] ?? spl_object_id($row));

            $hits[] = new SearchHit(
                documentId: $documentId,
                index: $definition->name,
                type: $definition->documentType,
                score: $score,
                fields: $fields,
            );
        }

        return $hits;
    }

    /**
     * @return array<string, mixed>
     */
    private function objectToArray(object $row): array
    {
        if (method_exists($row, 'toArray')) {
            return $row->toArray();
        }

        return (array) $row;
    }
}
