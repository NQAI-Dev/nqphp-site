<?php

declare(strict_types=1);

namespace Nqphp\Core\Attribute;

/**
 * Mark a class as a CLI command and describe its bin/console name,
 * description, and aliases.
 *
 * Commands are auto-discovered by Nqphp\Core\Console\CommandDiscoverer
 * from any class placed in:
 *   - src/Feature/{Name}/Command/{File}.php   (feature-scoped commands)
 *   - src/Core/Command/{File}.php             (framework commands)
 *
 * The class must extend Symfony\Component\Console\Command\Command.
 *
 * Examples:
 *   #[AsCommand(name: 'hello:greet', description: 'Greet a user.')]
 *   final class HelloGreetCommand extends Command { ... }
 *
 *   #[AsCommand(name: 'cache:clear', description: 'Clear cache.', aliases: ['cc'])]
 *   final class CacheClearCommand extends Command { ... }
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final class AsCommand
{
    /**
     * @param string $name        Command name as it appears in `bin/console <name>` (e.g. "hello:greet").
     * @param string $description One-line description shown in `bin/console list`.
     * @param string[] $aliases   Alternative names that also invoke this command.
     */
    public function __construct(
        public readonly string $name,
        public readonly string $description = '',
        public readonly array $aliases = [],
    ) {
    }
}
