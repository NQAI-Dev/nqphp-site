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
 * Phase 2 #11 — EntityManager::remove() basic lifecycle test.
 *
 * Covers:
 *   - persist → remove → findOneBy returns null
 *   - remove is idempotent (second call returns false)
 *   - remove on never-persisted entity throws
 *   - same API works against both InMemoryDriver and SqliteDriver
 */
final class EntityManagerRemoveTest extends TestCase
{
    private const ENTITY_SRC = <<<'PHP'
<?php
declare(strict_types=1);
namespace App\Del\Entity;

use Nqphp\Core\Attribute\Column;
use Nqphp\Core\Attribute\Entity;
use Nqphp\Core\Attribute\Id;

#[Entity(name: 'note')]
final class Note
{
    #[Id]
    public ?int $id = null;
    #[Column]
    public string $body = '';
}
PHP;

    private function setupEnvironment(): string
    {
        $tmp = sys_get_temp_dir() . '/nqphp-rm-' . uniqid();
        mkdir($tmp . '/Feature/Del/Entity', 0755, true);
        $src = $tmp . '/Feature/Del/Entity/Note.php';
        file_put_contents($src, self::ENTITY_SRC);
        require_once $src;
        return $tmp;
    }

    private function cleanupEnvironment(string $tmp): void
    {
        @unlink($tmp . '/Feature/Del/Entity/Note.php');
        @rmdir($tmp . '/Feature/Del/Entity');
        @rmdir($tmp . '/Feature/Del');
        @rmdir($tmp . '/Feature');
        @rmdir($tmp);
    }

    public function testRemoveReturnsTrueOnceThenFalse(): void
    {
        $tmp = $this->setupEnvironment();
        try {
            $discoverer = new EntityDiscoverer([$tmp]);
            $em = new EntityManager($discoverer);

            $note = new \App\Del\Entity\Note();
            $note->body = 'read me';
            $em->persist($note);
            self::assertSame(1, $note->id);

            // First remove: success
            self::assertTrue($em->remove($note));
            // findOneBy now returns null
            self::assertNull($em->findOneBy(\App\Del\Entity\Note::class, ['id' => 1]));

            // Second remove: idempotent (false)
            self::assertFalse($em->remove($note));
        } finally {
            $this->cleanupEnvironment($tmp);
        }
    }

    public function testRemoveOnNeverPersistedThrows(): void
    {
        $tmp = $this->setupEnvironment();
        try {
            $discoverer = new EntityDiscoverer([$tmp]);
            $em = new EntityManager($discoverer);

            $note = new \App\Del\Entity\Note();
            $note->body = 'never persisted';
            // id is null → remove() should throw

            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('its #[Id] is null');
            $em->remove($note);
        } finally {
            $this->cleanupEnvironment($tmp);
        }
    }

    public function testSqliteRemoveLifecycle(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite not loaded');
            return;
        }
        $tmp = $this->setupEnvironment();
        try {
            $discoverer = new EntityDiscoverer([$tmp]);
            $pdo = new \PDO('sqlite::memory:');
            $em = new EntityManager($discoverer, new \Nqphp\Core\Entity\Driver\SqliteDriver($pdo));

            $note = new \App\Del\Entity\Note();
            $note->body = 'sqlite test';
            $em->persist($note);

            // verify it persisted
            self::assertSame('sqlite test', $em->findOneBy(\App\Del\Entity\Note::class, ['id' => 1])->body);

            // remove + verify
            self::assertTrue($em->remove($note));
            self::assertNull($em->findOneBy(\App\Del\Entity\Note::class, ['id' => 1]));

            // count is now 0
            self::assertSame(0, $em->count(\App\Del\Entity\Note::class));
        } finally {
            $this->cleanupEnvironment($tmp);
        }
    }
}
