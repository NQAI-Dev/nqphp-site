<?php

declare(strict_types=1);

namespace Nqphp\Core\Entity\Driver;

/**
 * Storage contract for EntityManager.
 *
 * Phase 2 #9 (custom ORM, not Doctrine) — drivers implement this
 * contract to plug different storage backends behind a single
 * EntityManager API. The framework ships two implementations:
 *
 *   - InMemoryDriver: array-backed, fast, ephemeral (good for tests)
 *   - SqliteDriver:   PDO-backed SQLite, persistent, the "real" prod
 *                     choice until a MySQL/Postgres driver lands
 *
 * Each method takes an `$entityName` string (the value of the
 * entity's #[Entity(name: ...)] attribute) so the driver can store
 * mixed entity types in the same backend.
 */
interface DriverInterface
{
    /**
     * Persist a new entity or update an existing one (keyed by id).
     * Returns the assigned id (for INSERTs) or the existing id (for UPDATEs).
     *
     * @param array<string, mixed> $data entity data, keyed by column name (snake_case).
     */
    public function persist(string $entityName, array $data): int;

    /**
     * Find one row matching all criteria. Returns the data array
     * (column → value) or null.
     *
     * Each criterion value can be either:
     *   - a scalar: WHERE col = :col
     *   - ['LIKE' => 'foo%']: WHERE col LIKE :col (operator is case-insensitive)
     *   - ['IN' => [1,2,3]]: WHERE col IN (?, ?, ?)
     *   - ['BETWEEN' => [10, 20]]: WHERE col BETWEEN ? AND ?
     *   - ['>=' => 5], ['!=' => 'foo']: comparison operators
     *
     * Plain scalars are equivalent to ['=' => $value].
     */
    public function findOneBy(string $entityName, array $criteria): ?array;

    /**
     * Find rows matching all criteria, with optional ordering / pagination.
     *
     * @param array<string, mixed>  $criteria see findOneBy() for value shapes
     * @param array<int, string>     $orderBy  (column => 'asc'|'desc')
     * @return list<array<string, mixed>> list of column → value rows
     */
    public function findBy(string $entityName, array $criteria, array $orderBy = [], ?int $limit = null, ?int $offset = null): array;

    /** @return list<array<string, mixed>> */
    public function findAll(string $entityName, array $orderBy = [], ?int $limit = null, ?int $offset = null): array;

    public function count(string $entityName, array $criteria = []): int;

    /** Delete a row by id. Returns true if something was deleted. */
    public function delete(string $entityName, int $id): bool;

    /**
     * Ensure the schema for $entityName exists in the backend.
     * Drivers should call this lazily (or eagerly during flush()) so
     * that fresh databases work without separate migration steps.
     *
     * @param array<string, array{name: string, type: string, nullable: bool, length: int|null}> $columns
     */
    public function ensureSchema(string $entityName, array $columns): void;
}
