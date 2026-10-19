<?php

namespace App\Services\CashAdvance;

use App\Models\CashAdvance\CashAdvance;
use App\Models\CashAdvance\CashAdvanceSetting;
use App\Models\Notification;
use App\Support\CashAdvance\CarDueRules;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Log;

/**
 * Reminds requesters to file the Cash Advance Report (CAR) of an approved, not-yet-reported cash advance: shortly before the deadline,
 * on the day, the first day it is late, then weekly while it stays overdue (CarDueRules decides which day gets which message).
 *
 * Run daily by `cash-advance:car-reminders`. Safe to run twice in a day: a requester gets at most one reminder per document per day,
 * decided from the notifications already sent. Does nothing while reminders are switched off or the deadline is 0 (none).
 */
class CarReminderService
{
    public const NOTIFICATION_TYPE = 'car_reminder';

    /**
     * @return array{checked:int, sent:int, items:array<int,array{request_no:string,employee_id:int,stage:string,days:int}>}
     */
    public function run(?CarbonInterface $today = null, bool $dryRun = false): array
    {
        $today ??= now();
        $settings = CashAdvanceSetting::current();
        $result = ['checked' => 0, 'sent' => 0, 'items' => []];

        if (!$settings->car_reminders_enabled || !$settings->hasCarDueLimit()) {
            return $result;
        }

        $soon = (int) $settings->car_reminder_days_before;

        // Only an advance the requester still has to report: approved, nothing filed (a report waiting for review is HR's turn now).
        CashAdvance::query()
            ->where('status', CashAdvance::STATUS_APPROVED)
            ->where('settlement_status', CashAdvance::SETTLE_UNREPORTED)
            ->whereNotNull('completed_at')
            ->orderBy('id')
            ->chunkById(200, function ($advances) use ($settings, $soon, $today, $dryRun, &$result) {
                foreach ($advances as $advance) {
                    $result['checked']++;
                    $status = CarDueRules::status($advance->completed_at, (int) $settings->car_due_days, $today, $soon);
                    $stage = CarDueRules::reminderStage($status, $soon);
                    if ($stage === null || $this->alreadyRemindedToday($advance, $today)) {
                        continue;
                    }

                    $result['items'][] = ['request_no' => $advance->request_no, 'employee_id' => (int) $advance->employee_id, 'stage' => $stage,
                        'days' => $stage === CarDueRules::STAGE_OVERDUE ? $status['days_late'] : (int) $status['days_left']];
                    if (!$dryRun) {
                        $this->send($advance, $stage, $status, $today);
                    }
                    $result['sent']++;
                }
            });

        return $result;
    }

    private function alreadyRemindedToday(CashAdvance $advance, CarbonInterface $today): bool
    {
        return Notification::query()
            ->where('type', self::NOTIFICATION_TYPE)
            ->where('employee_id', $advance->employee_id)
            ->whereDate('created_at', $today->toDateString())
            ->where('preview', 'like', '%' . $advance->request_no . '%')
            ->exists();
    }

    private function send(CashAdvance $advance, string $stage, array $status, CarbonInterface $today): void
    {
        $due = $status['due_on']?->format('d M Y');
        $message = match ($stage) {
            CarDueRules::STAGE_UPCOMING  => "Cash advance {$advance->request_no}: file your report (CAR) by {$due} — {$status['days_left']} day(s) left.",
            CarDueRules::STAGE_DUE_TODAY => "Cash advance {$advance->request_no}: your report (CAR) is due today ({$due}).",
            default                      => "Cash advance {$advance->request_no}: your report (CAR) is overdue by {$status['days_late']} day(s) (was due {$due}). Please file it now.",
        };

        try {
            $notification = new Notification([
                'employee_id' => $advance->employee_id,
                'type'        => self::NOTIFICATION_TYPE,
                'preview'     => $message,
                'link'        => '/general/my-cash-advance',
            ]);
            // Stamped with the day the run is for (today in production), so "already reminded today" is judged on that same day.
            $notification->created_at = $today;
            $notification->save();
        } catch (\Throwable $e) {
            // A failed reminder must not stop the others; it is logged so it is not lost silently.
            Log::error('Failed to send a CAR reminder.', ['cash_advance_id' => $advance->id, 'message' => $e->getMessage()]);
        }
    }
}
