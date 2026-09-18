<?php

declare(strict_types=1);

namespace Nqphp\Core\Js;

use Symfony\Component\Filesystem\Path;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Serves JavaScript ES modules from two sources:
 *
 *   1. Framework-bundled modules under `src/Core/Js/Resources/`,
 *      addressable as `/_nqphp/js/{file}.js` (e.g. `nqphp-runtime.js`).
 *   2. Per-feature modules under each `src/Feature/{Name}/Resources/`,
 *      addressable as `/_nqphp/js/{Name}/{file}.js`.
 *
 * A request that does not resolve to a file is rejected with 404.
 * We never traverse outside the configured roots, so paths like
 * `../etc/passwd` cannot escape — the resolver normalizes and
 * verifies the resolved realpath is still under a configured root.
 *
 * The `Content-Type` is always `application/javascript` so browsers
 * treat the response as an ES module regardless of file extension
 * inside `Resources/` (we accept `.js` only).
 */
final class JsModuleServer
{
    /** @var string[] Absolute filesystem roots the server may read from. */
    private array $roots;

    /**
     * @param string[] $frameworkRoots Top-level roots whose first path
     *                                segment is treated as the module
     *                                name (e.g. `src/Core/Js/Resources`).
     * @param string[] $featureRoots   Roots that contain feature
     *                                subdirectories whose name scopes
     *                                the module (e.g. `src/Feature`).
     */
    public function __construct(
        private readonly array $frameworkRoots,
        private readonly array $featureRoots,
    ) {
        // Combine all readable roots for the canonical-path containment check.
        $this->roots = array_merge(
            array_map(fn (string $r) => $this->normalize($r), $frameworkRoots),
            array_map(fn (string $r) => $this->normalize($r), $featureRoots),
        );
    }

    /**
     * Resolve the framework-internal URL prefix that should be routed
     * to this server. The Kernel checks `$request->getPathInfo()` for
     * this prefix and, on a match, hands the remainder to `serve()`.
     */
    public static function urlPrefix(): string
    {
        return '/_nqphp/js/';
    }

    /**
     * Try to serve a JS module given a path relative to the JS URL
     * prefix. Returns `null` when no module matches — caller should
     * fall through to the next handler (typically the router).
     *
     * @param string $relativePath Path after `/js/`, e.g. `nqphp-runtime.js` or `Hello/client.js`.
     */
    public function serve(string $relativePath): ?Response
    {
        $relativePath = ltrim($relativePath, '/');
        if ($relativePath === '' || str_contains($relativePath, "\0")) {
            return null;
        }
        // Reject obvious traversal early — defence in depth.
        if (str_contains($relativePath, '..')) {
            return null;
        }

        // Framework module: `_nqphp/js/nqphp-runtime.js` → search the framework roots directly.
        if (!str_contains($relativePath, '/')) {
            foreach ($this->frameworkRoots as $root) {
                $absolute = $this->normalize($root) . DIRECTORY_SEPARATOR . $relativePath;
                if ($this->isUnderRoots($absolute) && is_file($absolute)) {
                    return $this->respond($absolute);
                }
            }
            return null;
        }

        // Feature module: `_nqphp/js/{Feature}/client.js`.
        // Resolves to `{featureRoot}/{Feature}/Resources/{file}.js` —
        // the `Resources/` segment is implicit in the URL convention so
        // the on-disk layout mirrors the documented vertical slice
        // (Controller/, Entity/, Job/, Command/, Resources/).
        foreach ($this->featureRoots as $featureRoot) {
            $featureRootNorm = $this->normalize($featureRoot);
            // The relative path has the form `{Feature}/{file}.js`.
            $parts = explode('/', $relativePath, 2);
            if (count($parts) !== 2) {
                continue;
            }
            [$feature, $file] = $parts;
            // Reject traversal pieces embedded inside feature name too.
            if ($feature === '' || $file === '' || str_contains($feature, '..') || str_contains($file, '..')) {
                continue;
            }
            $absolute = $featureRootNorm
                . DIRECTORY_SEPARATOR . $feature
                . DIRECTORY_SEPARATOR . 'Resources'
                . DIRECTORY_SEPARATOR . $file;
            if ($this->isUnderRoots($absolute) && is_file($absolute)) {
                return $this->respond($absolute);
            }
        }

        return null;
    }

    /**
     * Convenience helper for tests and routers — list every JS module
     * the server knows about, as `URL path => absolute filesystem path`.
     *
     * @return array<string,string>
     */
    public function discover(): array
    {
        $modules = [];
        foreach ($this->frameworkRoots as $root) {
            $rootNorm = $this->normalize($root);
            if (!is_dir($rootNorm)) {
                continue;
            }
            foreach (glob($rootNorm . DIRECTORY_SEPARATOR . '*.js') ?: [] as $file) {
                $name = basename($file);
                $modules[self::urlPrefix() . $name] = $file;
            }
        }
        foreach ($this->featureRoots as $featureRoot) {
            $featureRootNorm = $this->normalize($featureRoot);
            if (!is_dir($featureRootNorm)) {
                continue;
            }
            foreach (glob($featureRootNorm . DIRECTORY_SEPARATOR . '*' . DIRECTORY_SEPARATOR . 'Resources' . DIRECTORY_SEPARATOR . '*.js') ?: [] as $file) {
                $feature = basename(dirname(dirname($file)));
                $name = basename($file);
                $modules[self::urlPrefix() . $feature . '/' . $name] = $file;
            }
        }
        return $modules;
    }

    private function respond(string $absolute): Response
    {
        $body = file_get_contents($absolute);
        if ($body === false) {
            return new Response('', 500, ['content-type' => 'text/plain']);
        }
        return new Response(
            $body,
            200,
            [
                'content-type' => 'application/javascript; charset=utf-8',
                'cache-control' => 'public, max-age=300',
            ],
        );
    }

    private function normalize(string $path): string
    {
        // Resolve to a canonical, real path without requiring the file to exist.
        $real = realpath($path);
        return $real !== false ? $real : rtrim($path, DIRECTORY_SEPARATOR);
    }

    /**
     * Verify that the absolute path is contained under one of the
     * configured roots, preventing traversal escapes even if the early
     * `..` check above is bypassed.
     */
    private function isUnderRoots(string $absolute): bool
    {
        foreach ($this->roots as $root) {
            if ($root !== '' && str_starts_with($absolute, $root . DIRECTORY_SEPARATOR)) {
                return true;
            }
        }
        return false;
    }
}
