<?php

declare(strict_types=1);

namespace Semitexa\Search\Application\Service\Orm;

use Semitexa\Orm\Adapter\DatabaseAdapterInterface;
use Semitexa\Orm\Adapter\SqlIdentifier;
use Semitexa\Orm\Application\Service\Uuid7;
use Semitexa\Search\Domain\Contract\GroupedLikeSearchQueryInterface;
use Semitexa\Search\Domain\Contract\OrmSearchQueryInterface;

/**
 * The shipped OrmSearchQueryInterface: parameterised SQL over one table.
 *
 * Every column is checked as a plain identifier and quoted, every value is
 * a bound parameter, and only the comparison operators the translator uses
 * are accepted. Immutable: each call returns a new query.
 */
final class TableSearchQuery implements OrmSearchQueryInterface, GroupedLikeSearchQueryInterface
{
    private const OPERATORS = ['=', '!=', '<>', '<', '<=', '>', '>=', 'LIKE'];

    /** @var list<array{string, string}> [AND, SQL condition] */
    private array $conditions = [];

    /** @var array<string, mixed> */
    private array $params = [];

    /** @var list<string> */
    private array $order = [];

    private ?int $limit = null;
    private int $offset = 0;

    /**
     * @param string $likeEscape the ESCAPE clause for this dialect (the translator escapes with a backslash; SQLite has no default escape character)
     * @param array<string, string> $columnFor a column the translator names (tenant_id) => the table's own
     * @param string|null $softDeleteColumn rows with a value there are deleted and never found
     */
    public function __construct(
        private readonly DatabaseAdapterInterface $adapter,
        private readonly string $table,
        private readonly string $likeEscape = '',
        private readonly array $columnFor = [],
        ?string $softDeleteColumn = null,
    ) {
        self::identifier($table);
        if ($softDeleteColumn !== null) {
            $this->conditions[] = ['AND', self::identifier($softDeleteColumn) . ' IS NULL'];
        }
    }

    public function where(string $column, string $operator, mixed $value): static
    {
        return $this->with('AND', $this->column($column) . ' ' . self::operator($operator) . ' ', $value);
    }

    /**
     * Refused: an OR here is OR-ed against everything before it, the tenant
     * and soft-delete conditions included, and lets other tenants' and
     * deleted rows through. Use whereAnyLike() for alternatives.
     */
    public function orWhere(string $column, string $operator, mixed $value): static
    {
        throw new \LogicException('TableSearchQuery has no orWhere(): it would bypass the tenant and soft-delete conditions. Use whereAnyLike().');
    }

    public function whereAnyLike(array $patternByColumn): static
    {
        if ($patternByColumn === []) {
            return clone $this;
        }
        $clone = clone $this;
        $parts = [];
        foreach ($patternByColumn as $column => $pattern) {
            $parts[] = $this->column($column) . ' LIKE ' . $clone->bind($pattern) . $this->likeEscape;
        }
        $clone->conditions[] = ['AND', '(' . implode(' OR ', $parts) . ')'];

        return $clone;
    }

    public function whereIn(string $column, array $values): static
    {
        $clone = clone $this;
        if ($values === []) {
            $clone->conditions[] = ['AND', '1 = 0'];

            return $clone;
        }
        $placeholders = array_map(fn (mixed $v): string => $clone->bind($v), array_values($values));
        $clone->conditions[] = ['AND', $this->column($column) . ' IN (' . implode(', ', $placeholders) . ')'];

        return $clone;
    }

    public function whereBetween(string $column, mixed $from, mixed $to): static
    {
        $clone = clone $this;
        $clone->conditions[] = ['AND', $this->column($column) . ' BETWEEN ' . $clone->bind($from) . ' AND ' . $clone->bind($to)];

        return $clone;
    }

    public function orderBy(string $column, string $direction = 'ASC'): static
    {
        $clone = clone $this;
        $clone->order[] = $this->column($column) . (strtoupper($direction) === 'DESC' ? ' DESC' : ' ASC');

        return $clone;
    }

    public function limit(int $limit): static
    {
        $clone = clone $this;
        $clone->limit = max(0, $limit);

        return $clone;
    }

    public function offset(int $offset): static
    {
        $clone = clone $this;
        $clone->offset = max(0, $offset);

        return $clone;
    }

    public function fetchAll(): array
    {
        $sql = 'SELECT * FROM ' . self::identifier($this->table) . $this->whereSql()
            . ($this->order !== [] ? ' ORDER BY ' . implode(', ', $this->order) : '');
        if ($this->limit !== null) {
            $sql .= ' LIMIT ' . $this->limit . ' OFFSET ' . $this->offset;
        }

        return array_map(static function (array $row): object {
            // A BINARY(16) UUID key came back as raw bytes: not a usable
            // document id, and not even valid UTF-8 for json_encode.
            foreach (['id', 'uuid'] as $key) {
                if (is_string($row[$key] ?? null) && strlen($row[$key]) === 16 && !mb_check_encoding($row[$key], 'UTF-8')) {
                    $row[$key] = Uuid7::fromBytes($row[$key]);
                }
            }

            return (object) $row;
        }, $this->adapter->execute($sql, $this->params)->rows);
    }

    public function count(): int
    {
        $row = $this->adapter->execute('SELECT COUNT(*) AS n FROM ' . self::identifier($this->table) . $this->whereSql(), $this->params)->rows[0] ?? null;

        return (int) ($row['n'] ?? 0);
    }

    private function with(string $glue, string $prefix, mixed $value): static
    {
        $clone = clone $this;
        $clone->conditions[] = [$glue, $prefix . $clone->bind($value)];

        return $clone;
    }

    private function bind(mixed $value): string
    {
        $name = 'p' . count($this->params);
        $this->params[$name] = is_bool($value) ? (int) $value : $value;

        return ':' . $name;
    }

    private function whereSql(): string
    {
        return $this->conditions === [] ? '' : ' WHERE ' . implode(' AND ', array_column($this->conditions, 1));
    }

    private function column(string $name): string
    {
        return self::identifier($this->columnFor[$name] ?? $name);
    }

    private static function identifier(string $name): string
    {
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $name) !== 1) {
            throw new \InvalidArgumentException("'{$name}' is not a plain column or table name.");
        }

        return SqlIdentifier::quote($name);
    }

    private static function operator(string $operator): string
    {
        $operator = strtoupper(trim($operator));
        if (!in_array($operator, self::OPERATORS, true)) {
            throw new \InvalidArgumentException("Operator '{$operator}' is not supported.");
        }

        return $operator;
    }
}
