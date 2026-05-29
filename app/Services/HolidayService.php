<?php

namespace App\Services;

use Carbon\Carbon;

class HolidayService
{
    /**
     * Get all Indonesian holidays (national + cuti bersama) for a year range.
     * Returns array of ['date' => 'YYYY-MM-DD', 'name' => '...', 'type' => 'national|cuti_bersama'].
     */
    public function getHolidays(int $yearFrom, int $yearTo): array
    {
        $holidays = [];

        for ($year = $yearFrom; $year <= $yearTo; $year++) {
            $list = config('indonesian-holidays.' . $year, []);
            foreach ($list as $h) {
                $holidays[] = $h;
            }
        }

        usort($holidays, fn($a, $b) => strcmp($a['date'], $b['date']));

        return $holidays;
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
