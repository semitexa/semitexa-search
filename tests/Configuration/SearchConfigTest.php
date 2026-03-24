<?php

declare(strict_types=1);

namespace Semitexa\Search\Tests\Configuration;

use PHPUnit\Framework\TestCase;
use Semitexa\Search\Configuration\SearchConfig;

final class SearchConfigTest extends TestCase
{
    public function testDefaults(): void
    {
        $config = new SearchConfig();

        $this->assertSame(20, $config->defaultLimit);
        $this->assertSame(500, $config->maxQueryLength);
        $this->assertSame(20, $config->maxFilterCount);
        $this->assertSame(100, $config->maxResultLimit);
        $this->assertFalse($config->plannerEnabled);
        $this->assertSame(3000, $config->plannerTimeoutMs);
        $this->assertSame(0.5, $config->plannerMinConfidence);
        $this->assertTrue($config->observabilityEnabled);
    }

    public function testCustomValues(): void
    {
        $config = new SearchConfig(
            defaultLimit: 50,
            maxQueryLength: 1000,
            maxFilterCount: 10,
            maxResultLimit: 200,
            plannerEnabled: true,
            plannerTimeoutMs: 5000,
            plannerMinConfidence: 0.7,
            observabilityEnabled: false,
        );

        $this->assertSame(50, $config->defaultLimit);
        $this->assertTrue($config->plannerEnabled);
        $this->assertSame(0.7, $config->plannerMinConfidence);
    }
}
