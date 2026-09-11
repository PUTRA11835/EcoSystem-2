<?php

namespace App\Services;

use App\Models\Holiday;
use Carbon\Carbon;

class HolidayService
{
    /**
     * Per-request memoization keyed by "yearFrom-yearTo" — isNonWorkingDay() is called once
     * per calendar day inside SlaService's calcHours()/addWorkingHours() loops, which run
     * per ticket in list endpoints (e.g. TicketController::index() over 1000+ tickets). Without
     * this cache, a single request re-queries the `holidays` table for every day of every
     * ticket's lifetime — thousands of redundant queries that can exceed max_execution_time.
     * Static so it survives across the many short-lived SlaService/HolidayService instances
     * app(SlaService::class) creates per ticket; safe since it only lives for one PHP request.
     */
    private static array $holidayCache = [];

    /**
     * Get all Indonesian holidays (national + cuti bersama + custom) for a year range.
     * Sumber data: tabel `holidays` (dikelola admin via menu Manajemen → Hari Libur).
     * Returns array of ['date' => 'YYYY-MM-DD', 'name' => '...', 'type' => 'national|cuti_bersama|custom'].
     */
    public function getHolidays(int $yearFrom, int $yearTo): array
    {
        $cacheKey = "{$yearFrom}-{$yearTo}";
        if (isset(self::$holidayCache[$cacheKey])) {
            return self::$holidayCache[$cacheKey];
        }

        $from = sprintf('%04d-01-01', $yearFrom);
        $to   = sprintf('%04d-12-31', $yearTo);

        return self::$holidayCache[$cacheKey] = Holiday::query()
            ->where('is_active', true)
            ->whereBetween('date', [$from, $to])
            ->orderBy('date')
            ->get()
            ->map(fn($h) => [
                'date' => $h->date->format('Y-m-d'),
                'name' => $h->name,
                'type' => $h->type,
            ])
            ->all();
    }

    /**
     * Get plain list of holiday dates (YYYY-MM-DD) for a year range.
     */
    public function getHolidayDates(int $yearFrom, int $yearTo): array
    {
        $dates = array_map(fn($h) => $h['date'], $this->getHolidays($yearFrom, $yearTo));
        return array_values(array_unique($dates));
    }

    /**
     * Check if a given date is a holiday or weekend.
     */
    public function isNonWorkingDay($date): bool
    {
        $date = $date instanceof Carbon ? $date : Carbon::parse($date);

        if ($date->isWeekend()) {
            return true;
        }

        return in_array(
            $date->format('Y-m-d'),
            $this->getHolidayDates($date->year, $date->year),
            true
        );
    }

    /**
     * Add N working days to a given start date.
     * Inclusive convention: 1 working day = start == end.
     */
    public function addWorkingDays($start, int $workingDays): Carbon
    {
        $date = $start instanceof Carbon ? $start->copy() : Carbon::parse($start);

        if ($workingDays <= 0) {
            return $date;
        }

        $remaining = $workingDays - 1; // inclusive: start counts as day 1
        while ($remaining > 0) {
            $date->addDay();
            if (!$this->isNonWorkingDay($date)) {
                $remaining--;
            }
        }

        return $date;
    }

    /**
     * Count working days between two dates (inclusive on both ends).
     */
    public function countWorkingDays($start, $end): int
    {
        $start = $start instanceof Carbon ? $start->copy() : Carbon::parse($start);
        $end   = $end   instanceof Carbon ? $end->copy()   : Carbon::parse($end);

        if ($end->lt($start)) {
            return 0;
        }

        $count = 0;
        $cursor = $start->copy();
        while ($cursor->lte($end)) {
            if (!$this->isNonWorkingDay($cursor)) {
                $count++;
            }
            $cursor->addDay();
        }

        return $count;
    }
}
