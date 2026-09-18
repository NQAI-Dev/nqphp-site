<?php

declare(strict_types=1);

namespace Nqphp\Core\Js;

use Nqphp\Core\Security\CsrfTokenManager;
use Symfony\Component\HttpFoundation\Request;

/**
 * Tiny value object that templates use to embed framework-runtime
 * metadata without hand-writing URLs.
 *
 * Typical HTML usage from a controller's `render()` body:
 *
 *     return $this->render(sprintf(
 *         '<!doctype html><script type="module" src="%s"></script>' .
 *         '<meta name="nqphp-csrf-cookie" content="%s">',
 *         $this->js->runtimeUrl(),
 *         $this->js->csrfCookieName(),
 *     ));
 *
 * The framework runtime (`nqphp-runtime.js`) reads these meta tags at
 * boot and exposes `csrf()`, `fetchJson()`, and `loadModule(name)` on
 * `window.nqphp`.
 */
final class JsRuntime
{
    public function __construct(
        private readonly CsrfTokenManager $csrf,
    ) {
    }

    /**
     * URL the browser should `<script type="module" src=...>` to load
     * the framework runtime helpers (`csrf`, `fetchJson`, …).
     */
    public function runtimeUrl(): string
    {
        return JsModuleServer::urlPrefix() . 'nqphp-runtime.js';
    }

    /**
     * Cookie name that holds the CSRF token. Mirrors the constant on
     * CsrfTokenManager so JS can read it without a server roundtrip.
     */
    public function csrfCookieName(): string
    {
        return CsrfTokenManager::COOKIE_NAME;
    }

    /**
     * Header name clients must send on state-changing requests.
     */
    public function csrfHeaderName(): string
    {
        return CsrfTokenManager::HEADER_NAME;
    }

    /**
     * Whether the current request is one we enforce CSRF on. Useful
     * for the controller to conditionally render a form's hidden field.
     */
    public function isStateChanging(Request $request): bool
    {
        return $this->csrf->isStateChanging($request);
    }
}
