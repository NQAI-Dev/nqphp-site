<?php

declare(strict_types=1);

namespace Nqphp\Core\Attribute;

/**
 * Mark a static method as a scheduled task.
 *
 * Scheduled tasks are auto-discovered by
 * Nqphp\Core\Scheduler\ScheduleDiscoverer from any class placed in:
 *   - src/Feature/{Name}/Scheduler/{File}.php   (feature-scoped schedules)
 *   - src/Core/Scheduler/{File}.php             (framework schedules, future)
 *
 * The method must be public static and accept no arguments
 * (matches Symfony Scheduler's RecurringMessage::call() contract).
 *
 * Examples:
 *   #[Schedule(cron: '0 * * * *', description: 'Clear expired cache entries every hour.')]
 *   public static function clearExpiredCache(): void { ... }
 *
 *   #[Schedule(cron: '@daily', description: 'Rotate logs daily.', name: 'logs:rotate')]
 *   public static function rotateLogs(): void { ... }
 */
#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
final class Schedule
{
    /**
     * @param string      $cron        5-field cron expression ("0 * * * *"), or "@hourly"/"@daily"/"@weekly"/etc.
     * @param string      $description One-line description shown in `bin/console schedule:list`.
     * @param string|null $name        Unique schedule name. Null = "{Class}::{method}".
     */
    public function __construct(
        public readonly string $cron,
        public readonly string $description = '',
        public readonly ?string $name = null,
    ) {
    }
}
