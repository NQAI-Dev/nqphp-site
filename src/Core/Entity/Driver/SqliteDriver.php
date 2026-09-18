<?php

declare(strict_types=1);

namespace Nqphp\Core\Entity\Driver;

use PDO;

/**
 * SQLite-backed Driver implementation — PDO + sqlite3, persistent.
 *
 * The path passed to the constructor becomes a single SQLite file;
 * `:memory:` makes the database in-memory only (useful for tests).
 *
 * Schema is created lazily via ensureSchema(): on the first persist()
 * for an entity-name, the driver creates the table if it doesn't
 * exist. Phase 3+ will add a real migration layer; for now the
 * driver generates schema from #[Column] metadata at runtime.
 */
final class SqliteDriver implements DriverInterface
{
    /** @var array<string, array<string, string>> entity-name → column-name → SQLite type */
    private array $schemas = [];

    public function __construct(private readonly PDO $pdo)
    {
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    }

    public function persist(string $entityName, array $data): int
    {
        $this->ensureTable($entityName);
        if (!isset($data['id'])) {
            $data['id'] = (int) $this->pdo->query(
                "SELECT COALESCE(MAX(id), 0) + 1 FROM `$entityName`"
            )->fetchColumn();
        }
        $id = (int) $data['id'];
        if ($this->findOneBy($entityName, ['id' => $id]) !== null) {
            $this->updateRow($entityName, $id, $data);
        } else {
            $this->insertRow($entityName, $data);
        }
        return $id;
    }

