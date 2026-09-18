<?php

declare(strict_types=1);

namespace Nqphp\Core\Config;

use ReflectionClass;

/**
 * Typed config accessor.
 *
 * Given a `#[ConfigKey]` schema class (e.g. BlogConfig), reads the
 * raw per-feature array from FeatureConfig and instantiates the
 * schema class with the keys mapped onto its public typed properties.
 *
 * Key → property mapping: the raw key is the property's snake_case
 * name; the schema property uses camelCase. E.g. raw key `cache_ttl`
 * maps onto BlogConfig::$cacheTtl.
 *
 * Missing keys fall back to the schema class's default value (the
 * class's own initialiser). Extra raw keys (not declared on the
 * schema) are silently dropped.
 */
final class ConfigStore
{
    public function __construct(private readonly FeatureConfig $raw)
    {
    }

    /**
     * @template T of object
     * @param class-string<T> $schemaClass
     * @return T
     */
    public function get(string $schemaClass): object
    {
        $reflection = new ReflectionClass($schemaClass);
        $attrs = $reflection->getAttributes(\Nqphp\Core\Attribute\ConfigKey::class);
        if (\count($attrs) === 0) {
            throw new \RuntimeException(sprintf(
                '%s is not marked with #[ConfigKey]',
                $schemaClass
            ));
        }
        /** @var \Nqphp\Core\Attribute\ConfigKey $schemaAttr */
        $schemaAttr = $attrs[0]->newInstance();
        $feature = $schemaAttr->feature;
        $raw = $this->raw->load()->all($feature);

        $instance = $reflection->newInstanceWithoutConstructor();
        foreach ($reflection->getProperties() as $prop) {
            $rawKey = $this->camelToSnake($prop->getName());
            if (\array_key_exists($rawKey, $raw)) {
                $prop->setAccessible(true);
                $prop->setValue($instance, $raw[$rawKey]);
            }
        }
        return $instance;
    }

    /** camelCase → snake_case. */
    private function camelToSnake(string $name): string
    {
        return strtolower(preg_replace('/(?<!^)([A-Z])/', '_$1', $name));
    }
}
