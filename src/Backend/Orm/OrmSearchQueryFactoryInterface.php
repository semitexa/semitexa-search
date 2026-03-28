<?php

declare(strict_types=1);

namespace Semitexa\Search\Backend\Orm;

use Semitexa\Search\Index\SearchIndexDefinition;

interface OrmSearchQueryFactoryInterface
{
    public function createQuery(SearchIndexDefinition $definition): OrmSearchQueryInterface;
}