    public function findOneBy(string $entityName, array $criteria): ?array
    {
        $this->ensureTable($entityName);
        [$where, $params] = $this->buildWhere($criteria);
        $stmt = $this->pdo->prepare("SELECT * FROM `$entityName` $where LIMIT 1");
        $stmt->execute($params);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public function findBy(string $entityName, array $criteria, array $orderBy = [], ?int $limit = null, ?int $offset = null): array
    {
        $this->ensureTable($entityName);
        [$where, $params] = $this->buildWhere($criteria);
        $sql = "SELECT * FROM `$entityName` $where";
        if (\count($orderBy) > 0) {
            $clauses = [];
            foreach ($orderBy as $col => $dir) {
                $dirSafe = \strtolower($dir) === 'desc' ? 'DESC' : 'ASC';
                $clauses[] = "`$col` $dirSafe";
            }
            $sql .= ' ORDER BY ' . implode(', ', $clauses);
        }
        if ($limit !== null && $limit > 0) {
            $sql .= ' LIMIT ' . (int) $limit;
        }
        if ($offset !== null && $offset > 0) {
            $sql .= ' OFFSET ' . (int) $offset;
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function findAll(string $entityName, array $orderBy = [], ?int $limit = null, ?int $offset = null): array
    {
        return $this->findBy($entityName, [], $orderBy, $limit, $offset);
    }

    public function count(string $entityName, array $criteria = []): int
    {
        $this->ensureTable($entityName);
        [$where, $params] = $this->buildWhere($criteria);
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM `$entityName` $where");
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    public function delete(string $entityName, int $id): bool
    {
        $this->ensureTable($entityName);
        $stmt = $this->pdo->prepare("DELETE FROM `$entityName` WHERE id = :id");
        $stmt->execute(['id' => $id]);
        return $stmt->rowCount() > 0;
    }

    public function ensureSchema(string $entityName, array $columns): void
    {
        $this->schemas[$entityName] = [];
        foreach ($columns as $name => $meta) {
            $type = $this->sqliteType($meta['type'] ?? 'string');
            $nullable = ($meta['nullable'] ?? false) ? '' : ' NOT NULL';
            $unique = ($meta['unique'] ?? false) ? ' UNIQUE' : '';
            $default = $this->renderDefault($meta['default'] ?? null);
            $this->schemas[$entityName][$name] = $type . $nullable . $unique . $default;
        }
        // Drop and re-create is the simplest schema-bootstrap for now.
        // Real migrations come in a follow-up commit.
        $this->pdo->exec("DROP TABLE IF EXISTS `$entityName`");
        $cols = ['id INTEGER PRIMARY KEY AUTOINCREMENT'];
        foreach ($this->schemas[$entityName] as $name => $def) {
            if ($name === 'id') {
                continue;
            }
            // $def now includes "TYPE [NOT NULL] [UNIQUE]" — embedded space-separated
            // form is valid SQLite column-constraint syntax.
            $cols[] = "`$name` $def";
        }
        $this->pdo->exec("CREATE TABLE `$entityName` (" . implode(', ', $cols) . ')');
    }

    /**
     * Lazy schema creation — called on first persist for an entity-name.
     * Uses schemas cached from ensureSchema() if available.
     */
    private function ensureTable(string $entityName): void
    {
        $stmt = $this->pdo->query(
            "SELECT name FROM sqlite_master WHERE type='table' AND name=" . $this->pdo->quote($entityName)
        );
        if ($stmt->fetchColumn() === $entityName) {
            return;  // exists
        }
        // No schema registered → default id-only table (caller will
        // usually call ensureSchema() before persist for typed columns).
        $this->pdo->exec("CREATE TABLE IF NOT EXISTS `$entityName` (id INTEGER PRIMARY KEY AUTOINCREMENT)");
    }

    /**
     * @param array<string, mixed> $data
     */
    private function insertRow(string $entityName, array $data): void
    {
        $cols = array_keys($data);
        $placeholders = array_map(fn($c) => ":$c", $cols);
        $sql = "INSERT INTO `$entityName` (`" . implode('`, `', $cols) . "`) VALUES ("
             . implode(', ', $placeholders) . ')';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($data);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function updateRow(string $entityName, int $id, array $data): void
    {
        unset($data['id']);
        $assignments = array_map(fn($c) => "`$c` = :$c", array_keys($data));
        $sql = "UPDATE `$entityName` SET " . implode(', ', $assignments) . " WHERE id = :__id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($data + ['__id' => $id]);
    }

    /**
     * Build a WHERE clause from operator-aware criteria.
     *
     * Supported criterion value shapes (Phase 2 #10):
     *   scalar  → WHERE col = :col (default exact match)
     *   [op => v] where op is one of: =, !=, >, <, >=, <=, LIKE
     *   [IN => [...]] → WHERE col IN (:col_0, :col_1, ...)
     *   [BETWEEN => [min, max]] → WHERE col BETWEEN :col_min AND :col_max
     *
     * @param array<string, mixed> $criteria column → scalar | operator-array
     * @return array{0: string, 1: array<string, mixed>} [where-clause, params]
     */
    private function buildWhere(array $criteria): array
    {
        if (\count($criteria) === 0) {
            return ['', []];
        }
        $clauses = [];
        $params = [];
        $i = 0;
        foreach ($criteria as $col => $value) {
            $placeholder = $col . '_' . $i++;
            // Operator-array shape: [op => operand] or [IN => [...]] or [BETWEEN => [...]]
            if (\is_array($value) && \count($value) === 1) {
                $op = \strtoupper((string) \array_key_first($value));
                $operand = $value[$op];
                switch ($op) {
                    case 'IN':
                        if (!\is_array($operand)) {
                            throw new \InvalidArgumentException('IN requires an array operand');
                        }
                        $placeholders = [];
                        foreach ($operand as $idx => $item) {
                            $p = $placeholder . '_' . $idx;
                            $placeholders[] = ':' . $p;
                            $params[$p] = $item;
                        }
                        $clauses[] = "\`$col\` IN (" . \implode(', ', $placeholders) . ')';
                        continue 2;
                    case 'BETWEEN':
                        if (!\is_array($operand) || \count($operand) !== 2) {
                            throw new \InvalidArgumentException('BETWEEN requires a 2-element array operand');
                        }
                        $clauses[] = "\`$col\` BETWEEN :{$placeholder}_min AND :{$placeholder}_max";
                        $params[$placeholder . '_min'] = $operand[0];
                        $params[$placeholder . '_max'] = $operand[1];
                        continue 2;
                    default:
                        // Comparison / LIKE operator
                        $clauses[] = "\`$col\` $op :$placeholder";
                        $params[$placeholder] = $operand;
                        continue 2;
                }
            }
            // Scalar shape: exact match.
            $clauses[] = "\`$col\` = :$placeholder";
            $params[$placeholder] = $value;
        }
        return ['WHERE ' . \implode(' AND ', $clauses), $params];
    }

    // Render a default value as SQLite DEFAULT clause fragment.
    // Returns '' (no DEFAULT), ' DEFAULT 42' (numeric),
    // " DEFAULT 'foo'" (string with SQL-standard escape), or
    // ' DEFAULT 1' (true) / ' DEFAULT 0' (false).
    // SQL-standard escape: a single quote inside a single-quoted
    // literal is rendered as two single quotes. This is the only
    // safe way to embed user-controlled strings in DDL since DDL
    // does not go through PDO prepared statements.
    private function renderDefault(mixed $default): string
    {
        if ($default === null) {
            return '';
        }
        if (is_bool($default)) {
            return ' DEFAULT ' . ($default ? '1' : '0');
        }
        if (is_int($default) || is_float($default)) {
            return ' DEFAULT ' . $default;
        }
        $escaped = str_replace("'", "''", (string) $default);
        return " DEFAULT '" . $escaped . "'";
    }

        /**
     * @return string SQLite column type for a logical type
     */
    private function sqliteType(string $logical): string
    {
        return match (\strtolower($logical)) {
            'string' => 'TEXT',
            'integer' => 'INTEGER',
            'float' => 'REAL',
            'boolean' => 'INTEGER',
            'datetime' => 'TEXT',
            'json' => 'TEXT',
            default => 'TEXT',
        };
    }
}
