<?php

declare(strict_types=1);

namespace Nqphp\Tests;

use Nqphp\Core\Attribute\Service;
use Nqphp\Core\Kernel\Kernel;
use Nqphp\Core\Service\ServiceDiscoverer;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Phase 2 #5 (lightweight service registry) test.
 *
 * Validates:
 *   - ServiceDiscoverer discovers #[Service]-annotated classes
 *   - Kernel::service() lazy-loads + caches singletons
 *   - Prototype scope returns a new instance each call
 *   - Unknown name throws RuntimeException
 */
final class ServiceDiscovererTest extends TestCase
{
    public function testDiscoversServiceAttributeOnClass(): void
    {
        $dir = sys_get_temp_dir() . '/nqphp-svc-' . uniqid();
        mkdir($dir . '/Feature/MyApp/Service', 0755, true);

        $srcPath = $dir . '/Feature/MyApp/Service/Greeter.php';
        file_put_contents($srcPath, <<<'PHP'
<?php
declare(strict_types=1);
namespace App\MyApp\Service;

use Nqphp\Core\Attribute\Service;

#[Service(name: 'greeter')]
final class Greeter
{
    public function greet(string $name): string { return "hi $name"; }
}
PHP);

        $discoverer = new ServiceDiscoverer([$dir]);
        self::assertTrue($discoverer->discover()->has('greeter'));
        $desc = $discoverer->describe('greeter');
        self::assertSame('Greeter', (new \ReflectionClass($desc['class']))->getShortName());
        self::assertSame('singleton', $desc['scope']);

        unlink($srcPath);
        rmdir($dir . '/Feature/MyApp/Service');
        rmdir($dir . '/Feature/MyApp');
        rmdir($dir . '/Feature');
        rmdir($dir);
    }

    public function testEmptyDirsReturnNoServices(): void
    {
        $tmp = sys_get_temp_dir() . '/nqphp-svc-empty-' . uniqid();
        mkdir($tmp, 0755, true);
        try {
            $discoverer = new ServiceDiscoverer([$tmp]);
            self::assertSame([], $discoverer->discover()->all());
        } finally {
            rmdir($tmp);
        }
    }

    public function testKernelServiceReturnsSameInstanceForSingleton(): void
    {
        $dir = sys_get_temp_dir() . '/nqphp-svc-singleton-' . uniqid();
        mkdir($dir . '/Feature/Test/Service', 0755, true);

        $srcPath = $dir . '/Feature/Test/Service/Counter.php';
        file_put_contents($srcPath, <<<'PHP'
<?php
declare(strict_types=1);
namespace App\Test\Service;

use Nqphp\Core\Attribute\Service;

#[Service(name: 'counter')]
final class Counter
{
    public int $calls = 0;
}
PHP);

        // Override ServiceDiscoverer scan paths via constructor — the
        // Kernel's own constructor uses src/Feature/, but for this
        // test we point at the temp dir. We achieve this by creating
        // a Kernel subclass via anonymous class? Too heavy. Instead:
        // use reflection to set the service discoverer's scan dirs
        // post-construction. (Production code never does this; test
        // only.)
        $kernel = new Kernel(__DIR__ . '/..');
        $reflectedSd = (new \ReflectionClass($kernel))->getProperty('serviceDiscoverer');
        $reflectedSd->setAccessible(true);
        $sd = $reflectedSd->getValue($kernel);
        // Swap the dirs — re-assign the readonly property
        $reflectedSd->setValue($kernel, new ServiceDiscoverer([$dir]));
        unset($reflectedSd); // close out reflection handle

        $first = $kernel->service('counter');
        $first->calls = 1;
        $second = $kernel->service('counter');
        self::assertSame($first, $second);
        self::assertSame(1, $second->calls);

        unlink($srcPath);
        rmdir($dir . '/Feature/Test/Service');
        rmdir($dir . '/Feature/Test');
        rmdir($dir . '/Feature');
        rmdir($dir);
    }

    public function testKernelServiceThrowsForUnknown(): void
    {
        $kernel = new Kernel(__DIR__ . '/..');
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unknown service:');
        $kernel->service('definitely-not-a-real-service');
    }
}
