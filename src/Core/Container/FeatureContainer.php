<?php

declare(strict_types=1);

namespace Nqphp\Core\Container;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

/**
 * Loads per-feature Symfony DI definitions from
 * `src/Feature/{Name}/config/services.yaml` into a ContainerBuilder.
 *
 * The framework already ships a lightweight service registry
 * (ServiceDiscoverer + #[Service]) for `#[Service]`-annotated classes.
 * FeatureContainer is the FULL Symfony DI escape hatch: when a
 * feature needs constructor injection, factories, or autowiring
 * (not just lazy instantiation), drop a `services.yaml` next to the
 * feature's controllers and this loader picks it up.
 *
 * Usage:
 *
 *   $container = $kernel->featureContainer();  // ContainerBuilder, compiled
 *   $logger = $container->get('monolog.logger');
 *   // or autowire:
 *   $service = $container->get(\App\Feature\Auth\Service\MyService::class);
 *
 * Empty / missing services.yaml → feature just contributes nothing,
 * which is the default for most features (they use `#[Service]`).
 */
final class FeatureContainer
{
    /** @var string[] Top-level roots the loader walks for services.yaml. */
    private array $featureRoots;

    /**
     * @param string[] $featureRoots typically `[$projectDir . '/src/Feature']`
     */
    public function __construct(array $featureRoots)
    {
        $this->featureRoots = $featureRoots;
    }

    /**
     * Build a fresh ContainerBuilder with all per-feature services.yaml
     * definitions loaded. Each feature's yaml is loaded into a
     * namespaced container area so identical service ids across
     * different features don't collide (Symfony prefixes them
     * with the feature's namespace automatically).
     *
     * Callers may further mutate the returned ContainerBuilder
     * (e.g. compile() + a Cache pool) before using it.
     *
     * @return ContainerBuilder uncompiled, with all per-feature
     *                        services.yaml loaded.
     */
    public function build(): ContainerBuilder
    {
        $container = new ContainerBuilder();
        foreach ($this->featureRoots as $featureRoot) {
            if (!is_dir($featureRoot)) {
                continue;
            }
            $this->loadFromRoot($container, $featureRoot);
        }
        return $container;
    }

    /**
     * Load every `config/services.yaml` directly under `featureRoot`'s
     * subdirectories. Each feature contributes its yaml under the
     * feature name (so identical ids across features don't clash).
     */
    private function loadFromRoot(ContainerBuilder $container, string $featureRoot): void
    {
        $entries = scandir($featureRoot) ?: [];
        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $featureDir = $featureRoot . DIRECTORY_SEPARATOR . $entry;
            if (!is_dir($featureDir)) {
                continue;
            }
            $yamlPath = $featureDir . DIRECTORY_SEPARATOR . 'config'
                . DIRECTORY_SEPARATOR . 'services.yaml';
            if (!is_file($yamlPath)) {
                continue;  // feature ships no DI definitions — fine
            }
            $loader = new YamlFileLoader($container, new \Symfony\Component\Config\FileLocator($featureDir . '/config'));
            $loader->load('services.yaml');
        }
    }
}
