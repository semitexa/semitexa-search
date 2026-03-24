<?php

declare(strict_types=1);

namespace Semitexa\Search\Enum;

enum SearchScope: string
{
    case Tenant = 'tenant';
    case Global = 'global';
}
