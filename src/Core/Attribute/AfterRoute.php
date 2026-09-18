<?php

declare(strict_types=1);

namespace Nqphp\Core\Attribute;

/**
 * Mark a static method as an "after" middleware for specific routes.
 *
 * Same pattern as #[BeforeRoute] but runs AFTER the controller
 * dispatched (and after the global #[Middleware] pipeline).
 * Useful for post-processing the response: caching, audit logging,
 * header injection, etc.
 *
 * Method must be public static, accept the Symfony Request and
 * the Symfony Response, return a (possibly-modified) Response.
 */
#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
final class AfterRoute
{
    /**
     * @param string      $routePath Glob-style path pattern.
     * @param string      $name      Unique handler name.
     * @param string|null $method    HTTP method filter (null = any).
     */
    public function __construct(
        public readonly string $routePath,
        public readonly string $name,
        public readonly ?string $method = null,
    ) {
    }
}
