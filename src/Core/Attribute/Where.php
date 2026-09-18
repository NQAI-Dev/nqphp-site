<?php

declare(strict_types=1);

namespace Nqphp\Core\Attribute;

/**
 * Mark a property as filterable in EntityManager queries with a
 * custom SQL operator (LIKE, IN, BETWEEN, >, <, etc.).
 *
 * Without #[Where], `findBy(Entity::class, ['name' => 'Alice'])`
 * generates `WHERE name = :name` (exact match).
 *
 * With #[Where], callers can choose an operator per call:
 *
 *   #[Where(operator: 'LIKE')]
 *   public string $name;
 *
 *   $em->findBy(User::class, ['name' => '%ali%']);
 *   // generates: WHERE name LIKE :name
 *
 * Supported operators (string-form, case-insensitive):
 *   '=' (default), 'LIKE', 'IN', '>', '<', '>=', '<=', '!='
 *
 * For 'IN', the value must be an array — the driver generates
 * `WHERE col IN (:col_0, :col_1, ...)`.
 *
 * For 'BETWEEN', the value must be a 2-element array [min, max];
 * the driver generates `WHERE col BETWEEN :col_min AND :col_max`.
 *
 * Operators that allow SQL injection (e.g. raw fragments) are NOT
 * exposed. Callers needing full SQL freedom should compose
 * #[Column(name: '...')] with a manual `$pdo->prepare(...)`.
 *
 * Phase 2 #10 (custom ORM query DSL). Adds LIKE/IN/BETWEEN/comparison
 * operators on top of the exact-match API shipped at b15a757.
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class Where
{
    public function __construct(
        public readonly string $operator = '=',
    ) {
    }
}
