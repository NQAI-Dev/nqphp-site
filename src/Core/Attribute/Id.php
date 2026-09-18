<?php

declare(strict_types=1);

namespace Nqphp\Core\Attribute;

/**
 * Mark a property as the entity's primary key.
 *
 * Used by EntityManager + SQL drivers to:
 *   - know which property holds the row's identifier
 *   - decide INSERT vs UPDATE (no $id → INSERT; existing $id → UPDATE)
 *   - generate the WHERE clause for findOneBy/findBy
 *
 * Example (entity for an `users` table):
 *
 *   #[Entity(name: 'user')]
 *   final class User {
 *       #[Id]
 *       public ?int $id = null;
 *
 *       #[Column(name: 'email', type: 'string', length: 255)]
 *       public string $email = '';
 *   }
 *
 * Conventions:
 *   - exactly one #[Id] per entity (asserted by EntityManager)
 *   - $id must be nullable until persisted (so INSERT vs UPDATE
 *     can be distinguished at the EntityManager level)
 *   - integer or string types supported (string PKs for UUID/nanoid
 *     patterns come later)
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class Id
{
}
