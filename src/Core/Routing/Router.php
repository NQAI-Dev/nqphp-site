<?php

declare(strict_types=1);

namespace Nqphp\Core\Routing;

use Nqphp\Core\Attribute\Controller as ControllerAttr;
use Nqphp\Core\Attribute\Route as RouteAttr;
use ReflectionClass;
use Symfony\Component\Routing\RouteCollection;

/**
 * Auto-discover routes from #[Controller] + #[Route] attributes on
 * classes in src/Feature/{Name}/Controller/.
 *
 * The implementation is a thin wrapper around Symfony's built-in
 * attribute loader — we just point it at the Feature/ subdirectories
 * and collect the route definitions. Names are namespaced via the
 * #[Controller] prefix so routes don't collide between features.
 */
final class Router
{
    /** @var string[] Directories scanned for controllers (PSR-4-relative). */
    private array $controllerDirs;

    /** @var RouteCollection */
    private RouteCollection $routes;

    public function __construct(array $controllerDirs)
    {
        $this->controllerDirs = $controllerDirs;
        $this->routes = new RouteCollection();
    }

    public function discover(): RouteCollection
    {
        foreach ($this->controllerDirs as $dir) {
            $this->scanDir($dir);
        }
        return $this->routes;
    }

    private function scanDir(string $dir): void
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
            $this->loadFile((string) $file);
        }
    }

    private function loadFile(string $path): void
    {
        $contents = file_get_contents($path);
        if ($contents === false) {
            return;
        }
        // Strip the leading "<?php" tag so we can re-eval as a class
        // definition in a fresh namespace. We use Reflection on the
        // namespace + class names found in the file's source instead
        // — far simpler than building a full PSR-4 loader.
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
                // Force autoloading by requiring the file.
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
        $ref = new ReflectionClass($fqcn);
        $ctrlAttrs = $ref->getAttributes(ControllerAttr::class);
        if (\count($ctrlAttrs) === 0) {
            return;
        }
        $ctrl = $ctrlAttrs[0]->newInstance();
        $prefix = $ctrl->prefix;
        $namePrefix = $ctrl->namePrefix ?? $this->prefixToName($prefix);

        foreach ($ref->getMethods() as $method) {
            foreach ($method->getAttributes(RouteAttr::class) as $routeAttr) {
                /** @var RouteAttr $route */
                $route = $routeAttr->newInstance();
                $path = $prefix . $route->path;
                $name = ($namePrefix !== '' ? $namePrefix . ':' : '') . ($route->name ?? $method->getName());

                $symRoute = new \Symfony\Component\Routing\Route(
                    $path,
                    [
                        '_controller' => $fqcn . '::' . $method->getName(),
                        '_method' => $ref->getName() . '::' . $method->getName(),
                    ],
                    $route->requirements,
                    [], // options
                    '', // host
                    [], // schemes
                    $route->methods,
                    '' // condition
                );
                $this->routes->add($name, $symRoute);
            }
        }
    }

    private function prefixToName(string $prefix): string
    {
        // "/blog/posts" → "blog:posts"; "/" → ""; "" → ""
        return trim(str_replace('/', ':', trim($prefix, '/')), ':');
    }
}
