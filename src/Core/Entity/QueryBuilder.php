<?php

declare(strict_types=1);

namespace Nqphp\Core\Entity;

use Nqphp\Core\Entity\Driver\DriverInterface;

/**
 * Fluent query builder for the custom ORM layer.
 *
 * Wraps the raw criteria / orderBy / pagination arrays accepted by
 * DriverInterface into a readable, chainable API. Queries are lazy —
 * nothing hits the driver until you call fetch(), fetchOne(), or count().
 *
 * Usage example:
 *
 *   $results = QueryBuilder::for('article', $driver)
 *       ->where('status', 'published')
 *       ->where('views', ['>=', 100])
 *       ->orderBy('created_at', 'desc')
 *       ->limit(10)
 *       ->offset(20)
 *       ->fetch();
 *
 * Operator shorthand in where():
 *   - scalar value       → exact match (=)
 *   - ['op' => value]    → comparison: =, !=, <, >, <=, >=, LIKE
 *   - ['IN' => [...]]    → IN list
 *   - ['BETWEEN' => [a, b]] → BETWEEN range
 *
 * The builder is immutable after each `with*` method (clone-based),
 * so the same base query can be branched into multiple variants:
 *
 *   $base = QueryBuilder::for('order', $driver)->where('user_id', 42);
 *   $open   = $base->where('status', 'open')->fetch();
 *   $closed = $base->where('status', 'closed')->fetch();
 */
final class QueryBuilder
{
    /** @var array<string, mixed> */
    private array $criteria = [];

    /** @var array<string, string> column → 'asc'|'desc' */
    private array $orderBy = [];

    private ?int $limit  = null;
    private ?int $offset = null;

    private function __construct(
        private readonly string          $entityName,
        private readonly DriverInterface $driver,
    ) {
    }

    /**
     * Start building a query for $entityName using $driver.
     */
    public static function for(string $entityName, DriverInterface $driver): self
    {
        return new self($entityName, $driver);
    }

    // -------------------------------------------------------------------------
    // Fluent constraint methods (each returns a clone — immutable builder)
    // -------------------------------------------------------------------------

    /**
     * Add a WHERE criterion.
     *
     * $value accepts every shape that DriverInterface supports:
     *   - scalar              → exact match
     *   - ['LIKE' => 'foo%']  → LIKE
     *   - ['IN'   => [1,2,3]] → IN
     *   - ['BETWEEN' => [a,b]]→ BETWEEN
     *   - ['>=' => 5]         → comparison operator
     *
     * Multiple calls to where() are combined with AND.
     */
    public function where(string $column, mixed $value): self
    {
        $clone = clone $this;
        $clone->criteria[$column] = $value;
        return $clone;
    }

    /**
     * Shorthand for where($col, ['IN' => $values]).
     *
     * @param list<mixed> $values
     */
    public function whereIn(string $column, array $values): self
    {
        return $this->where($column, ['IN' => $values]);
    }

    /**
     * Shorthand for where($col, ['BETWEEN' => [$min, $max]]).
     */
    public function whereBetween(string $column, mixed $min, mixed $max): self
    {
        return $this->where($column, ['BETWEEN' => [$min, $max]]);
    }

    /**
     * Shorthand for where($col, ['LIKE' => $pattern]).
     */
    public function whereLike(string $column, string $pattern): self
    {
        return $this->where($column, ['LIKE' => $pattern]);
    }

    /**
     * Add an ORDER BY clause.
     *
     * Each call appends a new sort key; earlier keys take priority.
     * $direction is case-insensitive; anything other than 'desc' is
     * treated as 'asc'.
     */
    public function orderBy(string $column, string $direction = 'asc'): self
    {
        $clone = clone $this;
        $clone->orderBy[$column] = \strtolower($direction) === 'desc' ? 'desc' : 'asc';
        return $clone;
    }

    /**
     * Set the maximum number of rows to return.
     */
    public function limit(int $limit): self
    {
        $clone         = clone $this;
        $clone->limit  = $limit;
        return $clone;
    }

    /**
     * Skip the first $offset rows before returning results.
     */
    public function offset(int $offset): self
    {
        $clone         = clone $this;
        $clone->offset = $offset;
        return $clone;
    }

    /**
     * Convenience: set limit + offset for a one-based page number.
     *
     *   ->paginate(page: 3, perPage: 25) → offset 50, limit 25
     */
    public function paginate(int $page, int $perPage): self
    {
        return $this
            ->limit($perPage)
            ->offset(($page - 1) * $perPage);
    }

    // -------------------------------------------------------------------------
    // Terminal methods — execute against the driver
    // -------------------------------------------------------------------------

    /**
     * Fetch all matching rows.
     *
     * @return list<array<string, mixed>>
     */
    public function fetch(): array
    {
        return $this->driver->findBy(
            $this->entityName,
            $this->criteria,
            $this->orderBy,
            $this->limit,
            $this->offset,
        );
    }

    /**
     * Fetch at most one matching row, or null.
     *
     * @return array<string, mixed>|null
     */
    public function fetchOne(): ?array
    {
        $rows = $this->driver->findBy(
            $this->entityName,
            $this->criteria,
            $this->orderBy,
            1,
            $this->offset,
        );
        return $rows[0] ?? null;
    }

    /**
     * Count matching rows (ignores limit/offset — counts the full set).
     */
    public function count(): int
    {
        return $this->driver->count($this->entityName, $this->criteria);
    }

    /**
     * Return true when at least one matching row exists.
     */
    public function exists(): bool
    {
        return $this->count() > 0;
    }

    // -------------------------------------------------------------------------
    // Introspection (useful for debugging / logging)
    // -------------------------------------------------------------------------

    /** @return array<string, mixed> */
    public function getCriteria(): array
    {
        return $this->criteria;
    }

    /** @return array<string, string> */
    public function getOrderBy(): array
    {
        return $this->orderBy;
    }

    public function getLimit(): ?int
    {
        return $this->limit;
    }

    public function getOffset(): ?int
    {
        return $this->offset;
    }
}
