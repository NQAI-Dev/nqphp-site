<?php

declare(strict_types=1);

namespace Nqphp\Core\Entity;

use Nqphp\Core\Attribute\Column;
use Nqphp\Core\Attribute\Entity;
use Nqphp\Core\Attribute\Id;
use Nqphp\Core\Entity\Driver\DriverInterface;
use Nqphp\Core\Entity\Driver\InMemoryDriver;
use ReflectionClass;
use ReflectionProperty;

/**
 * Custom ORM-style entity manager.
 *
 * Phase 2 #8 (custom ORM, not Doctrine).
 *
 * Public API mirrors Symfony's `EntityRepository` for familiarity:
 *   - findAll(EntityClass)
 *   - findBy(EntityClass, array $criteria, ?array $orderBy, ?int $limit, ?int $offset)
 *   - findOneBy(EntityClass, array $criteria)
 *   - count(EntityClass, array $criteria)
 *   - persist(entity) + flush() — Unit-of-Work pattern
 *
 * Storage is in-memory (rows keyed by entity-name + id). The next
 * slice (Phase 2 #9) adds a SQL driver that implements the same
 * interface over PDO.
 */
final class EntityManager
{
    public function __construct(
        private readonly EntityDiscoverer $discoverer,
        private readonly DriverInterface $driver = new InMemoryDriver(),
    ) {
    }

    /**
     * Persist a new or modified entity (queued for the next flush()).
     *
     * Phase 2 #8: actually persists immediately (the in-memory
     * driver has no flush barrier). The next slice will introduce a
     * real flush that batches SQL writes.
     *
     * @template T of object
     * @param T $entity
     * @return T
     */
    public function persist(object $entity): object
    {
        $entityName = $this->entityNameFor($entity);
        $reflection = new ReflectionClass($entity);
        $idProp = $this->idPropertyFor($reflection);
        $idProp->setAccessible(true);
        if ($idProp->getValue($entity) === null) {
            $idProp->setValue($entity, $this->nextIds[$entityName] ??= 1);
        }
        $this->rows[$entityName][(int) $idProp->getValue($entity)] = $entity;
        return $entity;
    }

    /** No-op for now — in-memory driver persists immediately. */
    public function flush(): void
    {
    }

    /**
     * Delete an entity by its primary key.
     *
     * Returns true if the entity was found and deleted; false if no
     * row with that id existed (idempotent — second call on the same
     * entity returns false without raising).
     *
     * Phase 2 #11 — completes the persistence-side surface alongside
     * persist() + flush(). Cascade semantics are NOT included: deleting
     * a parent does not delete children. Callers needing cascade must
     * remove related entities explicitly before removing the parent.
     *
     * @param object $entity an entity with a non-null #[Id] property
     * @return bool true if a row was deleted, false otherwise
     */
    public function remove(object $entity): bool
    {
        $entityName = $this->entityNameFor($entity);
        $reflection = new ReflectionClass($entity);
        $idProp = $this->idPropertyFor($reflection);
        $idProp->setAccessible(true);
        $id = $idProp->getValue($entity);
        if ($id === null) {
            throw new \RuntimeException(sprintf(
                'Cannot remove %s: its #[Id] is null (entity was never persisted).',
                $entityName
            ));
        }
        return $this->driver->delete($entityName, (int) $id);
    }

    /** @return list<object> */
    public function findAll(string $entityClass): array
    {
        $name = $this->nameFor($entityClass);
        return array_values($this->rows[$name] ?? []);
    }

    /**
     * @return list<object>
     */
    public function findBy(string $entityClass, array $criteria, ?array $orderBy = null, ?int $limit = null, ?int $offset = null): array
    {
        $rows = $this->findAll($entityClass);
        $rows = $this->applyCriteria($rows, $criteria);
        if ($orderBy !== null) {
            $rows = $this->applyOrderBy($rows, $entityClass, $orderBy);
        }
        if ($offset !== null && $offset > 0) {
            $rows = \array_slice($rows, $offset);
        }
        if ($limit !== null && $limit > 0) {
            $rows = \array_slice($rows, 0, $limit);
        }
        return $rows;
    }

    /** @return object|null */
    public function findOneBy(string $entityClass, array $criteria): ?object
    {
        $matches = $this->findBy($entityClass, $criteria, null, 1);
        return $matches[0] ?? null;
    }

    public function count(string $entityClass, array $criteria = []): int
    {
        if (\count($criteria) === 0) {
            return \count($this->rows[$this->nameFor($entityClass)] ?? []);
        }
        return \count($this->applyCriteria($this->findAll($entityClass), $criteria));
    }

