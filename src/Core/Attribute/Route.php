<?php

declare(strict_types=1);

namespace Nqphp\Core\Attribute;

/**
 * Mark a controller method as a route handler.
 *
 * Examples:
 *   #[Route('/', methods: ['GET'], name: 'list')]
 *   #[Route('/{id}', methods: ['GET'], name: 'show', requirements: ['id' => '\d+'])]
 *   #[Route('/', methods: ['POST'], name: 'create')]
 */
#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
final class Route
{
    /**
     * @param string $path Route path relative to the enclosing #[Controller] prefix.
     * @param string[] $methods HTTP methods accepted (e.g. ['GET'], ['POST', 'PUT']).
     * @param string|null $name Route name suffix. Null = use method name. Full name is "{prefix}:{name}".
     * @param array<string,string> $requirements Placeholder requirements (e.g. ['id' => '\d+']).
     */
    public function __construct(
        public readonly string $path,
        public readonly array $methods = ['GET'],
        public readonly ?string $name = null,
        public readonly array $requirements = [],
    ) {
    }
}
