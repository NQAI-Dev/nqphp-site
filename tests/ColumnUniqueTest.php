<?php

declare(strict_types=1);

namespace Nqphp\Tests;

use Nqphp\Core\Attribute\Column;
use Nqphp\Core\Attribute\Entity;
use Nqphp\Core\Attribute\Id;
use Nqphp\Core\Entity\Driver\SqliteDriver;
use PHPUnit\Framework\TestCase;
use PDOException;

/**
 * Phase 2 #10 extension: #[Column(unique: true)] translates to
 * UNIQUE column-constraint in SQLite.
 *
 * Mirrors the pattern from EntityManagerTest — temporary directory
 * with #[Entity]-tagged source, driver-asserting schema + insert
 * behavior. Pure runtime test (no DI), uses PHPUnit's TestCase.
 */
final class ColumnUniqueTest extends TestCase
{
    public function testUniqueConstraintBlocksDuplicateInserts(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite not loaded');
            return;
        }

        $pdo = new \PDO('sqlite::memory:');
        $driver = new SqliteDriver($pdo);
        $driver->ensureSchema('user', [
            'id'    => ['type' => 'integer', 'nullable' => false, 'unique' => false],
            'email' => ['type' => 'string',  'nullable' => false, 'unique' => true, 'length' => 255],
            'name'  => ['type' => 'string',  'nullable' => true,  'unique' => false],
        ]);

        // Verify schema contains UNIQUE on email
        $sql = $pdo->query("SELECT sql FROM sqlite_master WHERE type='table' AND name='user'")
                   ->fetchColumn();
        self::assertStringContainsString('UNIQUE', $sql);
        self::assertStringContainsString('email', $sql);

        // First insert OK
        $pdo->exec("INSERT INTO user (email, name) VALUES ('alice@x.com', 'Alice')");
        self::assertSame(1, $pdo->query("SELECT COUNT(*) FROM user")->fetchColumn());

        // Duplicate email blocked by UNIQUE constraint
        $this->expectException(PDOException::class);
        $this->expectExceptionMessageMatches('/UNIQUE constraint failed/');
        $pdo->exec("INSERT INTO user (email, name) VALUES ('alice@x.com', 'Alice2')");
    }

    public function testNonUniqueColumnAllowsDuplicates(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite not loaded');
            return;
        }

        $pdo = new \PDO('sqlite::memory:');
        $driver = new SqliteDriver($pdo);
        $driver->ensureSchema('post', [
            'id'    => ['type' => 'integer', 'nullable' => false, 'unique' => false],
            'title' => ['type' => 'string',  'nullable' => false, 'unique' => false],
        ]);

        $pdo->exec("INSERT INTO post (title) VALUES ('hello')");
        $pdo->exec("INSERT INTO post (title) VALUES ('hello')");  // dup OK без unique
        self::assertSame(2, $pdo->query("SELECT COUNT(*) FROM post")->fetchColumn());
    }

    public function testMultipleUniqueColumnsInOneTable(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite not loaded');
            return;
        }

        $pdo = new \PDO('sqlite::memory:');
        $driver = new SqliteDriver($pdo);
        $driver->ensureSchema('account', [
            'id'       => ['type' => 'integer', 'nullable' => false, 'unique' => false],
            'username' => ['type' => 'string',  'nullable' => false, 'unique' => true],
            'email'    => ['type' => 'string',  'nullable' => false, 'unique' => true],
        ]);

        // Both fields with UNIQUE
        $sql = $pdo->query("SELECT sql FROM sqlite_master WHERE type='table' AND name='account'")
                   ->fetchColumn();
        $uniqueCount = substr_count($sql, 'UNIQUE');
        self::assertSame(2, $uniqueCount, 'expected 2 UNIQUE constraints');

        $pdo->exec("INSERT INTO account (username, email) VALUES ('alice', 'a@x.com')");

        // Dup username blocked
        try {
            $pdo->exec("INSERT INTO account (username, email) VALUES ('alice', 'b@x.com')");
            self::fail('expected UNIQUE violation on duplicate username');
        } catch (PDOException $e) {
            self::assertStringContainsString('UNIQUE', $e->getMessage());
        }

        // Dup email blocked
        try {
            $pdo->exec("INSERT INTO account (username, email) VALUES ('bob', 'a@x.com')");
            self::fail('expected UNIQUE violation on duplicate email');
        } catch (PDOException $e) {
            self::assertStringContainsString('UNIQUE', $e->getMessage());
        }

        // Different username + email OK
        $pdo->exec("INSERT INTO account (username, email) VALUES ('bob', 'b@x.com')");
        self::assertSame(2, $pdo->query("SELECT COUNT(*) FROM account")->fetchColumn());
    }
}
