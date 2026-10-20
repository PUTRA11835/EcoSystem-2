<?php

namespace App\Console\Commands;

use App\Services\CashAdvance\CarReminderService;
use Illuminate\Console\Command;

/**
 * Daily: remind requesters to file the report (CAR) of an approved cash advance — before the deadline, on the day, and while overdue.
 * The deadline and the switches are in Cash Advance → Settings → Settlement Rules. `--dry-run` lists who WOULD be reminded without
 * sending anything.
 */
class CashAdvanceCarReminders extends Command
{
    protected $signature = 'cash-advance:car-reminders {--dry-run : List the reminders without sending them}';

    protected $description = 'Remind requesters to file the report (CAR) of an approved cash advance — before the deadline, on the day and while overdue';

    public function handle(CarReminderService $reminders): int
    {
        $dry = (bool) $this->option('dry-run');
        $result = $reminders->run(null, $dry);

        foreach ($result['items'] as $item) {
            $this->line(sprintf('%s  employee #%d  %s (%d day(s))', $item['request_no'], $item['employee_id'], $item['stage'], $item['days']));
        }
        $this->info(sprintf('%d cash advance(s) checked, %d reminder(s) %s.', $result['checked'], $result['sent'], $dry ? 'would be sent (dry run)' : 'sent'));

        return self::SUCCESS;
    }
}
