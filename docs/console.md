# Console Commands & CLI

Console commands in `nqphp` are self-discovering and declared via the `#[AsCommand]` attribute.

## Defining a Command

```php
declare(strict_types=1);

namespace App\Feature\Hello\Command;

use Nqphp\Core\Attribute\AsCommand;

#[AsCommand(
    name: 'app:greet',
    description: 'Prints a friendly greeting to the terminal.'
)]
class GreetCommand
{
    public function __invoke(array $args): int
    {
        $name = $args[0] ?? 'World';
        echo "Hello, {$name}!\n";
        return 0;
    }
}
```

## Running Commands

Run commands via the application CLI binary:

```bash
php bin/console app:greet Developer
# Output: Hello, Developer!
```

## Built-in Framework Commands

- `list`: Inspect all registered commands and schedules.
- `feature:show`: Introspect active feature slices and their components.
- `entity:list`: List all discovered database entities and schemas.
- `schedule:list`: Review periodic cron schedules.
