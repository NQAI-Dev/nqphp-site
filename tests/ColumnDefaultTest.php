<?php

declare(strict_types=1);

namespace Nqphp\Tests;

use Nqphp\Core\Entity\Driver\SqliteDriver;
use PHPUnit\Framework\TestCase;

/**
 * Phase 2 #10 extension: #[Column(default: ...)] translates to
 * SQLite DEFAULT clause in CREATE TABLE.
 *
 * Mirrors tests/ColumnUniqueTest.php pattern.
 */
final class ColumnDefaultTest extends TestCase
{
    public function testStringDefaultFillsOnInsert(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite not loaded');
            return;
        }

        $pdo = new \PDO('sqlite::memory:');
        $driver = new SqliteDriver($pdo);
        $driver->ensureSchema('user', [
            'id'   => ['type' => 'integer', 'nullable' => false, 'unique' => false, 'default' => null],
            'name' => ['type' => 'string',  'nullable' => false, 'unique' => false, 'default' => 'Anonymous'],
        ]);

        // Verify schema contains DEFAULT clause
        $sql = $pdo->query("SELECT sql FROM sqlite_master WHERE type='table' AND name='user'")
                   ->fetchColumn();
        self::assertStringContainsString("DEFAULT 'Anonymous'", $sql);

        $pdo->exec("INSERT INTO user (id) VALUES (1)");
        $name = $pdo->query('SELECT name FROM user WHERE id=1')->fetchColumn();
        self::assertSame('Anonymous', $name);
    }

    public function testNumericDefaults(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite not loaded');
            return;
        }

        $pdo = new \PDO('sqlite::memory:');
        $driver = new SqliteDriver($pdo);
        $driver->ensureSchema('counter', [
            'id'      => ['type' => 'integer', 'nullable' => false, 'unique' => false, 'default' => null],
            'count'   => ['type' => 'integer', 'nullable' => false, 'unique' => false, 'default' => 0],
            'rate'    => ['type' => 'float',   'nullable' => false, 'unique' => false, 'default' => 1.5],
            'enabled' => ['type' => 'boolean', 'nullable' => false, 'unique' => false, 'default' => true],
        ]);

        $pdo->exec('INSERT INTO counter (id) VALUES (1)');
        $row = $pdo->query('SELECT * FROM counter WHERE id=1')->fetch(\PDO::FETCH_ASSOC);
        self::assertSame(0, (int) $row['count']);
        self::assertSame(1.5, (float) $row['rate']);
        self::assertSame(1, (int) $row['enabled']);
    }

    public function testStringWithSingleQuoteEscaped(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite not loaded');
            return;
        }

        $pdo = new \PDO('sqlite::memory:');
        $driver = new SqliteDriver($pdo);
        $driver->ensureSchema('user', [
            'id'   => ['type' => 'integer', 'nullable' => false, 'unique' => false, 'default' => null],
            'name' => ['type' => 'string',  'nullable' => false, 'unique' => false, 'default' => "O'Brien"],
        ]);

        // Embedded single quote MUST be doubled per SQL standard
        $sql = $pdo->query("SELECT sql FROM sqlite_master WHERE type='table' AND name='user'")
                   ->fetchColumn();
        self::assertStringContainsString("DEFAULT 'O''Brien'", $sql);

        $pdo->exec('INSERT INTO user (id) VALUES (1)');
        $name = $pdo->query('SELECT name FROM user WHERE id=1')->fetchColumn();
        self::assertSame("O'Brien", $name);
    }

    public function testNullDefaultMeansNoDefaultClause(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite not loaded');
            return;
        }

        $pdo = new \PDO('sqlite::memory:');
        $driver = new SqliteDriver($pdo);
        $driver->ensureSchema('note', [
            'id'      => ['type' => 'integer', 'nullable' => false, 'unique' => false, 'default' => null],
            'content' => ['type' => 'string',  'nullable' => true,  'unique' => false, 'default' => null],
        ]);

        // No DEFAULT clause for nullable column with null default
        $sql = $pdo->query("SELECT sql FROM sqlite_master WHERE type='table' AND name='note'")
                   ->fetchColumn();
        self::assertStringNotContainsString('DEFAULT', $sql);

        // INSERT without content → NULL
        $pdo->exec('INSERT INTO note (id) VALUES (1)');
        $content = $pdo->query('SELECT content FROM note WHERE id=1')->fetchColumn();
        self::assertNull($content);
    }

    public function testBooleanDefaultRendersAs1or0(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite not loaded');
            return;
        }

        $pdo = new \PDO('sqlite::memory:');
        $driver = new SqliteDriver($pdo);
        $driver->ensureSchema('flag', [
            'id'    => ['type' => 'integer', 'nullable' => false, 'unique' => false, 'default' => null],
            'on'    => ['type' => 'boolean', 'nullable' => false, 'unique' => false, 'default' => true],
            'off'   => ['type' => 'boolean', 'nullable' => false, 'unique' => false, 'default' => false],
        ]);

        $sql = $pdo->query("SELECT sql FROM sqlite_master WHERE type='table' AND name='flag'")
                   ->fetchColumn();
        self::assertStringContainsString('DEFAULT 1', $sql);
        self::assertStringContainsString('DEFAULT 0', $sql);

        $pdo->exec('INSERT INTO flag (id) VALUES (1)');
        $row = $pdo->query('SELECT * FROM flag WHERE id=1')->fetch(\PDO::FETCH_ASSOC);
        self::assertSame(1, (int) $row['on']);
        self::assertSame(0, (int) $row['off']);
    }
}
