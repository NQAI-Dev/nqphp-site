<?php

declare(strict_types=1);

namespace Nqphp\Core\Service;

use Nqphp\Core\Attribute\Service as ServiceAttr;

/**
 * Auto-discovers #[Service]-annotated classes from:
 *   - src/Feature/<Feature>/Service/<File>.php   (project services)
 *   - src/Core/Service/*.php        (framework, future)
 *
 * Returns a name → descriptor map so Kernel::service() can lazy-load
 * by name. Same file-walking + force-autoload pattern as
 * CommandDiscoverer / ScheduleDiscoverer / MiddlewareDiscoverer.
 *
 * The descriptor carries a factory callable (the class name) plus
 * the scope flag, so the accessor knows whether to cache the
 * instance or build a new one each call.
 */
final class ServiceDiscoverer
{
    /** @var string[] */
    private array $serviceDirs;

    /** @var array<string, array{class: class-string, scope: string}> */
    private array $services = [];

    /**
     * @param string[] $serviceDirs
     */
    public function __construct(array $serviceDirs)
    {
        $this->serviceDirs = $serviceDirs;
    }

    public function discover(): self
    {
        $this->services = [];
        foreach ($this->serviceDirs as $dir) {
            if (!is_dir($dir)) {
                continue;
            }
            $this->scanDir($dir);
        }
        return $this;
    }

    /**
     * @return array<string, array{class: class-string, scope: string}>
     */
    public function all(): array
    {
        return $this->services;
    }

    public function has(string $name): bool
    {
        return isset($this->services[$name]);
    }

    /**
     * @return array{class: class-string, scope: string}|null
     */
    public function describe(string $name): ?array
    {
        return $this->services[$name] ?? null;
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
        $attrs = $ref->getAttributes(ServiceAttr::class);
        if (\count($attrs) === 0) {
            return;
        }
        /** @var ServiceAttr $svc */
        $svc = $attrs[0]->newInstance();
        $this->services[$svc->name] = [
            'class' => $fqcn,
            'scope' => $svc->scope,
        ];
    }
}
