<?php

declare(strict_types=1);

namespace Nqphp\Core\Attribute;

/**
 * Mark a class as a controller and optionally provide a route prefix.
 *
 * Controllers are auto-discovered by the Kernel when placed in any
 * src/Feature/{Name}/Controller/ directory. The `prefix` argument is
 * prepended to every #[Route] in the class, useful for grouping routes
 * (e.g. #[Controller('/admin')] for admin/* routes).
 *
 * Example:
 *   #[Controller('/blog')]
 *   final class PostController {
 *       #[Route('/', methods: ['GET'], name: 'list')]
 *       public function list(): Response { ... }
 *   }
 *
 * Generates the route name "blog:list" and path "/blog/".
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final class Controller
{
    /**
     * @param string $prefix Path prefix prepended to every route in this class (without trailing slash).
     * @param string|null $namePrefix String prepended to every route name with ':' separator. Null = use prefix-derived name.
     */
    public function __construct(
        public readonly string $prefix = '',
        public readonly ?string $namePrefix = null,
    ) {
    }
}
