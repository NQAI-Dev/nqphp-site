<?php

declare(strict_types=1);

namespace Nqphp\Core\Attribute;

/**
 * Mark a static method as a "before" middleware for specific routes.
 *
 * Complements the global #[Middleware] pipeline. A BeforeRoute
 * handler runs only when the matched route path matches the
 * `routePath` glob pattern — letting features opt specific routes
 * into specific behaviors (e.g. "auth required for /admin/* only",
 * "rate-limit only on POST /api/*").
 *
 * The method must be public static and accept a Symfony Request,
 * returning either a Symfony Response (terminal — short-circuit) or
 * null (continue). Same contract as #[Middleware] handle().
 *
 * Example (src/Feature/Auth/Schedule/AuthMiddleware.php — naming
 * convention varies; the attribute is what matters):
 *
 *   #[BeforeRoute(routePath: '/admin/*', name: 'auth:admin')]
 *   public static function requireAdmin(Request $request): ?Response {
 *       if (!$request->attributes->get('user_id')) {
 *           return new Response('login required', 401);
 *       }
 *       return null;
 *   }
 *
 * The runtime walks the discovered routes, finds the one matching
 * the current request, and checks for matching BeforeRoute handlers
 * before invoking the controller. Same for AfterRoute (post-dispatch).
 */
#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
final class BeforeRoute
{
    /**
     * @param string      $routePath Glob-style path pattern (e.g. "/admin/*", "/api/users/{id}").
     * @param string      $name      Unique handler name for ordering/logs.
     * @param string|null $method    HTTP method filter (null = any).
     */
    public function __construct(
        public readonly string $routePath,
        public readonly string $name,
        public readonly ?string $method = null,
    ) {
    }
}
