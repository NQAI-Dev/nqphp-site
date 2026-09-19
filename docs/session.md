# Session & State

Session management is built into `nqphp` via `SessionInterface`, fully isolated and mockable for tests.

## Using Session

Access the session via `$this->session()` in `AbstractController` or autowire `SessionInterface`:

```php
#[Controller]
class CartController extends AbstractController
{
    #[Route('/cart', methods: ['GET'])]
    public function view(SessionInterface $session): Response
    {
        $items = $session->get('cart_items', []);
        return $this->json(['items' => $items]);
    }

    #[Route('/cart/add', methods: ['POST'])]
    public function add(Request $request): Response
    {
        $session = $this->session();
        $items = $session->get('cart_items', []);
        $items[] = $request->getPayload()->get('item_id');
        $session->set('cart_items', $items);

        return $this->json(['count' => count($items)]);
    }
}
```
