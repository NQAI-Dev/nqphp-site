# Controllers & Actions

In `nqphp`, a controller is a plain PHP class annotated with `#[Controller]`. It handles incoming requests, orchestrates business logic, and returns an HTTP `Response`.

## AbstractController Base

Extending `AbstractController` gives you access to built-in convenience methods for responses, JSON serialization, validation, and session access.

```php
<?php

declare(strict_types=1);

namespace App\Feature\Catalog\Controller;

use Nqphp\Core\Attribute\Controller;
use Nqphp\Core\Attribute\Route;
use Nqphp\Core\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

#[Controller]
class ProductController extends AbstractController
{
    #[Route('/products', methods: ['GET'])]
    public function index(): Response
    {
        $products = [
            ['id' => 1, 'name' => 'Server License', 'price' => 120],
            ['id' => 2, 'name' => 'Support Plan', 'price' => 50],
        ];

        return $this->json(['data' => $products]);
    }
}
```

## Helper Methods in AbstractController

- **`$this->json(mixed $data, int $status = 200, array $headers = [])`**: Returns a typed `JsonResponse` with automatic header and UTF-8 encoding.
- **`$this->validate(Request $request, string $dtoClass)`**: Hydrates and validates input DTO; throws `ValidationException` on failure (rendered as 422 Problem JSON).
- **`$this->session()`**: Returns `SessionInterface` for accessing and manipulating current session state.
- **`$this->kernel`**: Direct access to the active kernel instance for introspection.

## Dependency Injection in Action Methods

All action methods support automatic parameter resolution:

1. **Route variables**: Any `{name}` matched from the route path.
2. **Current Request**: Typehint `Symfony\Component\HttpFoundation\Request`.
3. **Core Services**: `SessionInterface`, `EventDispatcher`, `Cache`.
4. **Application Services**: Any class marked with `#[Service]`.

```php
#[Route('/order/{orderId}/pay', methods: ['POST'])]
public function pay(
    int $orderId,
    Request $request,
    PaymentService $payment,
    SessionInterface $session
): Response {
    $userId = $session->get('user_id');
    $payment->charge($userId, $orderId);

    return $this->json(['status' => 'paid']);
}
```
