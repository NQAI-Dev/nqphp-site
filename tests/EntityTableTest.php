<?php

declare(strict_types=1);

namespace Nqphp\Tests;

use Nqphp\Core\Attribute\Column;
use Nqphp\Core\Attribute\Entity;
use Nqphp\Core\Attribute\Id;
use Nqphp\Core\Entity\Driver\SqliteDriver;
use Nqphp\Core\Entity\EntityDiscoverer;
use Nqphp\Core\Entity\EntityManager;
use PHPUnit\Framework\TestCase;

/**
 * Phase 2 #10+ extension: #[Entity(table: ...)] lets a single entity
 * class map to a custom physical SQL table (legacy DB integration,
 * multi-tenant schemas).
 *
 * Without `table`, the physical SQL name equals the canonical handle
 * (Entity::name) — backward-compatible behavior.
 */
final class EntityTableTest extends TestCase
{
    public function testTableOverridePersistsToCustomSqlTable(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite not loaded');
            return;
        }

        $tmp = sys_get_temp_dir() . '/nqphp-tbl-' . uniqid();
        mkdir($tmp . '/Feature/Leg/Entity', 0755, true);
        $src = $tmp . '/Feature/Leg/Entity/User.php';
        file_put_contents($src, <<<'PHP'
<?php
declare(strict_types=1);
namespace App\Leg\Entity;

use Nqphp\Core\Attribute\Column;
use Nqphp\Core\Attribute\Entity;
use Nqphp\Core\Attribute\Id;

#[Entity(name: 'user', table: 'app_legacy_users')]
final class User
{
    #[Id]
    public ?int $id = null;
    #[Column]
    public string $email = '';
}
PHP);
        require_once $src;

        $pdo = new \PDO('sqlite::memory:');
        $em = new EntityManager(
            new EntityDiscoverer([$tmp]),
            new SqliteDriver($pdo)
        );

        // Persistence uses the override table name (app_legacy_users),
        // not the canonical handle (user).
        $u = new \App\Leg\Entity\User();
        $u->email = 'legacy@example.com';
        $em->persist($u);
        self::assertSame(1, $u->id);

        // Verify the row landed in `app_legacy_users` (not `user`).
        // Schema is bootstrapped on first persist via ensureSchema().
        $count = $pdo->query("SELECT COUNT(*) FROM `app_legacy_users`")->fetchColumn();
        self::assertSame(1, $count);

        // The handle name (`user`) does NOT get a table — proves the
        // override actually redirected storage.
        try {
            $pdo->query("SELECT COUNT(*) FROM `user`");
            self::fail('expected table `user` to not exist (override should have redirected)');
        } catch (\PDOException $e) {
            // sqlite "no such table" error — expected
            self::assertStringContainsStringIgnoringCase('no such table', $e->getMessage());
        }

        unlink($src);
        rmdir($tmp . '/Feature/Leg/Entity');
        rmdir($tmp . '/Feature/Leg');
        rmdir($tmp . '/Feature');
        rmdir($tmp);
    }

    public function testDefaultBehaviorEqualsName(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite not loaded');
            return;
        }

        $tmp = sys_get_temp_dir() . '/nqphp-tbl2-' . uniqid();
        mkdir($tmp . '/Feature/Def/Entity', 0755, true);
        $src = $tmp . '/Feature/Def/Entity/Item.php';
        file_put_contents($src, <<<'PHP'
<?php
declare(strict_types=1);
namespace App\Def\Entity;

use Nqphp\Core\Attribute\Column;
use Nqphp\Core\Attribute\Entity;
use Nqphp\Core\Attribute\Id;

#[Entity(name: 'item')]
final class Item
{
    #[Id]
    public ?int $id = null;
    #[Column]
    public string $name = '';
}
PHP);
        require_once $src;

        $pdo = new \PDO('sqlite::memory:');
        $em = new EntityManager(
            new EntityDiscoverer([$tmp]),
            new SqliteDriver($pdo)
        );

        $i = new \App\Def\Entity\Item();
        $i->name = 'thing';
        $em->persist($i);

        // Default: table == name (no override → backward compat).
        $count = $pdo->query("SELECT COUNT(*) FROM `item`")->fetchColumn();
        self::assertSame(1, $count);

        unlink($src);
        rmdir($tmp . '/Feature/Def/Entity');
        rmdir($tmp . '/Feature/Def');
        rmdir($tmp . '/Feature');
        rmdir($tmp);
    }
}
