<?php

declare(strict_types=1);

namespace Nqphp\Core\Middleware;

use Nqphp\Core\Attribute\Middleware as MiddlewareAttr;

/**
 * Auto-discovers #[Middleware]-annotated classes from:
 *   - src/Feature/<Feature>/Middleware/<File>.php   (project)
 *   - src/Core/Middleware/<File>.php        (framework, future)
 *
 * Returns descriptors sorted by `order` ascending (low → high), so
 * the runner can call them in priority order.
 *
 * Same file-walking + force-autoload pattern as
 * Nqphp\Core\Scheduler\ScheduleDiscoverer and
 * Nqphp\Core\Console\CommandDiscoverer — Phase 1 has no PSR-4 lazy
 * loader, so this eager-scan approach is consistent.
 *
 * Each descriptor is a callable-array `[instance, method]` so
 * MiddlewareRunner can invoke them directly.
 */
final class MiddlewareDiscoverer
{
    /** @var string[] */
    private array $middlewareDirs;

    /** @var list<array{name: string, order: int, callable: array{0: object, 1: string}}> */
    private array $middlewares = [];

    /**
     * @param string[] $middlewareDirs
     */
    public function __construct(array $middlewareDirs)
    {
        $this->middlewareDirs = $middlewareDirs;
    }

    public function discover(): self
    {
        $this->middlewares = [];
        foreach ($this->middlewareDirs as $dir) {
            if (!is_dir($dir)) {
                continue;
            }
            $this->scanDir($dir);
        }
        \usort($this->middlewares, fn ($a, $b) => $a['order'] <=> $b['order']);
        return $this;
    }

    /**
     * @return list<array{name: string, order: int, callable: array{0: object, 1: string}}>
     */
    public function all(): array
    {
        return $this->middlewares;
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
        $attrs = $ref->getAttributes(MiddlewareAttr::class);
        if (\count($attrs) === 0) {
            return;
        }
        /** @var MiddlewareAttr $mw */
        $mw = $attrs[0]->newInstance();
        if (!method_exists($fqcn, $mw->method)) {
            return;
        }
        $this->middlewares[] = [
            'name' => $mw->name,
            'order' => $mw->order,
            'callable' => [new $fqcn(), $mw->method],
        ];
    }
}
