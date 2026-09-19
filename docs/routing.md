# Routing

Routes are declared with the `#[Route]` attribute on controller methods. Controllers are discovered automatically from feature directories.

```php
#[Controller]
class BlogController
{
    #[Route('/blog/{slug}', name: 'blog_show', methods: ['GET'])]
    public function show(string $slug): Response
    {
        // ...
    }
}
```

## Named routes

Every route has a `name`, which can be used to generate URLs instead of hardcoding paths.

## HTTP methods

Restrict a route with `methods: ['GET']`, `['POST']`, and so on. A route without a `methods` option accepts any method.
