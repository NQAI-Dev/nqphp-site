<?php

declare(strict_types=1);

namespace Nqphp\Core\Attribute;

/**
 * Map a property to a database column.
 *
 * Used by EntityManager + future SQL drivers to:
 *   - know which property maps to which column name (default: snake_case
 *     of the property name — `email` → `email`, `createdAt` → `created_at`)
 *   - know the column type for schema generation (default: inferred
 *     from the PHP property type via reflection)
 *   - know whether the column is nullable
 *   - know the column length (for VARCHAR/CHAR)
 *
 * Example:
 *
 *   #[Column(name: 'email', type: 'string', length: 255)]
 *   public string $email = '';
 *
 *   #[Column(type: 'datetime')]
 *   public DateTimeImmutable $createdAt;
 *
 * Conventions:
 *   - properties without #[Column] are still part of the entity but
 *     not persisted (computed / transient)
 *   - the framework's reflection-based hydrator reads public properties
 *     directly; #[Column] is metadata for SQL generation
 *
 * Phase 2 #8 (custom ORM, not Doctrine). Phase 2 #9 will add the
 * SQL driver that actually uses this metadata.
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class Column
{
    /**
     * @param string|null $name     Column name (default: snake_case of the property name).
     * @param string|null $type     Column type: 'string' | 'integer' | 'float' | 'boolean' | 'datetime' | 'json'.
     *                              Null = inferred from the PHP property type.
     * @param bool         $nullable Whether the column allows NULL.
     * @param int|null     $length   String column length (VARCHAR/CHAR).
     */
    /**
     * @param string|null $name     Column name (default: snake_case of the property name).
     * @param string|null $type     Column type: 'string' | 'integer' | 'float' | 'boolean' | 'datetime' | 'json'.
     *                              Null = inferred from the PHP property type.
     * @param bool         $nullable Whether the column allows NULL.
     * @param int|null     $length   String column length (VARCHAR/CHAR).
     * @param bool         $unique   Whether the column has a UNIQUE constraint.
     *                              SqliteDriver translates this to a UNIQUE column.
     *                              No-op for InMemoryDriver (no schema).
     * @param mixed        $default  Column default value (NULL = no DEFAULT clause).
     *                              SqliteDriver renders this as `DEFAULT <value>` in DDL.
     *                              String defaults are single-quoted with embedded
     *                              single quotes escaped per SQL standard (`'` → `''`).
     *                              Boolean defaults render as 1/0 (SQLite has no native bool).
     *                              NULL/null skips the DEFAULT clause entirely.
     */
    public function __construct(
        public readonly ?string $name = null,
        public readonly ?string $type = null,
        public readonly bool $nullable = false,
        public readonly ?int $length = null,
        public readonly bool $unique = false,
        public readonly mixed $default = null,
    ) {
    }
}
