<?php

declare(strict_types=1);

namespace Nqphp\Tests;

use Nqphp\Core\Attribute\Schedule;
use Nqphp\Core\Scheduler\ScheduleDiscoverer;
use PHPUnit\Framework\TestCase;

/**
 * Phase 2 #4: ScheduleDiscoverer test.
 *
 * Phase 1 of ScheduleDiscoverer only validates:
 *   - discovers classes with #[Schedule] attributes
 *   - returns descriptor rows (name, cron, description, callable)
 *   - honours explicit name attribute (or falls back to Class::method)
 *
 * Phase 2+ (TODO): integration with symfony/scheduler for actual
 * task invocation. That's a separate `bin/console schedule:run`
 * command, not in scope here.
 */
final class ScheduleDiscovererTest extends TestCase
{
    public function testDiscoversEmptyWhenNoDirs(): void
    {
        $discoverer = new ScheduleDiscoverer(['/nonexistent/path']);
        self::assertSame([], $discoverer->discover()->all());
    }

    public function testDiscoversScheduleOnStaticMethod(): void
    {
        // Define an inline class with #[Schedule] attribute, point
        // ScheduleDiscoverer at the temp dir, and verify it picks up.
        $dir = sys_get_temp_dir() . '/nqphp-schedule-test-' . uniqid();
        mkdir($dir . '/Feature/Foo/Scheduler', 0755, true);

        $srcPath = $dir . '/Feature/Foo/Scheduler/Example.php';
        file_put_contents($srcPath, <<<'PHP'
<?php
declare(strict_types=1);
namespace App\Foo\Scheduler;

use Nqphp\Core\Attribute\Schedule;

final class Example
{
    #[Schedule(cron: '0 * * * *', description: 'Top of every hour.')]
    public static function topOfHour(): void {}
}
PHP);

        $discoverer = new ScheduleDiscoverer([$dir]);
        $schedules = $discoverer->discover()->all();

        self::assertCount(1, $schedules);
        self::assertSame('App\Foo\Scheduler\Example::topOfHour', $schedules[0]['name']);
        self::assertSame('0 * * * *', $schedules[0]['cron']);
        self::assertSame('Top of every hour.', $schedules[0]['description']);
        self::assertSame(['App\Foo\Scheduler\Example', 'topOfHour'], $schedules[0]['callable']);

        unlink($srcPath);
        rmdir($dir . '/Feature/Foo/Scheduler');
        rmdir($dir . '/Feature/Foo');
        rmdir($dir . '/Feature');
        rmdir($dir);
    }

    public function testHonoursExplicitNameAttribute(): void
    {
        $dir = sys_get_temp_dir() . '/nqphp-schedule-test-' . uniqid();
        mkdir($dir . '/Feature/Bar/Scheduler', 0755, true);

        $srcPath = $dir . '/Feature/Bar/Scheduler/Named.php';
        file_put_contents($srcPath, <<<'PHP'
<?php
declare(strict_types=1);
namespace App\Bar\Scheduler;

use Nqphp\Core\Attribute\Schedule;

final class Named
{
    #[Schedule(cron: '@daily', name: 'logs:rotate', description: 'Rotate logs daily.')]
    public static function rotateLogs(): void {}
}
PHP);

        $discoverer = new ScheduleDiscoverer([$dir]);
        $schedules = $discoverer->discover()->all();

        self::assertCount(1, $schedules);
        self::assertSame('logs:rotate', $schedules[0]['name']);
        self::assertSame('@daily', $schedules[0]['cron']);

        unlink($srcPath);
        rmdir($dir . '/Feature/Bar/Scheduler');
        rmdir($dir . '/Feature/Bar');
        rmdir($dir . '/Feature');
        rmdir($dir);
    }
}
