<?php

declare(strict_types=1);

namespace Semitexa\Search\Backend\Orm;

use Semitexa\Core\Attribute\SatisfiesServiceContract;
use Semitexa\Search\Contract\SearchBackendInterface;
use Semitexa\Search\Exception\SearchBackendException;
use Semitexa\Search\Index\SearchIndexDefinition;
use Semitexa\Search\Value\SearchHit;
use Semitexa\Search\Value\SearchRequest;
use Semitexa\Search\Value\SearchResult;

#[SatisfiesServiceContract(of: SearchBackendInterface::class)]
final class OrmSearchBackend implements SearchBackendInterface
{
    protected ?OrmSearchQueryFactoryInterface $queryFactory = null;
    private ?OrmSearchTranslator $translator = null;
    private ?OrmRankingStrategy $rankingStrategy = null;

    public function supports(SearchIndexDefinition $definition): bool
    {
        return $definition->backend === 'orm' && $this->queryFactory !== null;
    }

    public function search(SearchIndexDefinition $definition, SearchRequest $request): SearchResult
    {
        $translator = $this->translator ??= new OrmSearchTranslator();
        $rankingStrategy = $this->rankingStrategy ??= new OrmRankingStrategy();
        $startTime = hrtime(true);

        try {
            if ($this->queryFactory === null) {
                throw new SearchBackendException(
                    "ORM backend is not available for index '{$definition->name}' because no query factory is configured.",
                );
            }

            $query = $this->queryFactory->createQuery($definition);
            $query = $translator->apply($query, $definition, $request);

            $rows = $query->fetchAll();
            $total = $query->count();

            $hits = $this->mapHits($definition, $rows, $request->query, $rankingStrategy);

            if ($request->query !== null) {
                $hits = $rankingStrategy->sortByRelevance($hits);
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
        OrmRankingStrategy $rankingStrategy,
    ): array {
        $hits = [];

        foreach ($rows as $row) {
            $rowArray = $this->objectToArray($row);
            $score = $rankingStrategy->score($definition, $rowArray, $query);

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
