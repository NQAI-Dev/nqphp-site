<?php

declare(strict_types=1);

namespace Nqphp\Tests;

use Nqphp\Core\Attribute\Column;
use Nqphp\Core\Attribute\Entity;
use Nqphp\Core\Attribute\Id;
use Nqphp\Core\Attribute\Where;
use Nqphp\Core\Entity\EntityDiscoverer;
use Nqphp\Core\Entity\EntityManager;
use PHPUnit\Framework\TestCase;

/**
 * Phase 2 #10 — operator-aware criteria for findBy/findOneBy/count.
 *
 * Verifies both drivers (in-memory + Sqlite) accept the new
 * operator-array criterion shapes without breaking the existing
 * scalar/exact-match API.
 */
final class EntityManagerOperatorTest extends TestCase
{
    private const ENTITY_SRC = <<<'PHP'
<?php
declare(strict_types=1);
namespace App\Ops\Entity;

use Nqphp\Core\Attribute\Column;
use Nqphp\Core\Attribute\Entity;
use Nqphp\Core\Attribute\Id;
use Nqphp\Core\Attribute\Where;

#[Entity(name: 'item')]
final class Item
{
    #[Id]
    public ?int $id = null;
    #[Column]
    public string $name = '';
    #[Column]
    public int $price = 0;
    #[Where(operator: 'LIKE')]
    public string $sku = '';
}
PHP;

    private function setupEnvironment(): string
    {
        $tmp = sys_get_temp_dir() . '/nqphp-ops-' . uniqid();
        mkdir($tmp . '/Feature/Ops/Entity', 0755, true);
        $src = $tmp . '/Feature/Ops/Entity/Item.php';
        file_put_contents($src, self::ENTITY_SRC);
        require_once $src;
        return $tmp;
    }

    private function cleanupEnvironment(string $tmp): void
    {
        @unlink($tmp . '/Feature/Ops/Entity/Item.php');
        @rmdir($tmp . '/Feature/Ops/Entity');
        @rmdir($tmp . '/Feature/Ops');
        @rmdir($tmp . '/Feature');
        @rmdir($tmp);
    }

    public function testInMemoryLikeOperator(): void
    {
        $tmp = $this->setupEnvironment();
        $discoverer = new EntityDiscoverer([$tmp]);
        $em = new EntityManager($discoverer);

        foreach ([
            ['name' => 'Apple', 'price' => 10, 'sku' => 'sku-AAA'],
            ['name' => 'Apricot', 'price' => 15, 'sku' => 'sku-AAB'],
            ['name' => 'Banana', 'price' => 20, 'sku' => 'sku-BBB'],
        ] as $row) {
            $i = new \App\Ops\Entity\Item();
            $i->name = $row['name'];
            $i->price = $row['price'];
            $i->sku = $row['sku'];
            $em->persist($i);
        }

        $found = $em->findBy(\App\Ops\Entity\Item::class, ['name' => ['LIKE' => 'Ap%']]);
        self::assertCount(2, $found);
        self::assertSame('Apple', $found[0]->name);
        self::assertSame('Apricot', $found[1]->name);

        $this->cleanupEnvironment($tmp);
    }

    public function testInMemoryInAndBetween(): void
    {
        $tmp = $this->setupEnvironment();
        $discoverer = new EntityDiscoverer([$tmp]);
        $em = new EntityManager($discoverer);

        foreach ([
            ['name' => 'Apple', 'price' => 10, 'sku' => 'a'],
            ['name' => 'Apricot', 'price' => 15, 'sku' => 'b'],
            ['name' => 'Banana', 'price' => 20, 'sku' => 'c'],
            ['name' => 'Cherry', 'price' => 25, 'sku' => 'd'],
        ] as $row) {
            $i = new \App\Ops\Entity\Item();
            $i->name = $row['name'];
            $i->price = $row['price'];
            $i->sku = $row['sku'];
            $em->persist($i);
        }

        // IN
        $found = $em->findBy(\App\Ops\Entity\Item::class, ['name' => ['IN' => ['Apple', 'Cherry']]]);
        self::assertCount(2, $found);

        // BETWEEN
        $found = $em->findBy(\App\Ops\Entity\Item::class, ['price' => ['BETWEEN' => [12, 22]]]);
        self::assertCount(2, $found);
        self::assertSame('Apricot', $found[0]->name);
        self::assertSame('Banana', $found[1]->name);

        $this->cleanupEnvironment($tmp);
    }

