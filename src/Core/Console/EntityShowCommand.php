<?php

declare(strict_types=1);

namespace Nqphp\Core\Console;

use Nqphp\Core\Attribute\AsCommand;
use Nqphp\Core\Kernel\Kernel;
use ReflectionClass;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * `bin/console entity:show <name>` — single-entity detail report.
 *
 * Renders:
 *   - Entity name + FQCN
 *   - #[Id] property (primary key)
 *   - #[Column] properties with type/length/nullable
 *   - Has-no-#[Id] warning (mirrors the runtime check in EntityManager)
 *
 * Companion to `entity:list`; mirrors the same single-feature
 * shape as `feature:show <name>` for visual consistency in CI logs.
 */
#[AsCommand(name: 'entity:show', description: 'Show detailed info (#[Id] + #[Column]) for one entity.')]
final class EntityShowCommand extends Command
{
    public function __construct(private readonly Kernel $kernel)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('name', InputArgument::REQUIRED, 'The entity name (e.g. "user").');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $name = $input->getArgument('name');
        $entities = $this->kernel->entityDiscoverer()->discover()->all();

        if (!isset($entities[$name])) {
            $output->writeln(sprintf('<error>Entity "%s" not discovered.</error>', $name));
            $output->writeln('<comment>Known entities:</comment>');
            foreach ($entities as $known => $_) {
                $output->writeln('  ' . $known);
            }
            return self::FAILURE;
        }

        $class = $entities[$name];
        $output->writeln(sprintf('<info>Entity: %s</info>', $name));
        $output->writeln(sprintf('  class: %s', $class));

        $reflection = new ReflectionClass($class);
        $idProps = [];
        $columnProps = [];
        foreach ($reflection->getProperties() as $prop) {
            $idAttrs = $prop->getAttributes(\Nqphp\Core\Attribute\Id::class);
            $colAttrs = $prop->getAttributes(\Nqphp\Core\Attribute\Column::class);
            if (\count($idAttrs) > 0) {
                $idProps[] = $prop;
            }
            if (\count($colAttrs) > 0) {
                /** @var \Nqphp\Core\Attribute\Column $colAttr */
                $colAttr = $colAttrs[0]->newInstance();
                $columnProps[] = [$prop, $colAttr];
            }
        }

        // #[Id]
        $output->writeln('  #[Id] properties:');
        if (\count($idProps) === 0) {
            $output->writeln('    <comment>(none — EntityManager will refuse to persist this entity)</comment>');
        } else {
            foreach ($idProps as $prop) {
                $output->writeln(sprintf('    $%s : %s', $prop->getName(), (string) ($prop->getType() ?? 'mixed')));
            }
        }

        // #[Column]
        $output->writeln('  #[Column] properties:');
        if (\count($columnProps) === 0) {
            $output->writeln('    <comment>(none)</comment>');
        } else {
            foreach ($columnProps as [$prop, $col]) {
                $type = $col->type ?? (string) ($prop->getType() ?? 'mixed');
                $nullable = $col->nullable ? ' nullable' : '';
                $length = $col->length !== null ? sprintf(' length=%d', $col->length) : '';
                $colName = $col->name ?? $prop->getName();
                $output->writeln(sprintf('    $%s → `%s` (%s%s%s)', $prop->getName(), $colName, $type, $nullable, $length));
            }
        }

        return self::SUCCESS;
    }
}
