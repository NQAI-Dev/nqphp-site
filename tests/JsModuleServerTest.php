<?php

declare(strict_types=1);

namespace Nqphp\Tests;

use Nqphp\Core\Js\JsModuleServer;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * Verifies that JsModuleServer serves framework and feature modules
 * with the right MIME type, and refuses path-traversal escapes.
 */
final class JsModuleServerTest extends TestCase
{
    private string $tmpRoot;

    protected function setUp(): void
    {
        // Build a sandbox tree:
        //   $tmpRoot/framework/nqphp-runtime.js
        //   $tmpRoot/feature/Hello/Resources/client.js
        //   $tmpRoot/feature/Secret/Resources/private.js
        $this->tmpRoot = sys_get_temp_dir() . '/nqphp-js-' . bin2hex(random_bytes(4));
        mkdir($this->tmpRoot . '/framework', 0o755, true);
        mkdir($this->tmpRoot . '/feature/Hello/Resources', 0o755, true);
        mkdir($this->tmpRoot . '/feature/Secret/Resources', 0o755, true);
        file_put_contents($this->tmpRoot . '/framework/nqphp-runtime.js', "export const hello = 'framework';\n");
        file_put_contents($this->tmpRoot . '/feature/Hello/Resources/client.js', "export const hello = 'hello-feature';\n");
        file_put_contents($this->tmpRoot . '/feature/Secret/Resources/private.js', "export const secret = 'shh';\n");
    }

    protected function tearDown(): void
    {
        $this->rmRecursive($this->tmpRoot);
    }

    public function testServesFrameworkModule(): void
    {
        $server = new JsModuleServer(
            frameworkRoots: [$this->tmpRoot . '/framework'],
            featureRoots: [$this->tmpRoot . '/feature'],
        );
        $response = $server->serve('nqphp-runtime.js');
        self::assertInstanceOf(Response::class, $response);
        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('application/javascript', $response->headers->get('content-type'));
        self::assertStringContainsString("export const hello = 'framework'", (string) $response->getContent());
    }

    public function testServesFeatureModule(): void
    {
        $server = new JsModuleServer(
            frameworkRoots: [$this->tmpRoot . '/framework'],
            featureRoots: [$this->tmpRoot . '/feature'],
        );
        $response = $server->serve('Hello/client.js');
        self::assertInstanceOf(Response::class, $response);
        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString("export const hello = 'hello-feature'", (string) $response->getContent());
    }

    public function testReturnsNullForUnknownModule(): void
    {
        $server = new JsModuleServer(
            frameworkRoots: [$this->tmpRoot . '/framework'],
            featureRoots: [$this->tmpRoot . '/feature'],
        );
        self::assertNull($server->serve('does-not-exist.js'));
    }

    public function testRejectsTraversalAttempts(): void
    {
        $server = new JsModuleServer(
            frameworkRoots: [$this->tmpRoot . '/framework'],
            featureRoots: [$this->tmpRoot . '/feature'],
        );
        // Various flavors of `..` that should never escape the root.
        foreach (['../framework/nqphp-runtime.js', '..%2Fnqphp-runtime.js', '../Secret/Resources/private.js'] as $bad) {
            self::assertNull($server->serve($bad), "should reject: $bad");
        }
    }

    public function testDiscoverListsAllModules(): void
    {
        $server = new JsModuleServer(
            frameworkRoots: [$this->tmpRoot . '/framework'],
            featureRoots: [$this->tmpRoot . '/feature'],
        );
        $modules = $server->discover();
        $names = array_keys($modules);
        sort($names);
        self::assertSame([
            '/_nqphp/js/Hello/client.js',
            '/_nqphp/js/Secret/Resources/private.js',
            '/_nqphp/js/nqphp-runtime.js',
        ], $names);
    }

    public function testUrlPrefixIsStable(): void
    {
        self::assertSame('/_nqphp/js/', JsModuleServer::urlPrefix());
    }

    private function rmRecursive(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach (new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        ) as $entry) {
            $entry->isDir() ? rmdir((string) $entry) : unlink((string) $entry);
        }
        rmdir($dir);
    }
}
