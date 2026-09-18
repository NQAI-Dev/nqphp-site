<?php

declare(strict_types=1);

namespace Nqphp\Feature\Ping\Middleware;

use Nqphp\Core\Attribute\Middleware;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

#[Middleware(name: 'ping_log', order: 900)]
final class PingLogMiddleware
{
    public function handle(Request $request): ?Response
    {
        if (str_starts_with($request->getPathInfo(), '/api/ping')) {
            error_log('PING hit: ' . $request->getMethod() . ' ' . $request->getPathInfo());
        }
        return null;
    }
}
