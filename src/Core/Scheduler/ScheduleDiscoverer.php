<?php

declare(strict_types=1);

namespace Nqphp\Core\Scheduler;

use Nqphp\Core\Attribute\Schedule;
use Symfony\Component\Finder\Finder;

/**
 * Auto-discovers scheduled tasks from #[Schedule] attributes on
 * static methods across:
 *   - src/Feature/<Feature>/Scheduler/<File>.php   (project schedules)
 *   - src/Core/Scheduler/*.php        (framework schedules, future)
 *
 * Returns each task as a descriptor (name, cron, description, call
 * callable). The bin/console schedule:list command renders these
 * as a Symfony Console table; a future scheduler runner (Phase 2+)
 * will actually invoke them through Symfony Scheduler.
 *
 * Design: reflection-based, mirrors CommandDiscoverer's pattern
 * (which already shipped at 2be0a72). Both discoverers can co-exist
 * on the same Kernel — schedule discovery is a read-only metadata
 * pass at boot time.
 */
final class ScheduleDiscoverer
{
    /** @var string[] Directories scanned for scheduler classes. */
    private array $schedulerDirs;

    /** @var list<array{name: string, cron: string, description: string, callable: callable}> */
    private array $schedules = [];

    /**
     * @param string[] $schedulerDirs
     */
    public function __construct(array $schedulerDirs)
    {
        $this->schedulerDirs = $schedulerDirs;
    }

    /**
     * Walk each scheduler dir, reflection-load every class, collect
     * static methods marked with #[Schedule]. Returns the discoverer
     * (chainable) for fluent use.
     */
    public function discover(): self
    {
        $this->schedules = [];
        foreach ($this->schedulerDirs as $dir) {
            if (!is_dir($dir)) {
                continue;
            }
            $this->scanDir($dir);
        }
        return $this;
    }

    /**
     * @return list<array{name: string, cron: string, description: string, callable: callable}>
     */
    public function all(): array
    {
        return $this->schedules;
    }

    private function scanDir(string $dir): void
    {
        $finder = new Finder();
        $finder->files()->in($dir)->name('*.php');

        foreach ($finder as $file) {
            $path = (string) $file->getRealPath();
            $this->loadFile($path);
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
        foreach ($ref->getMethods(\ReflectionMethod::IS_PUBLIC | \ReflectionMethod::IS_STATIC) as $method) {
            foreach ($method->getAttributes(Schedule::class) as $attr) {
                /** @var Schedule $schedule */
                $schedule = $attr->newInstance();
                $name = $schedule->name ?? sprintf('%s::%s', $fqcn, $method->getName());
                $this->schedules[] = [
                    'name' => $name,
                    'cron' => $schedule->cron,
                    'description' => $schedule->description,
                    'callable' => [$fqcn, $method->getName()],
                ];
            }
        }
    }
}
