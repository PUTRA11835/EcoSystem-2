<?php

namespace App\Console\Commands;

use App\Models\Notification;
use App\Models\Timesheet;
use App\Services\HolidayService;
use App\Services\ResourceTimelineService;
use Illuminate\Console\Command;

/**
 * Daily reminder (in-app bell) for consultants who have not logged any timesheet
 * today. Any timesheet dated today counts — including drafts. Skipped entirely on
 * weekends and public holidays (HolidayService), and at most one reminder per
 * consultant per day, so a manual re-run does not spam.
 */
class SendTimesheetReminders extends Command
{
    public const TYPE = 'timesheet_reminder';

    protected $signature = 'notifications:timesheet-reminders';

    protected $description = 'Remind consultants who have not filled in today\'s timesheet.';

    public function handle(HolidayService $holidays, ResourceTimelineService $resources): int
    {
        $today = today();

        if ($holidays->isNonWorkingDay($today)) {
            $this->info('Non-working day — no reminders sent.');
            return self::SUCCESS;
        }

        $consultantIds = $resources->consultantsQuery()->pluck('employee_id')->all();

        $filled = Timesheet::whereIn('employee_id', $consultantIds)
            ->whereDate('date', $today)
            ->distinct()
            ->pluck('employee_id')
            ->all();

        $alreadyReminded = Notification::where('type', self::TYPE)
            ->whereDate('created_at', $today)
            ->pluck('employee_id')
            ->all();

        $targets = array_diff($consultantIds, $filled, $alreadyReminded);

        foreach ($targets as $employeeId) {
            Notification::create([
                'employee_id' => $employeeId,
                'type'        => self::TYPE,
                'from_name'   => 'Timesheet',
                'preview'     => 'You have not filled in your timesheet for today (' . $today->format('d M Y') . '). Please log your working hours.',
                'link'        => '/calendar/timesheets',
                'is_read'     => false,
            ]);
        }

        $this->info('Consultants: ' . count($consultantIds)
            . ' | Filled: ' . count($filled)
            . ' | Reminders sent: ' . count($targets));

        return self::SUCCESS;
    }
}
