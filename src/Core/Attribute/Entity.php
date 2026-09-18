<?php

declare(strict_types=1);

namespace Nqphp\Core\Attribute;

/**
 * Mark a class as a domain entity (data shape) discoverable by
 * EntityDiscoverer.
 *
 * Entities are auto-discovered from:
 *   - src/Feature/{Name}/Entity/{File}.php   (feature-scoped entities)
 *   - src/Core/Entity/{File}.php             (framework, future)
 *
 * The class is expected to be a POPO with public properties (the
 * column shape). The EntityManager reads/writes them via reflection;
 * no Doctrine or DB required. The whole point of this attribute is
 * to give features a typed data layer they can swap out later
 * (swap `DriverInterface` impl → MySQL/Postgres) without changing
 * entity classes.
 *
 * Example:
 *   #[Entity(name: 'user')]
 *   final class User {
 *       public ?int $id = null;
 *       public string $email;
 *       public string $name;
 *   }
 *
 * The `name` is the canonical handle used by EntityManager CRUD ops.
 * Convention: lowercase singular noun (`user`, `post`, `comment`).
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final class Entity
{
    /**
     * @param string      $name  Canonical handle used by application code
     *                           and EntityDiscoverer map keys (e.g. 'user').
     *                           Required — every entity must have one.
     * @param string|null $table Physical SQL table name (default: same as $name).
     *                           Override for legacy DB integration where the
     *                           canonical handle differs from the physical
     *                           table (e.g. Entity class `User` mapping to an
     *                           existing `app_users` table), or for
     *                           multi-tenant schemas where a single entity
     *                           class targets per-tenant tables at runtime.
     *
     *                           When null, SqliteDriver uses $name verbatim —
     *                           no behavior change for existing entities.
     */
    public function __construct(
        public readonly string $name,
        public readonly ?string $table = null,
    ) {
    }
}
