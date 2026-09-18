<?php

declare(strict_types=1);

namespace Nqphp\Core\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Base class for controllers. Provides render/redirect/json helpers.
 *
 * Subclasses override these to integrate templating; the framework
 * itself doesn't bundle a templating engine (use Twig, Plates, plain
 * PHP, or whatever fits the feature).
 */
abstract class AbstractController
{
    /**
     * Construct with a Kernel reference so subclasses can call
     * `$this->kernel->entityManager()`, `$this->kernel->config(...)`,
     * `$this->kernel->url(...)`, etc. without needing a separate
     * service container. The Kernel dispatches by passing itself
     * during controller instantiation via the controller-injection
     * helper (see Kernel::dispatch()).
     */
    /**
     * Kernel reference, injected via reflection by the framework after
     * construction. Formally declared (not a dynamic property) so
     * ReflectionClass::hasProperty('kernel') returns true and the
     * Kernel can setValue() on it for any AbstractController subclass.
     */
    protected ?\Nqphp\Core\Kernel\Kernel $kernel = null;

    public function __construct(?\Nqphp\Core\Kernel\Kernel $kernel = null)
    {
        // Kernel is optional at construction time. The framework
        // dispatches by setting the `kernel` property via reflection
        // right after `new $class()` for AbstractController subclasses,
        // so the property is non-null by the time any route handler
        // runs. Plain non-AbstractController controllers don't need
        // a Kernel reference and can construct without args.
        $this->kernel = $kernel;
    }

    /**
     * Return an HTML response. By default this just returns the body
     * verbatim. Override `render` in subclasses to integrate a
     * templating engine.
     */
    protected function render(string $body, int $status = 200, array $headers = []): Response
    {
        return new Response($body, $status, $headers);
    }

    protected function redirect(string $url, int $status = 302): RedirectResponse
    {
        return new RedirectResponse($url, $status);
    }

    protected function json(mixed $data, int $status = 200, array $headers = []): JsonResponse
    {
        return new JsonResponse($data, $status, $headers);
    }
}
