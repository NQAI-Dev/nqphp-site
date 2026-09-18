<?php

declare(strict_types=1);

namespace Nqphp\Core\Entity\Driver;

/**
 * In-memory Driver implementation — array-backed, ephemeral, fast.
 *
 * Good for tests, fixtures, and short-lived read-only contexts
 * (e.g. a CLI command that reads config from the database and exits).
 *
 * Data persists only as long as the Driver instance lives; once the
 * request ends, the next request starts with an empty store. Use
 * SqliteDriver when persistence across requests is needed.
 */
final class InMemoryDriver implements DriverInterface
{
    /** @var array<string, array<int, array<string, mixed>>> entity-name → id → row */
    private array $rows = [];

    /** @var array<string, int> entity-name → next-id counter */
    private array $nextIds = [];

    public function persist(string $entityName, array $data): int
    {
        if (!isset($data['id'])) {
            $data['id'] = $this->nextIds[$entityName] ??= 1;
        }
        $id = (int) $data['id'];
        $this->rows[$entityName][$id] = $data;
        return $id;
    }

    public function findOneBy(string $entityName, array $criteria): ?array
    {
        foreach ($this->rows[$entityName] ?? [] as $row) {
            if ($this->matches($row, $criteria)) {
                return $row;
            }
        }
        return null;
    }

    public function findBy(string $entityName, array $criteria, array $orderBy = [], ?int $limit = null, ?int $offset = null): array
    {
        $rows = array_values($this->rows[$entityName] ?? []);
        $rows = array_filter($rows, fn($row) => $this->matches($row, $criteria));
        if (\count($orderBy) > 0) {
            [$field, $direction] = [array_key_first($orderBy), $orderBy[array_key_first($orderBy)]];
            $sign = \strtolower($direction ?? 'asc') === 'desc' ? -1 : 1;
            usort($rows, function (array $a, array $b) use ($field, $sign): int {
                $va = $a[$field] ?? null;
                $vb = $b[$field] ?? null;
                return ($va <=> $vb) * $sign;
            });
        }
        if ($offset !== null && $offset > 0) {
            $rows = \array_slice($rows, $offset);
        }
        if ($limit !== null && $limit > 0) {
            $rows = \array_slice($rows, 0, $limit);
        }
        return array_values($rows);
    }

    public function findAll(string $entityName, array $orderBy = [], ?int $limit = null, ?int $offset = null): array
    {
        return $this->findBy($entityName, [], $orderBy, $limit, $offset);
    }

    public function count(string $entityName, array $criteria = []): int
    {
        if (\count($criteria) === 0) {
            return \count($this->rows[$entityName] ?? []);
        }
        $matches = array_filter(
            $this->rows[$entityName] ?? [],
            fn($row) => $this->matches($row, $criteria)
        );
        return \count($matches);
    }

    public function delete(string $entityName, int $id): bool
    {
        if (!isset($this->rows[$entityName][$id])) {
            return false;
        }
        unset($this->rows[$entityName][$id]);
        return true;
    }

    public function ensureSchema(string $entityName, array $columns): void
    {
        // In-memory driver has no schema — no-op. The schema metadata
        // is preserved in $columns argument for inspection but not
        // persisted across processes (because nothing is).
    }

    /**
     * Mirror of SqliteDriver's operator-aware criteria matching.
     *
     * Supported shapes (Phase 2 #10):
     *   scalar → exact ===
     *   [op => v] for op in =, !=, >, <, >=, <=, LIKE
     *   [IN => [...]] → in_array check
     *   [BETWEEN => [min, max]] → $val >= min && $val <= max
     *
     * @param array<string, mixed> $row
     * @param array<string, mixed> $criteria
     */
    private function matches(array $row, array $criteria): bool
    {
        foreach ($criteria as $key => $value) {
            $cell = $row[$key] ?? null;
            if (\is_array($value) && \count($value) === 1) {
                $op = \strtoupper((string) \array_key_first($value));
                $operand = $value[$op];
                switch ($op) {
                    case '=': if ($cell !== $operand) return false; break;
                    case '!=': if ($cell == $operand) return false; break;
                    case '>': if (!($cell > $operand)) return false; break;
                    case '<': if (!($cell < $operand)) return false; break;
                    case '>=': if (!($cell >= $operand)) return false; break;
                    case '<=': if (!($cell <= $operand)) return false; break;
                    case 'LIKE':
                        // SQL LIKE: % = any, _ = one. Convert to a tiny regex.
                        $regex = '';
                        $pattern = (string) $operand;
                        for ($i = 0; $i < \strlen($pattern); $i++) {
                            $c = $pattern[$i];
                            if ($c === '%') { $regex .= '.*'; }
                            elseif ($c === '_') { $regex .= '.'; }
                            else { $regex .= \preg_quote($c, '/'); }
                        }
                        if (!\preg_match('/' . $regex . '/', (string) $cell)) return false;
                        break;
                    case 'IN':
                        if (!\is_array($operand) || !\in_array($cell, $operand, false)) return false;
                        break;
                    case 'BETWEEN':
                        if (!\is_array($operand) || \count($operand) !== 2) return false;
                        if (!($cell >= $operand[0] && $cell <= $operand[1])) return false;
                        break;
                    default: return false;
                }
                continue;
            }
            if ($cell !== $value) return false;
        }
        return true;
    }
}
