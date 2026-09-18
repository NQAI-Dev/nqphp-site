<?php

declare(strict_types=1);

namespace Nqphp\Core\Console;

use Nqphp\Core\Attribute\AsCommand;
use ReflectionClass;
use Symfony\Component\Console\Command\Command;

/**
 * Auto-discover CLI commands from `#[AsCommand]` attributes on classes
 * under feature `Command/` subdirectories and core `Command/`.
 *
 * Mirrors the routing discovery: we walk the file tree, parse each PHP
 * file's namespace + class declarations, force-autoload it, then look
 * for the AsCommand attribute. This avoids the chicken-and-egg of
 * building a full PSR-4 loader — at Phase 1 we don't have one yet.
 *
 * Returned instances are constructed with the default name + description
 * taken from the attribute. Symfony's Application takes care of the rest.
 */
final class CommandDiscoverer
{
    /** @var string[] */
    private array $commandDirs;

    /**
     * @param string[] $commandDirs Absolute directories to scan for commands.
     */
    public function __construct(array $commandDirs)
    {
        $this->commandDirs = $commandDirs;
    }

    /**
     * @return Command[]
     */
    public function discover(): array
    {
        $commands = [];
        foreach ($this->commandDirs as $dir) {
            $this->scanDir($dir, $commands);
        }
        // Stable ordering by command name — keeps `bin/console list` output deterministic.
        \usort($commands, fn (Command $a, Command $b) => $a->getName() <=> $b->getName());
        return $commands;
    }

    /**
     * @param Command[] $commands
     */
    private function scanDir(string $dir, array &$commands): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }
            $this->loadFile((string) $file, $commands);
        }
    }

    /**
     * @param Command[] $commands
     */
    private function loadFile(string $path, array &$commands): void
    {
        $contents = file_get_contents($path);
        if ($contents === false) {
            return;
        }
        if (!preg_match('/^\s*namespace\s+([\w\\\\]+);/m', $contents, $ns)) {
            return;
        }
        $namespace = trim($ns[1], '\\');
        if (!preg_match_all('/(?:class|interface|trait)\s+(\w+)/', $contents, $classMatches)) {
            return;
        }
        foreach ($classMatches[1] as $className) {
            $fqcn = $namespace . '\\' . $className;
            if (!class_exists($fqcn)) {
                require_once $path;
            }
            if (!class_exists($fqcn)) {
                continue;
            }
            $this->loadClass($fqcn, $commands);
        }
    }

    /**
     * @param Command[] $commands
     */
    private function loadClass(string $fqcn, array &$commands): void
    {
        if (!is_subclass_of($fqcn, Command::class)) {
            return;
        }
        $ref = new ReflectionClass($fqcn);
        if ($ref->isAbstract()) {
            return;
        }
        $attrs = $ref->getAttributes(AsCommand::class);
        if (\count($attrs) === 0) {
            return;
        }
        /** @var AsCommand $meta */
        $meta = $attrs[0]->newInstance();

        /** @var Command $instance */
        $instance = new $fqcn();
        $instance->setName($meta->name);
        if ($meta->description !== '') {
            $instance->setDescription($meta->description);
        }
        if ($meta->aliases !== []) {
            $instance->setAliases($meta->aliases);
        }
        $commands[] = $instance;
    }
}
