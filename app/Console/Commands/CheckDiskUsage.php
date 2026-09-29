<?php

namespace App\Console\Commands;

use App\Services\ScheduleMonitorService;
use Illuminate\Console\Command;

/**
 * Periodic sweep for server disk capacity crossing the configured
 * warning/critical threshold (config('schedule_monitor.disk_usage')). See
 * ScheduleMonitorService::checkDiskUsageAlerts() for the alerting/idempotency
 * logic.
 *
 * Deliberately unnamed in routes/console.php (no ->name()) for the same
 * reason as schedule-monitor:check-staleness - infrastructure for the
 * monitor, not a task the monitor should watch itself.
 */
class CheckDiskUsage extends Command
{
    protected $signature = 'schedule-monitor:check-disk-usage';

    protected $description = 'Alert admins when server disk usage crosses the configured warning/critical threshold.';

    public function handle(): int
    {
        $sent = ScheduleMonitorService::checkDiskUsageAlerts();

        if ($sent > 0) {
            $this->info("Sent {$sent} disk usage alert.");
        }

        return self::SUCCESS;
    }
}
