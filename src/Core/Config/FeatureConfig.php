<?php

declare(strict_types=1);

namespace Nqphp\Core\Config;

/**
 * Per-feature configuration.
 *
 * Each Feature in `src/Feature/{Name}/` can ship a `config.php` file
 * returning a `<?php array(...)` of arbitrary key→value pairs. The
 * kernel loads them all on construction and exposes them via
 * `Kernel::config(string $key, mixed $default = null)`.
 *
 * Use cases:
 *   - Per-feature cache TTLs
 *   - Per-feature rate limits
 *   - Per-feature feature flags
 *   - Per-feature API keys (loaded at boot, never re-read)
 *
 * Phase 2 #1 (lighter version of services.yaml autoloading — no
 * Symfony DI dependency yet). When full DI lands, this reader stays
 * as the data-only half; DI becomes the service-construction half.
 */
final class FeatureConfig
{
    /** @var array<string, array<string, mixed>> Feature name → config map */
    private array $configs = [];

    /** @var string[] */
    private array $featureDirs;

    /**
     * @param string[] $featureDirs Absolute directories to scan for
     *                              Feature/{Name}/config.php.
     */
    public function __construct(array $featureDirs)
    {
        $this->featureDirs = $featureDirs;
    }

    /**
     * Walk each feature dir, require `config.php` if present, merge
     * the returned array under the feature's name. Idempotent —
     * subsequent calls don't re-read the same files.
     *
     * @return self
     */
    public function load(): self
    {
        $this->configs = [];
        foreach ($this->featureDirs as $dir) {
            if (!is_dir($dir)) {
                continue;
            }
            $this->loadDir($dir);
        }
        return $this;
    }

    /**
     * Lookup: returns the value at `<featureName>.<key>` if present,
     * else $default. Feature names use the directory basename.
     */
    public function get(string $feature, string $key, mixed $default = null): mixed
    {
        return $this->configs[$feature][$key] ?? $default;
    }

    /**
     * All known feature names.
     *
     * @return string[]
     */
    public function features(): array
    {
        return array_keys($this->configs);
    }

    /**
     * Raw map for a single feature, useful for debugging via CLI.
     *
     * @return array<string, mixed>
     */
    public function all(string $feature): array
    {
        return $this->configs[$feature] ?? [];
    }

    private function loadDir(string $dir): void
    {
        // Direct subdir iteration is enough — Feature layout is flat:
        // src/Feature/Hello/config.php, src/Feature/Auth/config.php, etc.
        $entries = scandir($dir) ?: [];
        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $featureDir = $dir . DIRECTORY_SEPARATOR . $entry;
            if (!is_dir($featureDir)) {
                continue;
            }
            $configPath = $featureDir . DIRECTORY_SEPARATOR . 'config.php';
            if (!is_file($configPath)) {
                continue;
            }
            $data = require $configPath;
            if (!\is_array($data)) {
                continue;  // config.php returned non-array → silently skip
            }
            $this->configs[$entry] = $data;
        }
    }
}
