# Dependency Injection & Services

`nqphp` includes an automatic service container based on modern PHP 8.4 reflection and attributes.

## Declaring Services

Mark any class with `#[Service]` to make it available for autowiring:

```php
declare(strict_types=1);

namespace App\Feature\User\Service;

use Nqphp\Core\Attribute\Service;

#[Service]
class UserRepository
{
    public function find(int $id): ?User
    {
        // Database lookup...
    }
}
```

## Controller Autowiring

Services, interfaces, and core kernel components (`SessionInterface`, `EventDispatcher`, `Cache`) are injected directly into controller actions or constructors:

```php
#[Controller]
class UserController extends AbstractController
{
    #[Route('/user/{id}', methods: ['GET'])]
    public function show(int $id, UserRepository $users): Response
    {
        $user = $users->find($id);
        if (!$user) {
            throw new NotFoundHttpException();
        }

        return $this->json($user);
    }
}
```
