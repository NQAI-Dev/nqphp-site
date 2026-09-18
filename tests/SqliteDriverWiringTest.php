<?php

declare(strict_types=1);

namespace Nqphp\Tests;

use Nqphp\Core\Kernel\Kernel;
use Nqphp\Core\Entity\Driver\DriverInterface;
use Nqphp\Core\Entity\Driver\SqliteDriver;
use Nqphp\Core\Entity\Driver\InMemoryDriver;
use PHPUnit\Framework\TestCase;

/**
 * Phase 2 #9 verification test — confirms the Kernel's default
 * SqliteDriver wiring works end-to-end.
 */
final class SqliteDriverWiringTest extends TestCase
{
    private const PROJECT_DIR = __DIR__ . '/..';

    public function testKernelDefaultsToSqliteDriver(): void
    {
        $kernel = new Kernel(self::PROJECT_DIR);
        // Drive via reflection to inspect the runtime type.
        $ref = new \ReflectionClass($kernel);
        $driverProp = $ref->getProperty('driver');
        $driverProp->setAccessible(true);
        self::assertInstanceOf(DriverInterface::class, $driverProp->getValue($kernel));
        // In CI (no env var override) we default to SqliteDriver with :memory:
        $driver = $driverProp->getValue($kernel);
        self::assertInstanceOf(SqliteDriver::class, $driver);
    }

    public function testEnvOverrideMemoryFallsBackToInMemory(): void
    {
        $_SERVER['NQPHP_DRIVER'] = 'memory';
        try {
            $kernel = new Kernel(self::PROJECT_DIR);
            $ref = new \ReflectionClass($kernel);
            $driverProp = $ref->getProperty('driver');
            $driverProp->setAccessible(true);
            self::assertInstanceOf(InMemoryDriver::class, $driverProp->getValue($kernel));
        } finally {
            unset($_SERVER['NQPHP_DRIVER']);
        }
    }

    public function testEntityManagerUsesSameDriverInstance(): void
    {
        $kernel = new Kernel(self::PROJECT_DIR);
        $ref = new \ReflectionClass($kernel);
        $driverProp = $ref->getProperty('driver');
        $emProp = $ref->getProperty('entityManager');
        $driverProp->setAccessible(true);
        $emProp->setAccessible(true);

        $em = $emProp->getValue($kernel);
        $refEm = new \ReflectionClass($em);
        $emDriverProp = $refEm->getProperty('driver');
        $emDriverProp->setAccessible(true);
        self::assertSame($driverProp->getValue($kernel), $emDriverProp->getValue($em));
    }
}
