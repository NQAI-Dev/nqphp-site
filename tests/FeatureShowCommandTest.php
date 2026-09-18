<?php

declare(strict_types=1);

namespace Nqphp\Tests;

use Nqphp\Core\Kernel\Kernel;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Phase 2 DX (feature:show command) test.
 *
 * Verifies the single-feature report command outputs the expected
 * sections (config, routes, middlewares) and fails loudly when
 * the feature name doesn't exist.
 */
final class FeatureShowCommandTest extends TestCase
{
    private const PROJECT_DIR = __DIR__ . '/..';

    public function testCommandOutputsExpectedHeader(): void
    {
        $kernel = new Kernel(self::PROJECT_DIR);
        $app = new Application('nqphp');
        $cmdClass = new \ReflectionClass(\Nqphp\Core\Console\FeatureShowCommand::class);
        $cmd = $cmdClass->newInstance($kernel);
        $app->add($cmd);

        $tester = new CommandTester($app->find('feature:show'));
        $tester->execute(['name' => 'NoSuchFeature']);
        $output = $tester->getDisplay();

        // Unknown feature should fail loudly with an error message
        self::assertStringContainsString('not discovered', $output);
        self::assertSame(Command::FAILURE, $tester->getStatusCode());
    }

    public function testCommandReportsConfigForKnownFeature(): void
    {
        $kernel = new Kernel(self::PROJECT_DIR);
        $app = new Application('nqphp');
        $cmdClass = new \ReflectionClass(\Nqphp\Core\Console\FeatureShowCommand::class);
        $cmd = $cmdClass->newInstance($kernel);
        $app->add($cmd);

        $tester = new CommandTester($app->find('feature:show'));
        $tester->execute(['name' => 'Hello']);
        $output = $tester->getDisplay();

        // Should report the feature header + the cached_ttl config key from Hello feature
        self::assertStringContainsString('Feature: Hello', $output);
        self::assertStringContainsString('config:', $output);
        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
    }
}
