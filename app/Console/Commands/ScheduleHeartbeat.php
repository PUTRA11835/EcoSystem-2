<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Deliberately does nothing - its only job is to exist and be tracked by
 * ScheduleMonitorService like every other named scheduled task. Runs every
 * minute with the tightest staleness threshold on the page (see
 * config('schedule_monitor.commands.scheduler-heartbeat')).
 *
 * Why this needs to exist separately from the 7 real tasks: if the external
 * cron that's supposed to call `php artisan schedule:run` every minute stops
 * working (wrong path after a server migration, cron daemon down, container
 * never had it configured at all), EVERY task on the Schedule Monitor page
 * goes stale/never-run at once - and that looks identical to "7 unrelated
 * jobs all broke at the same time" unless there's one task whose only
 * possible failure mode IS the trigger itself. A trivial no-op can't fail
 * for a business-logic reason, so if this one goes stale, the problem is
 * provably the scheduler trigger, not application code.
 */
class ScheduleHeartbeat extends Command
{
    protected $signature = 'schedule-monitor:heartbeat';

    protected $description = 'No-op heartbeat proving the external cron is actually invoking schedule:run.';

    public function handle(): int
    {
        return self::SUCCESS;
    }
}
