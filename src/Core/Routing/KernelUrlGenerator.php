<?php

declare(strict_types=1);

namespace Nqphp\Core\Routing;

use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\RouteCollection;

/**
 * Typed wrapper around Symfony's UrlGenerator for the framework's
 * RouteCollection.
 *
 * Usage from a controller:
 *
 *   return $this->redirect($this->kernel->url('admin:post:show',
 *       ['id' => 42]));
 *
 *   // → /admin/post/42
 *
 * Or with absolute URLs (e.g. for emails):
 *
 *   $link = $this->kernel->url('blog:post:show', ['slug' => 'hello'],
 *       absolute: true);
 *
 * The generator is built fresh on every call (RouteCollection is
 * already cached by the Router; constructing a UrlGenerator over
 * it is cheap). When callers need to override the scheme/host (e.g.
 * for canonical URLs in a different domain), pass them via the
 * `?` shape — see ::generate().
 *
 * Throws \Symfony\Component\Routing\Exception\RouteNotFoundException
 * if the named route doesn't exist (delegated from Symfony).
 */
final class KernelUrlGenerator
{
    public function __construct(private readonly RouteCollection $routes)
    {
    }

    /**
     * Generate a URL for a named route.
     *
     * @param string                       $name     the route name (e.g. "blog:post:show")
     * @param array<string, mixed>         $params   path placeholders + optional query strings:
     *                                              value=foo → ?foo=value (appended as query);
     *                                              any other key with leading "_" → ignored.
     * @param bool                         $absolute true → full URL with scheme + host,
     *                                              false (default) → path only.
     * @param array{scheme?: string, host?: string, https?: bool}|null $override
     *                                              scheme/host override (used by absolute URLs
     *                                              in different domains / for canonicalisation).
     * @return string
     */
    public function generate(
        string $name,
        array $params = [],
        bool $absolute = false,
        ?array $override = null,
    ): string {
        // Separate path placeholders from query strings: Symfony's
        // UrlGenerator treats keys with leading "_" as ignored, and
        // we use that convention for query parameters ("?foo=bar").
        // Any param key not matching the route's placeholders becomes
        // a query string automatically.
        $context = new RequestContext('', $override['scheme'] ?? '', $override['host'] ?? '');
        if (isset($override['https'])) {
            $context->setHttpsPort($override['https'] ? 443 : 80);
            $context->setHttpPort($override['https'] ? 80 : 443);
        }
        $generator = new UrlGenerator($this->routes, $context);
        $referenceType = $absolute
            ? \Symfony\Component\Routing\Generator\UrlGeneratorInterface::ABSOLUTE_URL
            : \Symfony\Component\Routing\Generator\UrlGeneratorInterface::ABSOLUTE_PATH;
        return $generator->generate($name, $params, $referenceType);
    }
}
