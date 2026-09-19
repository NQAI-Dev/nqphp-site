# Validation & Input DTOs

`nqphp` supports typed request validation directly into Data Transfer Objects using PHP attributes.

## Defining Input DTO

```php
declare(strict_types=1);

namespace App\Feature\Auth\Dto;

use Nqphp\Core\Validation\Assert\Email;
use Nqphp\Core\Validation\Assert\Length;
use Nqphp\Core\Validation\Assert\NotBlank;

class RegisterInput
{
    #[NotBlank]
    #[Email]
    public string $email;

    #[NotBlank]
    #[Length(min: 8)]
    public string $password;
}
```

## Validating in Controllers

Use the `$this->validate()` helper. If validation fails, `nqphp` immediately returns a `422 Unprocessable Entity` with `application/problem+json`:

```php
#[Controller]
class AuthController extends AbstractController
{
    #[Route('/register', methods: ['POST'])]
    public function register(Request $request): Response
    {
        $dto = $this->validate($request, RegisterInput::class);

        // $dto is fully hydrated and valid
        return $this->json(['status' => 'ok']);
    }
}
```
