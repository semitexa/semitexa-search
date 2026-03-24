<?php

declare(strict_types=1);

namespace Semitexa\Search\Exception;

final class SearchValidationException extends \RuntimeException
{
    /** @var list<string> */
    private array $violations;

    /**
     * @param list<string> $violations
     */
    public function __construct(string $message, array $violations = [], ?\Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
        $this->violations = $violations;
    }

    /**
     * @return list<string>
     */
    public function getViolations(): array
    {
        return $this->violations;
    }

    /**
     * @param list<string> $violations
     */
    public static function fromViolations(array $violations): self
    {
        return new self(
            'Search validation failed: ' . implode('; ', $violations),
            $violations,
        );
    }
}
