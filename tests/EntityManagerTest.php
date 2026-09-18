<?php

declare(strict_types=1);

namespace Nqphp\Tests;

use Nqphp\Core\Attribute\Column;
use Nqphp\Core\Attribute\Entity;
use Nqphp\Core\Attribute\Id;
use Nqphp\Core\Entity\EntityDiscoverer;
use Nqphp\Core\Entity\EntityManager;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Phase 2 #8 (custom ORM, not Doctrine) test.
 *
 * Validates the EntityManager query DSL against an in-memory store.
 * Phase 2 #9 will add the SQL driver that exercises the same API
 * over PDO.
 */
final class EntityManagerTest extends TestCase
{
    public function testFindByRequiresAtLeastOneColumn(): void
    {
        // Set up an entity class with #[Id] + #[Column] and verify
        // basic save + find lifecycle works.
        $tmp = sys_get_temp_dir() . '/nqphp-em-' . uniqid();
        mkdir($tmp . '/Feature/MyApp/Entity', 0755, true);
        $srcPath = $tmp . '/Feature/MyApp/Entity/User.php';
        file_put_contents($srcPath, <<<'PHP'
<?php
declare(strict_types=1);
namespace App\MyApp\Entity;

use Nqphp\Core\Attribute\Column;
use Nqphp\Core\Attribute\Entity;
use Nqphp\Core\Attribute\Id;

#[Entity(name: 'user')]
final class User
{
    #[Id]
    public ?int $id = null;
    #[Column(name: 'email')]
    public string $email = '';
    #[Column(name: 'name')]
    public string $name = '';
}
PHP);

        require_once $srcPath;
        $discoverer = new EntityDiscoverer([$tmp]);
        $em = new EntityManager($discoverer);

        /** @var object $alice */
        $alice = new \App\MyApp\Entity\User();
        $alice->email = 'alice@example.com';
        $alice->name = 'Alice';
        $em->persist($alice);
        self::assertSame(1, $alice->id);

        /** @var object $bob */
        $bob = new \App\MyApp\Entity\User();
        $bob->email = 'bob@example.com';
        $bob->name = 'Bob';
        $em->persist($bob);
        self::assertSame(2, $bob->id);

        $found = $em->findBy(\App\MyApp\Entity\User::class, ['email' => 'alice@example.com']);
        self::assertCount(1, $found);
        self::assertSame('Alice', $found[0]->name);

        $all = $em->findAll(\App\MyApp\Entity\User::class);
        self::assertCount(2, $all);

        $count = $em->count(\App\MyApp\Entity\User::class);
        self::assertSame(2, $count);

        unlink($srcPath);
        rmdir($tmp . '/Feature/MyApp/Entity');
        rmdir($tmp . '/Feature/MyApp');
        rmdir($tmp . '/Feature');
        rmdir($tmp);
    }

    public function testFindOneByReturnsFirstMatchOrNull(): void
    {
        $tmp = sys_get_temp_dir() . '/nqphp-em2-' . uniqid();
        mkdir($tmp . '/Feature/Test/Entity', 0755, true);
        $srcPath = $tmp . '/Feature/Test/Entity/Post.php';
        file_put_contents($srcPath, <<<'PHP'
<?php
declare(strict_types=1);
namespace App\Test\Entity;

use Nqphp\Core\Attribute\Column;
use Nqphp\Core\Attribute\Entity;
use Nqphp\Core\Attribute\Id;

#[Entity(name: 'post')]
final class Post
{
    #[Id]
    public ?int $id = null;
    #[Column]
    public string $title = '';
}
PHP);

        require_once $srcPath;
        $discoverer = new EntityDiscoverer([$tmp]);
        $em = new EntityManager($discoverer);

        /** @var object $p */
        $p = new \App\Test\Entity\Post();
        $p->title = 'Hello';
        $em->persist($p);

        $found = $em->findOneBy(\App\Test\Entity\Post::class, ['title' => 'Hello']);
        self::assertNotNull($found);
        self::assertSame('Hello', $found->title);

        $missing = $em->findOneBy(\App\Test\Entity\Post::class, ['title' => 'Nonexistent']);
        self::assertNull($missing);

        unlink($srcPath);
        rmdir($tmp . '/Feature/Test/Entity');
        rmdir($tmp . '/Feature/Test');
        rmdir($tmp . '/Feature');
        rmdir($tmp);
    }

    public function testEntityWithoutIdThrows(): void
    {
        $tmp = sys_get_temp_dir() . '/nqphp-em3-' . uniqid();
        mkdir($tmp . '/Feature/Bad/Entity', 0755, true);
        $srcPath = $tmp . '/Feature/Bad/Entity/Bad.php';
        file_put_contents($srcPath, <<<'PHP'
<?php
declare(strict_types=1);
namespace App\Bad\Entity;

use Nqphp\Core\Attribute\Entity;

#[Entity(name: 'bad')]
final class Bad
{
    public ?int $id = null;  // no #[Id]!
}
PHP);

        require_once $srcPath;
        $discoverer = new EntityDiscoverer([$tmp]);
        $em = new EntityManager($discoverer);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('has no #[Id] property');
        $em->persist(new \App\Bad\Entity\Bad());

        unlink($srcPath);
        rmdir($tmp . '/Feature/Bad/Entity');
        rmdir($tmp . '/Feature/Bad');
        rmdir($tmp . '/Feature');
        rmdir($tmp);
    }
}
