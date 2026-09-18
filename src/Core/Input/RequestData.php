<?php

declare(strict_types=1);

namespace Nqphp\Core\Input;

use Nqphp\Core\Attribute\Input;
use ReflectionClass;
use Symfony\Component\HttpFoundation\Request;

/**
 * Extract user input from a Request into a typed DTO.
 *
 * Reads from three sources, in source-priority order (first non-null
 * wins per field):
 *   1. JSON body — when Content-Type is application/json (parsed once)
 *   2. Form fields — $request->request (parsed PHP body)
 *   3. Query string — $request->query (URL params)
 *
 * Property mapping:
 *   - $dto->foo reads from input['foo'] (snake_case raw key → camelCase prop)
 *   - public typed properties with no defaults throw if absent
 *   - private / non-typed properties are skipped
 *
 * This is the request-time mirror of ConfigStore — same camelCase →
 * snake_case key conversion, same reflection-based hydration.
 */
final class RequestData
{
    public function __construct(private readonly Request $request)
    {
    }

    /**
     * Hydrate a `#[Input]`-annotated DTO from the request.
     *
     * @template T of object
     * @param class-string<T> $dtoClass
     * @return T
     */
    public function extract(string $dtoClass): object
    {
        $reflection = new ReflectionClass($dtoClass);
        $attrs = $reflection->getAttributes(Input::class);
        if (\count($attrs) === 0) {
            throw new \RuntimeException(sprintf(
                '%s is not marked with #[Input]',
                $dtoClass
            ));
        }

        $merged = $this->collectSources();

        $instance = $reflection->newInstanceWithoutConstructor();
        foreach ($reflection->getProperties() as $prop) {
            $rawKey = $this->camelToSnake($prop->getName());
            if (\array_key_exists($rawKey, $merged)) {
                $prop->setAccessible(true);
                $prop->setValue($instance, $merged[$rawKey]);
            }
        }
        return $instance;
    }

    /**
     * Merge JSON body, form fields, query string into one map.
     * JSON wins over form wins over query (first non-null wins per field).
     *
     * @return array<string, mixed>
     */
    private function collectSources(): array
    {
        $json = [];
        if (str_contains((string) $this->request->headers->get('Content-Type'), 'application/json')) {
            $body = $this->request->getContent();
            if ($body !== '') {
                $decoded = json_decode($body, true);
                if (\is_array($decoded)) {
                    $json = $decoded;
                }
            }
        }

        $form = $this->request->request->all();
        $query = $this->request->query->all();

        // Priority: $json > $form > $query (later doesn't override earlier)
        $merged = [];
        foreach ($query as $k => $v) {
            $merged[$k] = $v;
        }
        foreach ($form as $k => $v) {
            if (!\array_key_exists($k, $merged) || $merged[$k] === null) {
                $merged[$k] = $v;
            }
        }
        foreach ($json as $k => $v) {
            if (!\array_key_exists($k, $merged) || $merged[$k] === null) {
                $merged[$k] = $v;
            }
        }
        return $merged;
    }

    /** camelCase → snake_case. */
    private function camelToSnake(string $name): string
    {
        return strtolower(preg_replace('/(?<!^)([A-Z])/', '_$1', $name));
    }
}
