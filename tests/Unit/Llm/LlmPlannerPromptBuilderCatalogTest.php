<?php

declare(strict_types=1);

namespace Semitexa\Search\Tests\Unit\Llm;

use PHPUnit\Framework\TestCase;
use Semitexa\Prompt\Application\Service\PromptRegistry;
use Semitexa\Search\Application\Service\Llm\LlmPlannerPromptBuilder;
use Semitexa\Search\Application\Service\Prompt\SearchPlannerPrompt;
use Semitexa\Search\Domain\Enum\SearchFieldType;
use Semitexa\Search\Domain\Model\SearchFieldDefinition;
use Semitexa\Search\Domain\Model\SearchIndexDefinition;
use Semitexa\Search\Domain\Model\SearchPlannerPolicy;

/**
 * Byte-identity guard for the LlmPlannerPromptBuilder -> prompt-catalog
 * migration. The golden fixture was captured from the pre-migration builder; the
 * catalog template (with the five bound variables) must reproduce it exactly.
 */
final class LlmPlannerPromptBuilderCatalogTest extends TestCase
{
    public function testBuiltPromptIsByteIdenticalToLegacy(): void
    {
        $fields = [
            new SearchFieldDefinition('title', SearchFieldType::Contains, searchable: true),
            new SearchFieldDefinition('price', SearchFieldType::Exact, filterable: true, sortable: true),
        ];
        $definition = new SearchIndexDefinition('products', 'Product', $fields);

        $prompt = (new LlmPlannerPromptBuilder())->build($definition, 'cheap red shoes', new SearchPlannerPolicy());

        $golden = (string) file_get_contents(__DIR__ . '/fixtures/planner.golden.txt');
        self::assertSame($golden, $prompt);
    }

    public function testTemplateDeclaresItsFiveVariables(): void
    {
        $template = (new PromptRegistry())->buildFromClasses([SearchPlannerPrompt::class])['search.query-planner'];

        $vars = $template->variableNames();
        sort($vars);

        self::assertSame(['document_type', 'fields', 'index_name', 'operators', 'user_query'], $vars);
    }
}
