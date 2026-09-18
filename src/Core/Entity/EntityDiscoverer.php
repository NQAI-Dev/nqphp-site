<?php

declare(strict_types=1);

namespace Nqphp\Core\Entity;

use Nqphp\Core\Attribute\Entity as EntityAttr;

/**
 * Auto-discovers #[Entity]-annotated classes from:
 *   - src/Feature/<Feature>/Entity/<File>.php   (project entities)
 *   - src/Core/Entity/*.php        (framework, future)
 *
 * Returns a name → class map so EntityManager can instantiate new
 * instances via `new $class()` and read/write public properties via
 * reflection (matching the attribute's "POPO with public props"
 * contract).
 *
 * Same file-walking + force-autoload pattern as CommandDiscoverer /
 * ScheduleDiscoverer / MiddlewareDiscoverer / ServiceDiscoverer.
 */
final class EntityDiscoverer
{
    /** @var string[] */
    private array $entityDirs;

    /** @var array<string, class-string> */
    private array $entities = [];

    /**
     * @param string[] $entityDirs
     */
    public function __construct(array $entityDirs)
    {
        $this->entityDirs = $entityDirs;
    }

    public function discover(): self
    {
        $this->entities = [];
        foreach ($this->entityDirs as $dir) {
            if (!is_dir($dir)) {
                continue;
            }
            $this->scanDir($dir);
        }
        return $this;
    }

    /**
     * @return array<string, class-string>
     */
    public function all(): array
    {
        return $this->entities;
    }

    public function has(string $name): bool
    {
        return isset($this->entities[$name]);
    }

    /**
     * @return class-string|null
     */
    public function describe(string $name): ?string
    {
        return $this->entities[$name] ?? null;
    }

    private function scanDir(string $dir): void
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }
            $this->loadFile((string) $file);
        }
    }

    private function loadFile(string $path): void
    {
        $contents = (string) file_get_contents($path);
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
            $this->loadClass($fqcn);
        }
    }

    private function loadClass(string $fqcn): void
    {
        $ref = new \ReflectionClass($fqcn);
        $attrs = $ref->getAttributes(EntityAttr::class);
        if (\count($attrs) === 0) {
            return;
        }
        /** @var EntityAttr $entity */
        $entity = $attrs[0]->newInstance();
        $this->entities[$entity->name] = $fqcn;
    }
}
