<?php

declare(strict_types=1);

namespace Nqphp\Tests;

use Nqphp\Core\Container\FeatureContainer;
use Nqphp\Core\Kernel\Kernel;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Phase 2 #5 (per-feature services.yaml via Symfony DI) test.
 *
 * Validates the loader finds services.yaml under `config/` subdirs of
 * each feature, and that the resulting ContainerBuilder exposes
 * those services.
 */
final class FeatureContainerTest extends TestCase
{
    public function testEmptyRootsReturnsEmptyContainer(): void
    {
        $tmp = sys_get_temp_dir() . '/nqphp-fc-empty-' . uniqid();
        mkdir($tmp, 0755, true);
        try {
            $loader = new FeatureContainer([$tmp]);
            $container = $loader->build();
            self::assertInstanceOf(ContainerBuilder::class, $container);
            self::assertFalse($container->has('any_service'));
        } finally {
            rmdir($tmp);
        }
    }

    public function testFeatureWithoutServicesYamlIsFine(): void
    {
        // Features without services.yaml shouldn't break the loader.
        $tmp = sys_get_temp_dir() . '/nqphp-fc-no-svcyaml-' . uniqid();
        mkdir($tmp . '/MyApp/Controller', 0755, true);
        // Note: NO config/services.yaml — just controllers etc.
        try {
            $loader = new FeatureContainer([$tmp]);
            $container = $loader->build();
            self::assertInstanceOf(ContainerBuilder::class, $container);
        } finally {
            rmdir($tmp . '/MyApp/Controller');
            rmdir($tmp . '/MyApp');
            rmdir($tmp);
        }
    }

    public function testLoadsServicesYamlFromFeatureConfig(): void
    {
        $tmp = sys_get_temp_dir() . '/nqphp-fc-svcyaml-' . uniqid();
        mkdir($tmp . '/MyApp/config', 0755, true);

        $yamlPath = $tmp . '/MyApp/config/services.yaml';
        file_put_contents($yamlPath, <<<'YAML'
parameters:
    app.myapp.greeting: 'Hello from MyApp'

services:
    myapp.greeter:
        class: stdClass
        public: true
YAML);

        try {
            $loader = new FeatureContainer([$tmp]);
            $container = $loader->build();
            self::assertTrue($container->has('myapp.greeter'));
            self::assertSame('Hello from MyApp', $container->getParameter('app.myapp.greeting'));
        } finally {
            unlink($yamlPath);
            rmdir($tmp . '/MyApp/config');
            rmdir($tmp . '/MyApp');
            rmdir($tmp);
        }
    }

    public function testKernelExposesFeatureContainer(): void
    {
        $kernel = new Kernel(__DIR__ . '/..');
        self::assertInstanceOf(ContainerBuilder::class, $kernel->featureContainer());
    }
}
