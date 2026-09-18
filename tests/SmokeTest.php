<?php

declare(strict_types=1);

namespace Nqphp\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Smoke test — confirms the test runner boots and composer autoload
 * is wired correctly. Real framework tests follow in Phase 2.
 */
final class SmokeTest extends TestCase
{
    public function testBoot(): void
    {
        self::assertTrue(class_exists(\Nqphp\Core\Attribute\Controller::class));
        self::assertTrue(class_exists(\Nqphp\Core\Attribute\Route::class));
        self::assertTrue(class_exists(\Nqphp\Core\Kernel\Kernel::class));
    }
}
