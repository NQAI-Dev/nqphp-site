<?php

declare(strict_types=1);

namespace Nqphp\Core\Middleware;

use Nqphp\Core\Attribute\AfterRoute;
use Nqphp\Core\Attribute\BeforeRoute;

/**
 * Auto-discovers route-hook handlers (`#[BeforeRoute]`,
 * `#[AfterRoute]`) from static methods across:
 *   - src/Feature/<Feature>/Middleware/<File>.php   (project hooks live with middleware)
 *   - src/Core/Middleware/<File>.php        (framework, future)
 *
 * Returns two ordered lists: `before` and `after`. Each entry is
 * `{pattern, method, name, httpMethod}` — the runtime matches the
 * current route's path against `pattern` (glob-style via fnmatch).
 *
 * Same file-walking + force-autoload pattern as the other
 * discoverers in the framework.
 */
final class RouteHookDiscoverer
{
    /** @var string[] */
    private array $hookDirs;

    /** @var list<array{pattern: string, name: string, httpMethod: ?string, callable: array{0: class-string, 1: string}}> */
    private array $before = [];

    /** @var list<array{pattern: string, name: string, httpMethod: ?string, callable: array{0: class-string, 1: string}}> */
    private array $after = [];

    /**
     * @param string[] $hookDirs
     */
    public function __construct(array $hookDirs)
    {
        $this->hookDirs = $hookDirs;
    }

    public function discover(): self
    {
        $this->before = [];
        $this->after = [];
        foreach ($this->hookDirs as $dir) {
            if (!is_dir($dir)) {
                continue;
            }
            $this->scanDir($dir);
        }
        return $this;
    }

    /** @return list<array{pattern: string, name: string, httpMethod: ?string, callable: array{0: class-string, 1: string}}> */
    public function before(): array
    {
        return $this->before;
    }

    /** @return list<array{pattern: string, name: string, httpMethod: ?string, callable: array{0: class-string, 1: string}}> */
    public function after(): array
    {
        return $this->after;
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
        foreach ($ref->getMethods(\ReflectionMethod::IS_PUBLIC | \ReflectionMethod::IS_STATIC) as $method) {
            foreach ($method->getAttributes(BeforeRoute::class) as $attr) {
                /** @var BeforeRoute $hook */
                $hook = $attr->newInstance();
                $this->before[] = [
                    'pattern' => $hook->routePath,
                    'name' => $hook->name,
                    'httpMethod' => $hook->method,
                    'callable' => [$fqcn, $method->getName()],
                ];
            }
            foreach ($method->getAttributes(AfterRoute::class) as $attr) {
                /** @var AfterRoute $hook */
                $hook = $attr->newInstance();
                $this->after[] = [
                    'pattern' => $hook->routePath,
                    'name' => $hook->name,
                    'httpMethod' => $hook->method,
                    'callable' => [$fqcn, $method->getName()],
                ];
            }
        }
    }
}