    /**
     * @param list<object> $rows
     * @param array<string, mixed> $criteria map of property-name → expected-value
     * @return list<object>
     */
    private function applyCriteria(array $rows, array $criteria): array
    {
        if (\count($criteria) === 0) {
            return $rows;
        }
        $filtered = [];
        foreach ($rows as $row) {
            foreach ($criteria as $property => $value) {
                $prop = $this->propertyFor(new ReflectionClass($row), $property);
                if ($prop === null) {
                    continue 2;  // unknown property in criteria → exclude
                }
                $prop->setAccessible(true);
                if ($prop->getValue($row) !== $value) {
                    continue 2;
                }
            }
            $filtered[] = $row;
        }
        return $filtered;
    }

    /**
     * @param list<object> $rows
     * @param array<int, string> $orderBy (field => 'asc'|'desc')
     * @return list<object>
     */
    private function applyOrderBy(array $rows, string $entityClass, array $orderBy): array
    {
        if (\count($orderBy) === 0) {
            return $rows;
        }
        // Stable sort by the first orderBy field; subsequent fields
        // break ties via a chained comparator. Phase 2 #9 will hand
        // the work to the SQL driver when one is in use.
        [$field, $direction] = [array_key_first($orderBy), $orderBy[array_key_first($orderBy)]];
        $sign = \strtolower($direction ?? 'asc') === 'desc' ? -1 : 1;
        usort($rows, function (object $a, object $b) use ($field, $sign): int {
            $pa = new ReflectionClass($a);
            $pb = new ReflectionClass($b);
            $propA = $this->propertyFor($pa, $field);
            $propB = $this->propertyFor($pb, $field);
            if ($propA === null || $propB === null) {
                return 0;
            }
            $propA->setAccessible(true);
            $propB->setAccessible(true);
            $va = $propA->getValue($a);
            $vb = $propB->getValue($b);
            return $va <=> $vb * $sign;
        });
        return $rows;
    }

    /**
     * @return ReflectionProperty|null
     */
    private function propertyFor(ReflectionClass $class, string $name): ?ReflectionProperty
    {
        try {
            return $class->getProperty($name);
        } catch (\ReflectionException) {
            return null;
        }
    }

    /**
     * @return ReflectionProperty
     */
    private function idPropertyFor(ReflectionClass $class): ReflectionProperty
    {
        $idProp = null;
        foreach ($class->getProperties() as $prop) {
            if (\count($prop->getAttributes(Id::class)) > 0) {
                if ($idProp !== null) {
                    throw new \RuntimeException(sprintf(
                        'Entity %s has multiple #[Id] properties; exactly one is required.',
                        $class->getName()
                    ));
                }
                $idProp = $prop;
            }
        }
        if ($idProp === null) {
            throw new \RuntimeException(sprintf(
                'Entity %s has no #[Id] property; mark the primary key field with #[Id].',
                $class->getName()
            ));
        }
        return $idProp;
    }

    /**
     * @return list<string> list of #[Column]-tagged property names
     */
    private function columnPropertiesFor(ReflectionClass $class): array
    {
        $cols = [];
        foreach ($class->getProperties() as $prop) {
            if (\count($prop->getAttributes(Column::class)) > 0 || \count($prop->getAttributes(Id::class)) > 0) {
                $cols[] = $prop->getName();
            }
        }
        return $cols;
    }

    /**
     * @return string
     */
    private function entityNameFor(object $entity): string
    {
        $reflection = new ReflectionClass($entity);
        $attrs = $reflection->getAttributes(Entity::class);
        if (\count($attrs) === 0) {
            throw new \RuntimeException(sprintf(
                '%s is not marked with #[Entity]',
                $reflection->getName()
            ));
        }
        /** @var Entity $entityAttr */
        $entityAttr = $attrs[0]->newInstance();
        // `$name` is the canonical handle (Entity::name) used for discover lookup.
        // `$tableName` is the physical SQL table name — defaults to $name when
        // the entity doesn't override `table` (Phase 2 #10+ extension).
        $name = $entityAttr->name;
        $tableName = $entityAttr->table ?? $entityAttr->name;
        if (!$this->discoverer->discover()->has($name)) {
            throw new \RuntimeException(sprintf(
                'Entity "%s" not discovered — check the file path under src/Feature/*/Entity/',
                $name
            ));
        }
        return $tableName;
    }

    /**
     * @return string entity name for a given class-string
     */
    private function nameFor(string $entityClass): string
    {
        $reflection = new ReflectionClass($entityClass);
        $attrs = $reflection->getAttributes(Entity::class);
        if (\count($attrs) === 0) {
            throw new \RuntimeException(sprintf(
                '%s is not marked with #[Entity]',
                $entityClass
            ));
        }
        /** @var Entity $entityAttr */
        $entityAttr = $attrs[0]->newInstance();
        return $entityAttr->name;
    }
}
