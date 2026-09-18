<?php

declare(strict_types=1);

namespace Nqphp\Core\Attribute;

/**
 * Mark a class as a middleware component.
 *
 * Middlewares are auto-discovered by
 * Nqphp\Core\Middleware\MiddlewareDiscoverer from:
 *   - src/Feature/{Name}/Middleware/{File}.php   (feature-scoped)
 *   - src/Core/Middleware/{File}.php             (framework, future)
 *
 * A middleware class exposes a single public method named `handle`
 * (or the optional `method` argument) that accepts a Symfony Request
 * and returns either a Symfony Response (terminal — short-circuits
 * further processing) or null (continue to next middleware).
 *
 * Examples:
 *   #[Middleware(name: 'csrf', order: 50)]
 *   final class CsrfMiddleware {
 *       public function handle(Request $request): ?Response { ... }
 *   }
 *
 *   #[Middleware(name: 'cors', order: 0, method: 'process')]
 *   final class CorsMiddleware {
 *       public function process(Request $request): ?Response { ... }
 *   }
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final class Middleware
{
    /**
     * @param string $name   Unique middleware name.
     * @param int    $order  Sort order, low → high. Default 100.
     * @param string $method Method name on the class to invoke. Default "handle".
     */
    public function __construct(
        public readonly string $name,
        public readonly int $order = 100,
        public readonly string $method = 'handle',
    ) {
    }
}
