<?php

declare(strict_types=1);

namespace Nqphp\Core\Attribute;

/**
 * Mark a class as a service discoverable by ServiceDiscoverer.
 *
 * Services are auto-discovered from:
 *   - src/Feature/{Name}/Service/{File}.php   (feature-scoped)
 *   - src/Core/Service/{File}.php             (framework, future)
 *
 * The class must have a public constructor that takes no arguments
 * (or a constructor that's safe to invoke with no args). The
 * Kernel::service() accessor lazily instantiates it on first
 * lookup and caches it (singleton scope).
 *
 * Examples:
 *   #[Service(name: 'cache:user')]
 *   final class UserCache { public function get(int $id): ?User { ... } }
 *
 *   #[Service(name: 'mailer', scope: 'prototype')]
 *   final class Mailer {
 *       public function __construct(public string $recipient) { ... }
 *   }
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final class Service
{
    /**
     * @param string $name  Unique service name (lowercase, namespace-prefixed).
     * @param string $scope 'singleton' (default — one instance, reused)
     *                       or 'prototype' (new instance each lookup).
     */
    public function __construct(
        public readonly string $name,
        public readonly string $scope = 'singleton',
    ) {
    }
}
