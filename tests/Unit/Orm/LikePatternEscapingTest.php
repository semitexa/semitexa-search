<?php

declare(strict_types=1);

namespace Semitexa\Search\Tests\Unit\Orm;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Semitexa\Search\Application\Service\Orm\OrmSearchTranslator;
use Semitexa\Search\Domain\Enum\SearchMatchStrategy;

/** User search text is literal: none of its characters may act as a LIKE wildcard or escape. */
final class LikePatternEscapingTest extends TestCase
{
    #[Test]
    public function a_backslash_in_the_query_cannot_unescape_a_percent(): void
    {
        // Under MySQL's default escape `\`, "\\\\%" means a literal backslash
        // then a literal percent — the user's text, nothing more.
        self::assertSame('%50\\\\\\%off%', $this->pattern(SearchMatchStrategy::Contains, '50\\%off'));
    }

    #[Test]
    public function wildcards_are_escaped_and_the_strategy_adds_its_own(): void
    {
        self::assertSame('a\\_b\\%%', $this->pattern(SearchMatchStrategy::Prefix, 'a_b%'));
        self::assertSame('plain', $this->pattern(SearchMatchStrategy::Exact, 'plain'));
    }

    private function pattern(SearchMatchStrategy $strategy, string $query): string
    {
        $translator = (new \ReflectionClass(OrmSearchTranslator::class))->newInstanceWithoutConstructor();

        /** @var string */
        return (new \ReflectionMethod(OrmSearchTranslator::class, 'buildLikePattern'))->invoke($translator, $strategy, $query);
    }
}
