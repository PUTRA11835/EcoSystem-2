<?php

namespace App\Console\Commands;

use App\Services\ScheduleMonitorService;
use Illuminate\Console\Command;

/**
 * Periodic sweep so a scheduled task going silent (not merely failing, but
 * simply never firing) reaches an admin without anyone having to open
 * Control Center -> Schedule Monitor. See ScheduleMonitorService::
 * checkStaleness() for the alerting/idempotency logic and the caveat that
 * this cannot detect the scheduler itself being entirely offline.
 *
 * Deliberately unnamed in routes/console.php (no ->name()) so this command
 * is invisible to Schedule Monitor's own dashboard - it is infrastructure
 * for the monitor, not a task the monitor should watch itself.
 */
class CheckScheduleStaleness extends Command
{
    protected $signature = 'schedule-monitor:check-staleness';

    protected $description = 'Alert admins the first time a known scheduled task goes stale (has not completed within its expected window).';

    public function handle(): int
    {
        $sent = ScheduleMonitorService::checkStaleness();

        if ($sent > 0) {
            $this->info("Sent {$sent} staleness alert(s).");
        }

        return self::SUCCESS;
    }
}
