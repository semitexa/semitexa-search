<?php

declare(strict_types=1);

namespace Semitexa\Search\Domain\Enum;

enum SearchMatchStrategy: string
{
    case Exact = 'exact';
    case Prefix = 'prefix';
    case Contains = 'contains';
}
