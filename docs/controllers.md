# Controllers

Controllers are plain classes marked with the `#[Controller]` attribute. nqphp discovers them in feature directories and registers their `#[Route]` methods automatically.

## Rendering

Views are built with typed tags (`Tag::div`, `Tag::h2`, `Tag::ul`, …) and wrapped in a layout:

```php
$layout = Tag::div(['class' => 'grid'], [
    $sidebar,
    $main,
]);

return new Response(Layout::render('Page title', $layout));
```

## Responses

Controller methods return a `Symfony\Component\HttpFoundation\Response`. Anything available from a standard Symfony application applies here too.
