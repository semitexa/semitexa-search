<?php

declare(strict_types=1);

namespace Semitexa\Search\Exception;

final class SearchIndexNotFoundException extends \RuntimeException
{
    public function __construct(string $indexName)
    {
        parent::__construct("Search index '{$indexName}' is not registered");
    }
}
