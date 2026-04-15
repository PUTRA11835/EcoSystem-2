<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class ReportingPeriod extends Model
{
    protected $fillable = ['year', 'month', 'closed_at', 'closed_by'];

    protected $casts = [
        'closed_at' => 'datetime',
    ];

    // ── Period helpers ────────────────────────────────────────────────────

    /**
     * Compute the period (year, month) for a given date.
     * Rule: day 21 of month M → day 20 of month M+1  = "Period M".
     * Dates 1–20 of month M belong to period M-1.
     * Dates 21–31 of month M belong to period M.
     */
    public static function periodFor(Carbon $date): array
    {
        if ($date->day >= 21) {
            return ['year' => $date->year, 'month' => $date->month];
        }
        $prev = $date->copy()->subMonthNoOverflow();
        return ['year' => $prev->year, 'month' => $prev->month];
    }

    /**
     * Return the date range (start, end) for a given period month/year.
     */
    public static function dateRange(int $year, int $month): array
    {
        $start = Carbon::create($year, $month, 21)->startOfDay();
        // end = day 20 of month+1
        if ($month === 12) {
            $end = Carbon::create($year + 1, 1, 20)->endOfDay();
        } else {
            $end = Carbon::create($year, $month + 1, 20)->endOfDay();
        }
        return ['start' => $start, 'end' => $end];
    }

    /**
     * Current open period: the latest period that has not been closed yet.
     * Returns ['year' => int, 'month' => int, 'is_closed' => bool, 'closed_at' => ...]
     */
    public static function current(): array
    {
        $today = now();
        $p = self::periodFor($today);

        $record = self::where('year', $p['year'])->where('month', $p['month'])->first();

        return [
            'year'      => $p['year'],
            'month'     => $p['month'],
            'is_closed' => $record && $record->closed_at !== null,
            'closed_at' => $record?->closed_at?->toIso8601String(),
        ];
    }

    /**
     * Next period from a given period.
     */
    public static function nextPeriod(int $year, int $month): array
    {
        if ($month === 12) {
            return ['year' => $year + 1, 'month' => 1];
        }
        return ['year' => $year, 'month' => $month + 1];
    }

    /**
     * Check if a specific period is closed.
     */
    public static function isClosed(int $year, int $month): bool
    {
        $record = self::where('year', $year)->where('month', $month)->first();
        return $record && $record->closed_at !== null;
    }
}
