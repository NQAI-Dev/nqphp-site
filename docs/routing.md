# Routing & Attributes

Routing in `nqphp` is declarative and zero-overhead. Routes are defined directly above controller action methods using native PHP 8 attributes.

## Route Attribute Definition

Import the `#[Route]` attribute from `Nqphp\Core\Attribute\Route`:

```php
namespace App\Feature\Blog\Controller;

use Nqphp\Core\Attribute\Controller;
use Nqphp\Core\Attribute\Route;
use Symfony\Component\HttpFoundation\Response;

#[Controller]
class BlogController
{
    #[Route('/posts', name: 'post_list', methods: ['GET'])]
    public function list(): Response
    {
        return new Response('Blog post list');
    }
}
```

## Route Parameters & Constraints

You can capture URL segments using curly braces `{parameter}`. Route parameters are automatically passed as method arguments matching their names:

```php
#[Route('/posts/{id}', name: 'post_view', methods: ['GET'])]
public function show(int $id): Response
{
    return new Response("Viewing post #{$id}");
}

#[Route('/posts/{year}/{slug}', methods: ['GET'])]
public function archive(int $year, string $slug): Response
{
    return new Response("Archive for {$year}: {$slug}");
}
```

## HTTP Methods

Specify allowed HTTP methods in the `methods` array argument:

```php
// Only GET and HEAD requests
#[Route('/api/status', methods: ['GET', 'HEAD'])]

// POST action
#[Route('/api/submit', methods: ['POST'])]

// Multiple verbs on the same endpoint
#[Route('/profile', methods: ['GET', 'POST'])]
```

## Route Hooks: Before & After

You can attach lightweight interceptors to individual actions using `#[BeforeRoute]` and `#[AfterRoute]`:

```php
use Nqphp\Core\Attribute\BeforeRoute;

#[BeforeRoute([AuthCheck::class, 'verifyToken'])]
#[Route('/dashboard', methods: ['GET'])]
public function dashboard(): Response
{
    return new Response('Protected Dashboard');
}
```
