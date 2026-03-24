<?php

declare(strict_types=1);

namespace Semitexa\Search\Enum;

enum SearchFieldType: string
{
    case Exact = 'exact';
    case Prefix = 'prefix';
    case Contains = 'contains';
    case Keyword = 'keyword';
    case Enum = 'enum';
    case Date = 'date';
    case Number = 'number';
}
