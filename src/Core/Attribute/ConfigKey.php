<?php

declare(strict_types=1);

namespace Nqphp\Core\Attribute;

/**
 * Mark a class as a typed config-schema holder for a feature.
 *
 * The class has public typed properties (the schema). At boot time,
 * ConfigSchemaDiscoverer walks src/Feature/{Name}/Config/{File}.php
 * + src/Core/Config/{File}.php, and reads the property type + default
 * value via reflection. The ConfigStore accessor in the Kernel
 * then serves per-feature config values typed at the right level.
 *
 * Example (src/Feature/Blog/Config/BlogConfig.php):
 *
 *   #[ConfigKey(feature: 'Blog')]
 *   final class BlogConfig {
 *       public int $cacheTtl = 60;
 *       public string $authorEmail = '';
 *       public bool $featureFlagsShowAuthor = true;
 *   }
 *
 * Then `Kernel::config(BlogConfig::class)` returns an instance with
 * the values from src/Feature/Blog/config.php (or defaults if absent).
 *
 * Convention: one ConfigKey class per feature, named `{Feature}Config`.
 * Properties use camelCase; the kernel lowercases the first letter
 * when looking up in the raw config array (which uses snake_case
 * keys from the config.php author).
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final class ConfigKey
{
    public function __construct(
        public readonly string $feature,
    ) {
    }
}
