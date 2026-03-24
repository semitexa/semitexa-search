<?php

declare(strict_types=1);

namespace Semitexa\Search\Tests\Planner\Llm;

use PHPUnit\Framework\TestCase;
use Semitexa\Search\Contract\SearchPlannerPolicy;
use Semitexa\Search\Enum\SearchFieldType;
use Semitexa\Search\Index\SearchFieldDefinition;
use Semitexa\Search\Index\SearchIndexDefinition;
use Semitexa\Search\Planner\Llm\LlmPlannerSchemaValidator;

final class LlmPlannerSchemaValidatorTest extends TestCase
{
    private LlmPlannerSchemaValidator $validator;
    private SearchIndexDefinition $definition;
    private SearchPlannerPolicy $policy;

    protected function setUp(): void
    {
        $this->validator = new LlmPlannerSchemaValidator();
        $this->policy = new SearchPlannerPolicy();
        $this->definition = new SearchIndexDefinition(
            name: 'test',
            documentType: 'user',
            fields: [
                new SearchFieldDefinition(name: 'name', type: SearchFieldType::Contains, searchable: true),
                new SearchFieldDefinition(name: 'status', type: SearchFieldType::Enum, filterable: true),
                new SearchFieldDefinition(name: 'created_at', type: SearchFieldType::Date, sortable: true),
            ],
        );
    }

    public function testValidOutput(): void
    {
        $parsed = [
            'query' => 'john',
            'filters' => ['status' => 'active'],
            'sort' => [['field' => 'created_at', 'direction' => 'DESC']],
            'confidence' => 0.85,
            'warnings' => [],
        ];

        $violations = $this->validator->validate($parsed, $this->definition, $this->policy);
        $this->assertEmpty($violations);
    }

    public function testRejectUnknownField(): void
    {
        $parsed = [
            'filters' => ['nonexistent' => 'value'],
            'confidence' => 0.8,
        ];

        $violations = $this->validator->validate($parsed, $this->definition, $this->policy);
        $this->assertNotEmpty($violations);
        $this->assertStringContainsString('unknown field', $violations[0]);
    }

    public function testRejectNonFilterableField(): void
    {
        $parsed = [
            'filters' => ['name' => 'john'],
            'confidence' => 0.8,
        ];

        $violations = $this->validator->validate($parsed, $this->definition, $this->policy);
        $this->assertNotEmpty($violations);
        $this->assertStringContainsString('non-filterable', $violations[0]);
    }

    public function testRejectNonSortableField(): void
    {
        $parsed = [
            'sort' => [['field' => 'name', 'direction' => 'ASC']],
            'confidence' => 0.8,
        ];

        $violations = $this->validator->validate($parsed, $this->definition, $this->policy);
        $this->assertNotEmpty($violations);
        $this->assertStringContainsString('non-sortable', $violations[0]);
    }

    public function testRejectInvalidConfidence(): void
    {
        $parsed = [
            'confidence' => 1.5,
        ];

        $violations = $this->validator->validate($parsed, $this->definition, $this->policy);
        $this->assertNotEmpty($violations);
        $this->assertStringContainsString('confidence', $violations[0]);
    }

    public function testRejectInvalidSortDirection(): void
    {
        $parsed = [
            'sort' => [['field' => 'created_at', 'direction' => 'SIDEWAYS']],
            'confidence' => 0.8,
        ];

        $violations = $this->validator->validate($parsed, $this->definition, $this->policy);
        $this->assertNotEmpty($violations);
        $this->assertStringContainsString('sort direction', $violations[0]);
    }
}
