<?php

declare(strict_types=1);

namespace Nqphp\Core\Config;

use Nqphp\Core\Attribute\ConfigKey;

/**
 * Auto-discovers #[ConfigKey]-annotated schema classes from:
 *   - src/Feature/{Name}/Config/*.php   (project config schemas)
 *   - src/Core/Config/*.php              (framework, future)
 *
 * Returns a map of `feature → schema class`. Same file-walking +
 * force-autoload pattern as the other discoverers in the framework.
 */
final class ConfigSchemaDiscoverer
{
    /** @var string[] */
    private array $schemaDirs;

    /** @var array<string, class-string> feature → schema class */
    private array $schemas = [];

    /**
     * @param string[] $schemaDirs
     */
    public function __construct(array $schemaDirs)
    {
        $this->schemaDirs = $schemaDirs;
    }

    public function discover(): self
    {
        $this->schemas = [];
        foreach ($this->schemaDirs as $dir) {
            if (!is_dir($dir)) {
                continue;
            }
            $this->scanDir($dir);
        }
        return $this;
    }

    /**
     * @return array<string, class-string>
     */
    public function all(): array
    {
        return $this->schemas;
    }

    /**
     * @return class-string|null
     */
    public function describe(string $feature): ?string
    {
        return $this->schemas[$feature] ?? null;
    }

    private function scanDir(string $dir): void
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }
            $this->loadFile((string) $file);
        }
    }

    private function loadFile(string $path): void
    {
        $contents = (string) file_get_contents($path);
        if (!preg_match('/^\s*namespace\s+([\w\\\\]+);/m', $contents, $ns)) {
            return;
        }
        $namespace = trim($ns[1], '\\');
        if (!preg_match_all('/(?:class|interface|trait)\s+(\w+)/', $contents, $classMatches)) {
            return;
        }
        foreach ($classMatches[1] as $className) {
            $fqcn = $namespace . '\\' . $className;
            if (!class_exists($fqcn)) {
                require_once $path;
            }
            if (!class_exists($fqcn)) {
                continue;
            }
            $this->loadClass($fqcn);
        }
    }

    private function loadClass(string $fqcn): void
    {
        $ref = new \ReflectionClass($fqcn);
        $attrs = $ref->getAttributes(ConfigKey::class);
        if (\count($attrs) === 0) {
            return;
        }
        /** @var ConfigKey $schema */
        $schema = $attrs[0]->newInstance();
        $this->schemas[$schema->feature] = $fqcn;
    }
}
