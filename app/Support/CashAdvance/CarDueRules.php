<?php

namespace App\Support\CashAdvance;

use Carbon\CarbonInterface;

/**
 * When is a Cash Advance Report (CAR) due, and is it overdue — PURE (no database).
 *
 * The deadline is `car_due_days` after the cash advance was approved. The DAY it is due is still on time; the day after, it is overdue
 * (the same rule CashAdvanceAmountService::reportDueStatus() has always used for the "late report" flag).
 *
 *   none       no deadline set (0 days) or the advance is not approved yet
 *   on_track   more than $soonDays days left
 *   due_soon   $soonDays days or fewer left (including the due day itself)
 *   overdue    past the due day
 */
final class CarDueRules
{
    public const NONE = 'none';
    public const ON_TRACK = 'on_track';
    public const DUE_SOON = 'due_soon';
    public const OVERDUE = 'overdue';

    /** Reminder stages — one message each, never the same one twice for a document. */
    public const STAGE_UPCOMING = 'upcoming';
    public const STAGE_DUE_TODAY = 'due_today';
    public const STAGE_OVERDUE = 'overdue';

    /** While overdue, repeat the reminder every this many days (after the first day late). */
    public const OVERDUE_REPEAT_DAYS = 7;

    /**
     * @return array{state:string, due_on:?\Carbon\CarbonImmutable, days_left:?int, days_late:int}
     */
    public static function status(?CarbonInterface $approvedAt, int $limitDays, CarbonInterface $today, int $soonDays = 3): array
    {
        if ($approvedAt === null || $limitDays <= 0) {
            return ['state' => self::NONE, 'due_on' => null, 'days_left' => null, 'days_late' => 0];
        }

        $dueOn = $approvedAt->toImmutable()->startOfDay()->addDays($limitDays);
        $daysLeft = (int) $today->toImmutable()->startOfDay()->diffInDays($dueOn, false);

        $state = match (true) {
            $daysLeft < 0           => self::OVERDUE,
            $daysLeft <= $soonDays  => self::DUE_SOON,
            default                 => self::ON_TRACK,
        };

        return ['state' => $state, 'due_on' => $dueOn, 'days_left' => $daysLeft, 'days_late' => max(0, -$daysLeft)];
    }

    /**
     * Which reminder, if any, goes out TODAY for a document in this state: the first one $soonDays before the deadline, one on the due
     * day, one the first day it is late, then one every week while it stays overdue. Anything else → null (no message today).
     */
    public static function reminderStage(array $status, int $soonDays = 3): ?string
    {
        if ($status['state'] === self::NONE) {
            return null;
        }

        $left = $status['days_left'];
        $late = $status['days_late'];

        return match (true) {
            $late >= 1 && ($late === 1 || $late % self::OVERDUE_REPEAT_DAYS === 0) => self::STAGE_OVERDUE,
            $left === 0                                                            => self::STAGE_DUE_TODAY,
            $soonDays > 0 && $left === $soonDays                                   => self::STAGE_UPCOMING,
            default                                                                => null,
        };
    }
}
