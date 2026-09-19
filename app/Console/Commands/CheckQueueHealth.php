<?php

namespace App\Console\Commands;

use App\Services\ScheduleMonitorService;
use Illuminate\Console\Command;

/**
 * Periodic sweep for a supervised queue worker (docker/supervisord.conf)
 * getting stuck or dying without crashing outright - a backlog in `jobs`
 * never shows up as a Failed Jobs row, since a job that's never been
 * attempted can't have "failed" yet. See ScheduleMonitorService::
 * checkQueueHealthAlerts() for the alerting/idempotency logic.
 *
 * Deliberately unnamed in routes/console.php (no ->name()) for the same
 * reason as schedule-monitor:check-staleness - infrastructure for the
 * monitor, not a task the monitor should watch itself.
 */
class CheckQueueHealth extends Command
{
    protected $signature = 'schedule-monitor:check-queue-health';

    protected $description = 'Alert admins when a supervised queue worker has a growing backlog (stuck or offline worker).';

    public function handle(): int
    {
        $sent = ScheduleMonitorService::checkQueueHealthAlerts();

        if ($sent > 0) {
            $this->info("Sent {$sent} queue health alert(s).");
        }

        return self::SUCCESS;
    }
}
