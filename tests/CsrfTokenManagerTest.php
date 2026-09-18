<?php

declare(strict_types=1);

namespace Nqphp\Tests;

use Nqphp\Core\Security\CsrfTokenManager;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;

/**
 * Verifies the double-submit-cookie CSRF model: safe methods pass
 * without a token; state-changing requests require the cookie and
 * `X-CSRF-Token` header to match.
 */
final class CsrfTokenManagerTest extends TestCase
{
    public function testSafeMethodIsAlwaysValid(): void
    {
        $manager = new CsrfTokenManager();
        foreach (['GET', 'HEAD', 'OPTIONS'] as $method) {
            $request = Request::create('/foo', $method);
            self::assertTrue($manager->isValid($request), "$method should be safe");
            self::assertFalse($manager->isStateChanging($request));
        }
    }

    public function testStateChangingMethodWithoutCookieIsInvalid(): void
    {
        $manager = new CsrfTokenManager();
        $request = Request::create('/foo', 'POST', [], [], [], [], '{}');
        self::assertTrue($manager->isStateChanging($request));
        self::assertFalse($manager->isValid($request));
    }

    public function testStateChangingMethodWithMatchingHeaderIsValid(): void
    {
        $manager = new CsrfTokenManager();
        $token = bin2hex(random_bytes(32));
        $request = Request::create('/foo', 'POST', [], [CsrfTokenManager::COOKIE_NAME => $token], [], [], '{}');
        $request->headers->set(CsrfTokenManager::HEADER_NAME, $token);
        self::assertTrue($manager->isValid($request));
    }

    public function testMismatchedHeaderIsRejected(): void
    {
        $manager = new CsrfTokenManager();
        $request = Request::create('/foo', 'POST', [], [CsrfTokenManager::COOKIE_NAME => 'cookie-token'], [], [], '{}');
        $request->headers->set(CsrfTokenManager::HEADER_NAME, 'wrong-token');
        self::assertFalse($manager->isValid($request));
    }

    public function testBuildCookieSetsHttpOnlyFalse(): void
    {
        $manager = new CsrfTokenManager(secure: false);
        $request = Request::create('/foo', 'GET');
        $cookie = $manager->buildCookie($request);
        self::assertInstanceOf(Cookie::class, $cookie);
        self::assertSame(CsrfTokenManager::COOKIE_NAME, $cookie->getName());
        self::assertFalse($cookie->isHttpOnly(), 'CSRF cookie must be readable by JS');
        self::assertSame('lax', strtolower($cookie->getSameSite() ?? ''));
        self::assertNotEmpty($cookie->getValue());
    }

    public function testBuildCookieReusesExistingToken(): void
    {
        $manager = new CsrfTokenManager();
        $existing = 'preset-token-value';
        $request = Request::create('/foo', 'GET', [], [CsrfTokenManager::COOKIE_NAME => $existing]);
        self::assertSame($existing, $manager->buildCookie($request)->getValue());
    }
}
