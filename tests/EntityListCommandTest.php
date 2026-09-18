<?php

declare(strict_types=1);

namespace Nqphp\Tests;

use Nqphp\Core\Kernel\Kernel;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Phase 2 (entity DX) test.
 *
 * Verifies `bin/console entity:list` runs end-to-end and renders the
 * expected output structure (header + table-like rows).
 */
final class EntityListCommandTest extends TestCase
{
    private const PROJECT_DIR = __DIR__ . '/..';

    public function testCommandOutputsExpectedHeader(): void
    {
        $kernel = new Kernel(self::PROJECT_DIR);
        $app = new Application('nqphp');
        // Reflectively construct the command with Kernel injection
        // (Application::find picks by name but the command needs
        // Kernel injected via constructor).
        $cmdClass = new \ReflectionClass(\Nqphp\Core\Console\EntityListCommand::class);
        $cmd = $cmdClass->newInstance($kernel);
        $app->add($cmd);

        $tester = new CommandTester($app->find('entity:list'));
        $tester->execute([]);
        $output = $tester->getDisplay();

        self::assertStringContainsString('Discovered entities', $output);
        self::assertStringContainsString('NAME', $output);
        self::assertStringContainsString('CLASS', $output);
    }
}