    public function testSqliteDriverLikeOperator(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite not loaded');
            return;
        }
        $tmp = $this->setupEnvironment();
        $discoverer = new EntityDiscoverer([$tmp]);
        $pdo = new \PDO('sqlite::memory:');
        $em = new EntityManager($discoverer, new \Nqphp\Core\Entity\Driver\SqliteDriver($pdo));

        foreach ([
            ['name' => 'Apple', 'price' => 10, 'sku' => 'sku-AAA'],
            ['name' => 'Apricot', 'price' => 15, 'sku' => 'sku-AAB'],
            ['name' => 'Banana', 'price' => 20, 'sku' => 'sku-BBB'],
        ] as $row) {
            $i = new \App\Ops\Entity\Item();
            $i->name = $row['name'];
            $i->price = $row['price'];
            $i->sku = $row['sku'];
            $em->persist($i);
        }

        $found = $em->findBy(\App\Ops\Entity\Item::class, ['name' => ['LIKE' => 'Ap%']]);
        self::assertCount(2, $found);

        // Sqlite persists across Driver reuse
        $em2 = new EntityManager($discoverer, new \Nqphp\Core\Entity\Driver\SqliteDriver($pdo));
        $all = $em2->findAll(\App\Ops\Entity\Item::class);
        self::assertGreaterThanOrEqual(3, count($all));

        $this->cleanupEnvironment($tmp);
    }

    public function testSqliteDriverInAndBetween(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite not loaded');
            return;
        }
        $tmp = $this->setupEnvironment();
        $discoverer = new EntityDiscoverer([$tmp]);
        $pdo = new \PDO('sqlite::memory:');
        $em = new EntityManager($discoverer, new \Nqphp\Core\Entity\Driver\SqliteDriver($pdo));

        foreach ([
            ['name' => 'Apple', 'price' => 10, 'sku' => 'a'],
            ['name' => 'Apricot', 'price' => 15, 'sku' => 'b'],
            ['name' => 'Banana', 'price' => 20, 'sku' => 'c'],
            ['name' => 'Cherry', 'price' => 25, 'sku' => 'd'],
        ] as $row) {
            $i = new \App\Ops\Entity\Item();
            $i->name = $row['name'];
            $i->price = $row['price'];
            $i->sku = $row['sku'];
            $em->persist($i);
        }

        // IN
        $found = $em->findBy(\App\Ops\Entity\Item::class, ['name' => ['IN' => ['Apple', 'Cherry']]]);
        self::assertCount(2, $found);

        // BETWEEN
        $found = $em->findBy(\App\Ops\Entity\Item::class, ['price' => ['BETWEEN' => [12, 22]]]);
        self::assertCount(2, $found);

        // Comparison
        $found = $em->findBy(\App\Ops\Entity\Item::class, ['price' => ['>=' => 20]]);
        self::assertCount(2, $found);
        self::assertSame('Banana', $found[0]->name);

        $this->cleanupEnvironment($tmp);
    }

    public function testCountWithOperators(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite not loaded');
            return;
        }
        $tmp = $this->setupEnvironment();
        $discoverer = new EntityDiscoverer([$tmp]);
        $pdo = new \PDO('sqlite::memory:');
        $em = new EntityManager($discoverer, new \Nqphp\Core\Entity\Driver\SqliteDriver($pdo));

        for ($i = 1; $i <= 5; $i++) {
            $it = new \App\Ops\Entity\Item();
            $it->name = "Item{$i}";
            $it->price = $i * 10;
            $it->sku = "sku-{$i}";
            $em->persist($it);
        }

        self::assertSame(5, $em->count(\App\Ops\Entity\Item::class));
        self::assertSame(3, $em->count(\App\Ops\Entity\Item::class, ['price' => ['>=' => 30]]));
        self::assertSame(2, $em->count(\App\Ops\Entity\Item::class, ['name' => ['IN' => ['Item1', 'Item2']]]));

        $this->cleanupEnvironment($tmp);
    }
}
