# Cache & State Stores

`nqphp` provides built-in in-process and PSR-compatible caching with TTL expiration support and key namespaces.

## Usage via Kernel

The `Cache` component is accessible directly from controllers or through the service container:

```php
namespace App\Feature\Catalog\Controller;

use Nqphp\Core\Attribute\Controller;
use Nqphp\Core\Attribute\Route;
use Nqphp\Core\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;

#[Controller]
class CatalogController extends AbstractController
{
    #[Route('/api/categories', methods: ['GET'])]
    public function categories(): Response
    {
        $cache = $this->kernel->cache();

        $data = $cache->get('catalog.categories');
        if ($data === null) {
            $data = $this->loadFromDatabase();
            // Store in cache for 3600 seconds (1 hour)
            $cache->set('catalog.categories', $data, 3600);
        }

        return $this->json($data);
    }
}
```

## Cache Namespaces

Namespaces isolate distinct cache segments and allow granular invalidation:

```php
$userCache = $cache->namespace('users');
$userCache->set('profile.1', $userData);

// Clear only the 'users' namespace segment
$userCache->clear();
```
