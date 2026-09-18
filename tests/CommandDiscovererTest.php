<?php

declare(strict_types=1);

namespace Nqphp\Tests;

use Nqphp\Core\Console\CommandDiscoverer;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;

/**
 * Verifies that CommandDiscoverer walks the Feature + Core trees,
 * picks up classes marked with #[AsCommand], and constructs Command
 * instances with the metadata from the attribute.
 */
final class CommandDiscovererTest extends TestCase
{
    public function testDiscoversHelloGreetCommand(): void
    {
        $discoverer = new CommandDiscoverer([
            __DIR__ . '/../src/Feature',
        ]);

        $commands = $discoverer->discover();

        self::assertNotEmpty($commands, 'expected at least one command to be discovered');
        $byName = [];
        foreach ($commands as $cmd) {
            $byName[$cmd->getName()] = $cmd;
        }
        self::assertArrayHasKey('hello:greet', $byName);
        self::assertInstanceOf(Command::class, $byName['hello:greet']);
        self::assertSame('Greet the world (or the name you pass).', $byName['hello:greet']->getDescription());
        self::assertContains('hi', $byName['hello:greet']->getAliases());
    }

    public function testIgnoresDirectoriesWithoutCommands(): void
    {
        // Point at a directory that has no Command/ subfolders — should not throw.
        $discoverer = new CommandDiscoverer([
            __DIR__ . '/../src/Core/Routing',
        ]);
        $commands = $discoverer->discover();
        self::assertSame([], $commands);
    }

    public function testCommandsAreReturnedSortedByName(): void
    {
        $discoverer = new CommandDiscoverer([
            __DIR__ . '/../src/Feature',
            __DIR__ . '/../src/Core',
        ]);
        $names = array_map(static fn (Command $c) => $c->getName(), $discoverer->discover());
        $sorted = $names;
        sort($sorted);
        self::assertSame($sorted, $names, 'discoverer should return commands in deterministic order');
    }
}
