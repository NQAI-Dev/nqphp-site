# Configuration & Feature Config

`nqphp` treats configuration as strongly-typed objects tied directly to features rather than unstructured associative arrays.

## Declaring Feature Configuration

```php
declare(strict_types=1);

namespace App\Feature\Mail\Config;

use Nqphp\Core\Attribute\ConfigKey;
use Nqphp\Core\Config\FeatureConfig;

class MailConfig extends FeatureConfig
{
    #[ConfigKey('host', default: 'localhost')]
    public string $host;

    #[ConfigKey('port', default: 587)]
    public int $port;

    #[ConfigKey('encryption', default: 'tls')]
    public string $encryption;
}
```

## Accessing Configuration

Feature configs are automatically hydrated from environment variables or `config/` files and can be autowired into services and controllers:

```php
#[Controller]
class MailerController extends AbstractController
{
    #[Route('/mail/test', methods: ['POST'])]
    public function test(MailConfig $config): Response
    {
        return $this->json([
            'host' => $config->host,
            'port' => $config->port,
        ]);
    }
}
```
