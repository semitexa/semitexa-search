<?php

declare(strict_types=1);

namespace Semitexa\Search\Backend\Orm;

use Semitexa\Orm\Query\SelectQuery;
use Semitexa\Search\Index\SearchIndexDefinition;

interface OrmSearchQueryFactoryInterface
{
    public function createQuery(SearchIndexDefinition $definition): SelectQuery;
}
