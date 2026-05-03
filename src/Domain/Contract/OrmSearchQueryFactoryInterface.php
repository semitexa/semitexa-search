<?php

declare(strict_types=1);

namespace Semitexa\Search\Domain\Contract;

use Semitexa\Search\Domain\Model\SearchIndexDefinition;

interface OrmSearchQueryFactoryInterface
{
    public function createQuery(SearchIndexDefinition $definition): OrmSearchQueryInterface;
}
