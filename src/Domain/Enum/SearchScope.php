<?php

declare(strict_types=1);

namespace Semitexa\Search\Domain\Enum;

enum SearchScope: string
{
    case Tenant = 'tenant';
    case Global = 'global';
}
