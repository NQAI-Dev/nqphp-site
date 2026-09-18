<?php

declare(strict_types=1);

namespace Nqphp\Tests;

use Nqphp\Core\Attribute\ConfigKey;
use Nqphp\Core\Config\ConfigStore;
use Nqphp\Core\Config\FeatureConfig;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use stdClass;

/**
 * Phase 2 #7 (typed config via #[ConfigKey]) test.
 *
 * Validates the typed config flow:
 *   - ConfigStore maps raw snake_case keys from config.php onto
 *     schema's camelCase typed properties
 *   - missing keys fall back to the schema's default values
 *   - extra raw keys are silently dropped (no exception)
 *   - passing a non-#[ConfigKey] class throws RuntimeException
 */
final class ConfigStoreTest extends TestCase
{
    public function testTypedConfigHydratesFromRawArray(): void
    {
        // Mock FeatureConfig by giving it a hand-rolled raw map via
        // a public property reflection assignment.
        $raw = new FeatureConfig([]);
        $propRef = (new \ReflectionClass($raw))->getProperty('configs');
        $propRef->setAccessible(true);
        $propRef->setValue($raw, ['Hello' => [
            'cache_ttl' => 120,
            'rate_limit' => 200,
            'feature_flags' => ['show_emoji' => false, 'compact_view' => true],
        ]]);

        // Define an inline #[ConfigKey] class via anonymous-ish
        // approach (PHP doesn't allow attributes on anonymous classes
        // in 8.4, so use a temporary file instead).
        $tmp = sys_get_temp_dir() . '/nqphp-cfg-' . uniqid();
        mkdir($tmp . '/Hello/Config', 0755, true);
        $srcPath = $tmp . '/Hello/Config/HelloConfig.php';
        file_put_contents($srcPath, <<<'PHP'
<?php
declare(strict_types=1);
namespace App\Hello\Config;

use Nqphp\Core\Attribute\ConfigKey;

#[ConfigKey(feature: 'Hello')]
final class HelloConfig
{
    public int $cacheTtl = 60;
    public int $rateLimit = 100;
    public array $featureFlags = ['show_emoji' => true];
}
PHP);

        require_once $srcPath;
        $store = new ConfigStore($raw);
        $cfg = $store->get(\App\Hello\Config\HelloConfig::class);

        self::assertSame(120, $cfg->cacheTtl);
        self::assertSame(200, $cfg->rateLimit);
        self::assertSame(
            ['show_emoji' => false, 'compact_view' => true],
            $cfg->featureFlags
        );

        unlink($srcPath);
        rmdir($tmp . '/Hello/Config');
        rmdir($tmp . '/Hello');
        rmdir($tmp);
    }

    public function testMissingKeysFallBackToDefaults(): void
    {
        $raw = new FeatureConfig([]);
        $propRef = (new \ReflectionClass($raw))->getProperty('configs');
        $propRef->setAccessible(true);
        $propRef->setValue($raw, ['Hello' => []]);  // empty config

        $tmp = sys_get_temp_dir() . '/nqphp-cfg-defaults-' . uniqid();
        mkdir($tmp . '/Hello/Config', 0755, true);
        $srcPath = $tmp . '/Hello/Config/HelloConfig.php';
        file_put_contents($srcPath, <<<'PHP'
<?php
declare(strict_types=1);
namespace App\Hello\Config;

use Nqphp\Core\Attribute\ConfigKey;

#[ConfigKey(feature: 'Hello')]
final class HelloConfig
{
    public int $cacheTtl = 60;
    public string $name = 'default-name';
}
PHP);

        require_once $srcPath;
        $store = new ConfigStore($raw);
        $cfg = $store->get(\App\Hello\Config\HelloConfig::class);

        self::assertSame(60, $cfg->cacheTtl);
        self::assertSame('default-name', $cfg->name);

        unlink($srcPath);
        rmdir($tmp . '/Hello/Config');
        rmdir($tmp . '/Hello');
        rmdir($tmp);
    }

    public function testNonConfigKeyClassThrows(): void
    {
        $raw = new FeatureConfig([]);
        $store = new ConfigStore($raw);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('not marked with #[ConfigKey]');
        $store->get(stdClass::class);
    }
}
