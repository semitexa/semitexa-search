<?php

declare(strict_types=1);

namespace Semitexa\Search\Enum;

enum SearchMatchStrategy: string
{
    case Exact = 'exact';
    case Prefix = 'prefix';
    case Contains = 'contains';
}
