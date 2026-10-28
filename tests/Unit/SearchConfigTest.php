<?php

declare(strict_types=1);

namespace Semitexa\Search\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Semitexa\Search\Configuration\SearchConfig;

/**
 * SEARCH_PLANNER_RATE_LIMIT is the cap on paid model calls, and 0 turns it
 * off: a typo read as 0 turned it off without a word.
 */
final class SearchConfigTest extends TestCase
{
    private string|false $rateLimit;

    protected function setUp(): void
    {
        $this->rateLimit = getenv('SEARCH_PLANNER_RATE_LIMIT');
    }

    protected function tearDown(): void
    {
        putenv($this->rateLimit === false ? 'SEARCH_PLANNER_RATE_LIMIT' : 'SEARCH_PLANNER_RATE_LIMIT=' . $this->rateLimit);
    }

    #[Test]
    public function a_rate_limit_that_is_not_a_number_is_refused_not_read_as_no_limit(): void
    {
        putenv('SEARCH_PLANNER_RATE_LIMIT=20/min');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("SEARCH_PLANNER_RATE_LIMIT must be a whole number (0 = no limit), got '20/min'");

        SearchConfig::fromEnvironment();
    }

    #[Test]
    public function a_number_zero_and_a_blank_value_are_read_as_written(): void
    {
        putenv('SEARCH_PLANNER_RATE_LIMIT= 5 ');
        self::assertSame(5, SearchConfig::fromEnvironment()->plannerRateLimit);

        putenv('SEARCH_PLANNER_RATE_LIMIT=0');
        self::assertSame(0, SearchConfig::fromEnvironment()->plannerRateLimit, '0 is the documented "no limit"');

        putenv('SEARCH_PLANNER_RATE_LIMIT=');
        self::assertSame(20, SearchConfig::fromEnvironment()->plannerRateLimit, 'set but blank is the default');
    }
}
