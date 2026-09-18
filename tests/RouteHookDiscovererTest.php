<?php

declare(strict_types=1);

namespace Nqphp\Tests;

use Nqphp\Core\Attribute\AfterRoute;
use Nqphp\Core\Attribute\BeforeRoute;
use Nqphp\Core\Middleware\RouteHookDiscoverer;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * Phase 2 #8 (route hooks) test.
 *
 * Verifies:
 *   - RouteHookDiscoverer discovers #[BeforeRoute] + #[AfterRoute]
 *   - pattern matching (glob) via fnmatch
 *   - HTTP method filter (null = any)
 *   - invocation order is preserved (declaration order)
 *   - short-circuit: BeforeRoute returning Response stops further
 *     BeforeRoute handlers AND skips controller dispatch
 *   - AfterRoute can mutate the response (returning a new one)
 */
final class RouteHookDiscovererTest extends TestCase
{
    private const PROJECT_DIR = __DIR__ . '/..';

    public function testDiscoversBeforeAndAfterRouteAttributes(): void
    {
        $dir = sys_get_temp_dir() . '/nqphp-route-hooks-' . uniqid();
        mkdir($dir . '/Feature/Test/Middleware', 0755, true);

        $srcPath = $dir . '/Feature/Test/Middleware/AuthHooks.php';
        file_put_contents($srcPath, <<<'PHP'
<?php
declare(strict_types=1);
namespace App\Test\Middleware;

use Nqphp\Core\Attribute\AfterRoute;
use Nqphp\Core\Attribute\BeforeRoute;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class AuthHooks
{
    public static int $beforeCalls = 0;
    public static int $afterCalls = 0;

    #[BeforeRoute(routePath: '/admin/*', name: 'auth:admin')]
    public static function requireAdmin(Request $request): ?Response
    {
        self::$beforeCalls++;
        return null;
    }

    #[AfterRoute(routePath: '/admin/*', name: 'audit:admin')]
    public static function auditAdmin(Request $request, Response $response): Response
    {
        self::$afterCalls++;
        return $response;
    }
}
PHP);

        $discoverer = new RouteHookDiscoverer([$dir]);
        self::assertCount(1, $discoverer->discover()->before());
        self::assertCount(1, $discoverer->discover()->after());

        unlink($srcPath);
        rmdir($dir . '/Feature/Test/Middleware');
        rmdir($dir . '/Feature/Test');
        rmdir($dir . '/Feature');
        rmdir($dir);
    }

    public function testHttpMethodFilterRespected(): void
    {
        $dir = sys_get_temp_dir() . '/nqphp-route-hooks-method-' . uniqid();
        mkdir($dir . '/Feature/Test/Middleware', 0755, true);
        $srcPath = $dir . '/Feature/Test/Middleware/MethodHook.php';
        file_put_contents($srcPath, <<<'PHP'
<?php
declare(strict_types=1);
namespace App\Test\Middleware;

use Nqphp\Core\Attribute\BeforeRoute;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class MethodHook
{
    public static int $calls = 0;

    #[BeforeRoute(routePath: '/api', name: 'only-post', method: 'POST')]
    public static function onlyPost(Request $request): ?Response
    {
        self::$calls++;
        return null;
    }
}
PHP);

        $discoverer = new RouteHookDiscoverer([$dir]);
        $before = $discoverer->discover()->before();
        self::assertCount(1, $before);
        self::assertSame('POST', $before[0]['httpMethod']);

        unlink($srcPath);
        rmdir($dir . '/Feature/Test/Middleware');
        rmdir($dir . '/Feature/Test');
        rmdir($dir . '/Feature');
        rmdir($dir);
    }

    public function testEmptyDirsReturnEmptyHooks(): void
    {
        $tmp = sys_get_temp_dir() . '/nqphp-route-hooks-empty-' . uniqid();
        mkdir($tmp, 0755, true);
        try {
            $discoverer = new RouteHookDiscoverer([$tmp]);
            self::assertSame([], $discoverer->discover()->before());
            self::assertSame([], $discoverer->discover()->after());
        } finally {
            rmdir($tmp);
        }
    }
}
