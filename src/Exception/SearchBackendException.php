<?php

declare(strict_types=1);

namespace Semitexa\Search\Exception;

final class SearchBackendException extends \RuntimeException
{
    public function __construct(string $message, ?\Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }
}
