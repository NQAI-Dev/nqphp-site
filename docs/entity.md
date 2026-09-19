# Entity & Database ORM

`nqphp` includes a zero-overhead typed ORM layer supporting SQLite and in-memory persistence out of the box.

## Defining an Entity

Map classes to tables using attributes `#[Entity]`, `#[Id]`, and `#[Column]`:

```php
declare(strict_types=1);

namespace App\Feature\Blog\Entity;

use Nqphp\Core\Attribute\Column;
use Nqphp\Core\Attribute\Entity;
use Nqphp\Core\Attribute\Id;

#[Entity('posts')]
class Post
{
    #[Id]
    #[Column('id', type: 'integer')]
    public ?int $id = null;

    #[Column('title', type: 'string')]
    public string $title;

    #[Column('content', type: 'text')]
    public string $content;

    #[Column('created_at', type: 'datetime')]
    public string $createdAt;
}
```

## Querying with EntityManager

The `EntityManager` provides fluent repository methods and query builders:

```php
use Nqphp\Core\Entity\EntityManager;

#[Controller]
class BlogController extends AbstractController
{
    #[Route('/posts', methods: ['GET'])]
    public function list(EntityManager $em): Response
    {
        $posts = $em->getRepository(Post::class)->findAll();
        return $this->json($posts);
    }

    #[Route('/posts/create', methods: ['POST'])]
    public function create(Request $request, EntityManager $em): Response
    {
        $post = new Post();
        $post->title = (string) $request->getPayload()->get('title');
        $post->content = (string) $request->getPayload()->get('content');
        $post->createdAt = date('Y-m-d H:i:s');

        $em->persist($post);
        $em->flush();

        return $this->json($post, 201);
    }
}
```
