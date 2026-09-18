<?php

declare(strict_types=1);

namespace Nqphp\Tests;

use Nqphp\Core\Kernel\Kernel;
use Nqphp\Core\Routing\Router;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Exception\RouteNotFoundException;

/**
 * Tests for Kernel::url() and the underlying KernelUrlGenerator.
 *
 * Sets up an inline Controller + Router so we have named routes to
 * generate URLs for.
 */
final class KernelUrlGeneratorTest extends TestCase
{
    private const PROJECT_DIR = __DIR__ . '/..';

    public function testGeneratePathForNamedRoute(): void
    {
        $kernel = new Kernel(self::PROJECT_DIR);
        // Build a minimal named route: register it through the live
        // Router so Kernel::url() can resolve it.
        $router = $kernel->router;
        $routerClass = new \ReflectionClass($router);
        $routesProp = $routerClass->getProperty('routes');
        $routesProp->setAccessible(true);
        $collection = $routesProp->getValue($router);
        // Inject a single named route for the test
        $route = new \Symfony\Component\Routing\Route(
            '/users/{id}',
            ['_controller' => 'Test::show'],
            ['id' => '\d+'],
        );
        $collection->add('user:show', $route);

        // Default (path-only, no scheme/host)
        $url = $kernel->url('user:show', ['id' => 42]);
        self::assertStringContainsString('/users/42', $url);
        self::assertStringNotContainsString('http://', $url);  // path-only
    }

    public function testGenerateAbsoluteUrl(): void
    {
        $kernel = new Kernel(self::PROJECT_DIR);
        $router = $kernel->router;
        $routerClass = new \ReflectionClass($router);
        $routesProp = $routerClass->getProperty('routes');
        $routesProp->setAccessible(true);
        $collection = $routesProp->getValue($router);
        $route = new \Symfony\Component\Routing\Route('/health', ['_controller' => 'X::y']);
        $collection->add('health:check', $route);

        $url = $kernel->url('health:check', [], absolute: true);
        self::assertStringStartsWith('http://', $url);
        self::assertStringContainsString('/health', $url);
    }

    public function testGenerateWithSchemeOverride(): void
    {
        $kernel = new Kernel(self::PROJECT_DIR);
        $router = $kernel->router;
        $routerClass = new \ReflectionClass($router);
        $routesProp = $routerClass->getProperty('routes');
        $routesProp->setAccessible(true);
        $collection = $routesProp->getValue($router);
        $route = new \Symfony\Component\Routing\Route('/about', ['_controller' => 'X::y']);
        $collection->add('about:page', $route);

        $url = $kernel->url('about:page', [], absolute: true, override: ['scheme' => 'https', 'host' => 'example.com']);
        self::assertStringStartsWith('https://example.com', $url);
        self::assertStringContainsString('/about', $url);
    }

    public function testGenerateForUnknownRouteThrows(): void
    {
        $kernel = new Kernel(self::PROJECT_DIR);
        $this->expectException(RouteNotFoundException::class);
        $kernel->url('nonexistent:route', ['id' => 1]);
    }
}
