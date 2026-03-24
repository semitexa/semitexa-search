<?php

declare(strict_types=1);

namespace Semitexa\Search\Tests\Backend\Orm;

use PHPUnit\Framework\TestCase;
use Semitexa\Search\Backend\Orm\OrmRankingStrategy;
use Semitexa\Search\Enum\SearchFieldType;
use Semitexa\Search\Index\SearchFieldDefinition;
use Semitexa\Search\Index\SearchIndexDefinition;
use Semitexa\Search\Value\SearchHit;

final class OrmRankingStrategyTest extends TestCase
{
    private OrmRankingStrategy $strategy;
    private SearchIndexDefinition $definition;

    protected function setUp(): void
    {
        $this->strategy = new OrmRankingStrategy();
        $this->definition = new SearchIndexDefinition(
            name: 'test',
            documentType: 'user',
            fields: [
                new SearchFieldDefinition(
                    name: 'name',
                    type: SearchFieldType::Contains,
                    searchable: true,
                    weight: 2.0,
                ),
                new SearchFieldDefinition(
                    name: 'email',
                    type: SearchFieldType::Contains,
                    searchable: true,
                    weight: 1.0,
                ),
            ],
        );
    }

    public function testExactMatchScoresHighest(): void
    {
        $row = ['name' => 'John', 'email' => 'john@test.com'];
        $score = $this->strategy->score($this->definition, $row, 'John');

        $this->assertGreaterThan(0, $score);
    }

    public function testNullQueryReturnsZero(): void
    {
        $row = ['name' => 'John', 'email' => 'john@test.com'];
        $score = $this->strategy->score($this->definition, $row, null);

        $this->assertSame(0.0, $score);
    }

    public function testExactMatchOutranksPrefix(): void
    {
        $exactRow = ['name' => 'john', 'email' => 'other@test.com'];
        $prefixRow = ['name' => 'john doe', 'email' => 'other@test.com'];

        $exactScore = $this->strategy->score($this->definition, $exactRow, 'john');
        $prefixScore = $this->strategy->score($this->definition, $prefixRow, 'john');

        $this->assertGreaterThan($prefixScore, $exactScore);
    }

    public function testPrefixMatchOutranksContains(): void
    {
        $prefixRow = ['name' => 'john doe', 'email' => 'other@test.com'];
        $containsRow = ['name' => 'mr john', 'email' => 'other@test.com'];

        $prefixScore = $this->strategy->score($this->definition, $prefixRow, 'john');
        $containsScore = $this->strategy->score($this->definition, $containsRow, 'john');

        $this->assertGreaterThan($containsScore, $prefixScore);
    }

    public function testWeightAffectsScore(): void
    {
        $nameMatchRow = ['name' => 'john', 'email' => 'other@test.com'];
        $emailMatchRow = ['name' => 'other', 'email' => 'john'];

        $nameScore = $this->strategy->score($this->definition, $nameMatchRow, 'john');
        $emailScore = $this->strategy->score($this->definition, $emailMatchRow, 'john');

        $this->assertGreaterThan($emailScore, $nameScore);
    }

    public function testSortByRelevance(): void
    {
        $hits = [
            new SearchHit('1', 'test', 'user', 1.0, []),
            new SearchHit('2', 'test', 'user', 3.0, []),
            new SearchHit('3', 'test', 'user', 2.0, []),
        ];

        $sorted = $this->strategy->sortByRelevance($hits);

        $this->assertSame('2', $sorted[0]->documentId);
        $this->assertSame('3', $sorted[1]->documentId);
        $this->assertSame('1', $sorted[2]->documentId);
    }

    public function testSortStabilityOnEqualScores(): void
    {
        $hits = [
            new SearchHit('b', 'test', 'user', 1.0, []),
            new SearchHit('a', 'test', 'user', 1.0, []),
        ];

        $sorted = $this->strategy->sortByRelevance($hits);

        $this->assertSame('a', $sorted[0]->documentId);
        $this->assertSame('b', $sorted[1]->documentId);
    }
}
