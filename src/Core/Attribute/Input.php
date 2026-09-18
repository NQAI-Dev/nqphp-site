<?php

declare(strict_types=1);

namespace Nqphp\Core\Attribute;

/**
 * Mark a class as an input DTO discoverable by RequestData.
 *
 * The class is a POPO with public typed properties representing the
 * shape of incoming user input. RequestData reads from:
 *   - query string (`?foo=bar` → $foo)
 *   - form fields (`application/x-www-form-urlencoded` body)
 *   - JSON body (parsed when Content-Type is application/json)
 *
 * Source order: JSON > form > query (later sources don't override
 * earlier ones — first non-null wins). Override at call-site via
 * RequestData::fromRequest() if needed.
 *
 * Example (src/Feature/Blog/Input/CreatePostInput.php):
 *
 *   #[Input(name: 'blog:create-post')]
 *   final class CreatePostInput {
 *       public string $title = '';
 *       public string $body = '';
 *       public int $authorId = 0;
 *       public array $tags = [];
 *   }
 *
 * Then in the controller:
 *
 *   public function createPost(Request $request): Response {
 *       $input = $this->kernel->input($request, CreatePostInput::class);
 *       // $input->title, $input->body, etc. are filled from the request.
 *   }
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final class Input
{
    /**
     * @param string $name Canonical handle (lowercase, namespace-prefixed).
     */
    public function __construct(
        public readonly string $name,
    ) {
    }
}
