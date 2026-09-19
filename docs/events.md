# Event System & Listeners

`nqphp` features a decoupled event dispatcher based on PSR-14 event concepts and attribute-based auto-discovery.

## Core Events

The framework dispatches lifecycle events throughout request processing:
- `KernelRequestEvent`: Dispatched immediately when a request enters the kernel before route matching.
- `KernelResponseEvent`: Dispatched after controller execution to allow response mutation or header decoration.

## Creating Custom Events

Events are plain PHP objects:

```php
declare(strict_types=1);

namespace App\Feature\User\Event;

class UserRegisteredEvent
{
    public function __construct(
        public readonly int $userId,
        public readonly string $email
    ) {}
}
```

## Registering Listeners via Attributes

Annotate listener methods with `#[EventListener]`. The parameter typehint determines the event to listen for:

```php
namespace App\Feature\User\Listener;

use App\Feature\User\Event\UserRegisteredEvent;
use Nqphp\Core\Attribute\EventListener;

class SendWelcomeEmailListener
{
    #[EventListener]
    public function onUserRegistered(UserRegisteredEvent $event): void
    {
        // Deliver welcome email to $event->email
    }
}
```

## Dispatching Events

Inject `Nqphp\Core\Event\EventDispatcher` or access `$this->kernel->events()`:

```php
public function register(EventDispatcher $events): Response
{
    // ... user creation ...
    $events->dispatch(new UserRegisteredEvent($user->id, $user->email));

    return $this->json(['status' => 'registered']);
}
```
