# Cache & State Stores

Фреймворк предоставляет встроенное in-process и PSR-совместимое кеширование с поддержкой TTL и пространств имен.

## Использование через Kernel

Компонент `Cache` доступен напрямую из контроллеров или через контейнер:

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
            // Сохраняем в кеш на 3600 секунд (1 час)
            $cache->set('catalog.categories', $data, 3600);
        }

        return $this->json($data);
    }
}
```

## Пространства имен (Namespaces)

Для изолированного сброса частей кеша:

```php
$userCache = $cache->namespace('users');
$userCache->set('profile.1', $userData);

// Очистка только пространства users
$userCache->clear();
```
