# Middleware Pipeline

`nqphp` implements an onion-architecture middleware pipeline compliant with PSR-15 concepts.

## Middleware Contract

Each middleware receives the current `Request` and a `$next` callable:

```php
declare(strict_types=1);

namespace App\Middleware;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthMiddleware
{
    public function __invoke(Request $request, callable $next): Response
    {
        if (!$request->headers->has('Authorization')) {
            return new Response('Unauthorized', 401);
        }

        return $next($request);
    }
}
```

## Error Boundary

The pipeline wraps every dispatch in a resilient error boundary, automatically formatting exceptions into clean JSON problem specs or custom error pages.
