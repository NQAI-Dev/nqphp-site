<?php

declare(strict_types=1);

namespace Nqphp\Core\Console;

use Nqphp\Core\Attribute\AsCommand;
use Nqphp\Core\Kernel\Kernel;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * `bin/console entity:list` — list every #[Entity]-discovered domain
 * entity, sorted by entity name with a NAME/CLASS table layout.
 *
 * Discovered from src/Core/Console/ via #[AsCommand] (no manual
 * registration in bin/console). Mirror of the same-name inline
 * command that lived in bin/console before; promoted to a proper
 * standalone class so commands can be unit-tested with
 * CommandTester (see tests/EntityListCommandTest.php).
 */
#[AsCommand(name: 'entity:list', description: 'List all #[Entity]-discovered domain entities.')]
final class EntityListCommand extends Command
{
    public function __construct(private readonly Kernel $kernel)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $entities = $this->kernel->entityDiscoverer()->discover()->all();
        $output->writeln('<info>Discovered entities</info>');
        if (\count($entities) === 0) {
            $output->writeln('  <comment>(no entities — define one with #[Entity] under src/Feature/*/Entity/)</comment>');
            return self::SUCCESS;
        }
        $maxName = 0;
        foreach ($entities as $name => $class) {
            $maxName = max($maxName, \strlen($name));
        }
        $output->writeln(sprintf('  %-'.(int) $maxName.'s  %s', 'NAME', 'CLASS'));
        foreach ($entities as $name => $class) {
            $output->writeln(sprintf('  %-'.(int) $maxName.'s  %s', $name, $class));
        }
        return self::SUCCESS;
    }
}
