<?php

declare(strict_types=1);

namespace Nqphp\Tests;

use Nqphp\Core\Kernel\Kernel;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Phase 2 DX (service list command) test.
 *
 * Verifies the `bin/console service:list` output structure: header +
 * NAME / SCOPE / CLASS table.
 */
final class ServiceListCommandTest extends TestCase
{
    private const PROJECT_DIR = __DIR__ . '/..';

    public function testCommandOutputsExpectedHeaders(): void
    {
        $kernel = new Kernel(self::PROJECT_DIR);
        $app = new Application('nqphp');
        $cmdClass = new \ReflectionClass(\Nqphp\Core\Service\ServiceListCommand::class);
        $cmd = $cmdClass->newInstance($kernel);
        $app->add($cmd);

        $tester = new CommandTester($app->find('service:list'));
        $tester->execute([]);
        $output = $tester->getDisplay();

        self::assertStringContainsString('Discovered services', $output);
        self::assertStringContainsString('NAME', $output);
        self::assertStringContainsString('SCOPE', $output);
        self::assertStringContainsString('CLASS', $output);
    }
}
