<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class ReportingPeriod extends Model
{
    protected $fillable = [
        'year', 'month', 'start_date', 'end_date',
        // Legacy
        'closed_at', 'closed_by',
        // Global
        'global_status', 'opened_at', 'opened_by',
        // Project domain
        'project_status', 'project_opened_at', 'project_opened_by',
        'project_closed_at', 'project_closed_by',
        // Support domain
        'support_status', 'support_opened_at', 'support_opened_by',
        'support_closed_at', 'support_closed_by',
    ];

    protected $casts = [
        'closed_at'          => 'datetime',
        'opened_at'          => 'datetime',
        'project_opened_at'  => 'datetime',
        'project_closed_at'  => 'datetime',
        'support_opened_at'  => 'datetime',
        'support_closed_at'  => 'datetime',
        'start_date'         => 'date',
        'end_date'           => 'date',
    ];

    // ── Relationships ─────────────────────────────────────────────────────────

    public function auditLogs()
    {
        return $this->hasMany(PeriodAuditLog::class, 'period_id');
    }

    public function lateExceptions()
    {
        return $this->hasMany(PeriodLateException::class, 'period_id');
    }

    public function lateExceptionRequests()
    {
        return $this->hasMany(\App\Models\PeriodLateExceptionRequest::class, 'period_id');
    }

    public function opener()
    {
        return $this->belongsTo(Employee::class, 'opened_by', 'employee_id');
    }

    // ── Computed helpers ──────────────────────────────────────────────────────

    /** Human-readable period label, e.g. "March 2026 (21 Feb 2026 – 20 Mar 2026)" */
    public function getLabel(): string
    {
        static $short = [
            1=>'Jan',2=>'Feb',3=>'Mar',4=>'Apr',
            5=>'May',6=>'Jun',7=>'Jul',8=>'Aug',
            9=>'Sep',10=>'Oct',11=>'Nov',12=>'Dec',
        ];

        $name = Carbon::create($this->year, $this->month, 1)->format('F Y');

        if ($this->start_date && $this->end_date) {
            $s = Carbon::parse($this->start_date);
            $e = Carbon::parse($this->end_date);
            $range = "{$s->day} {$short[$s->month]} {$s->year} – {$e->day} {$short[$e->month]} {$e->year}";
            return "{$name} ({$range})";
        }

        // Fallback: compute from convention (21st prev month → 20th current month)
        $sm = $this->month === 1 ? 12 : $this->month - 1;
        $sy = $this->month === 1 ? $this->year - 1 : $this->year;
        return "{$name} (21 {$short[$sm]} {$sy} – 20 {$short[$this->month]} {$this->year})";
    }

    /** Is the period globally active (open)? */
    public function isGloballyOpen(): bool
    {
        return $this->global_status === 'open';
    }

    /** Is the project domain open? */
    public function isProjectOpen(): bool
    {
        return $this->project_status === 'open';
    }

    /** Is the support domain open? */
    public function isSupportOpen(): bool
    {
        return $this->support_status === 'open';
    }

    /** Can global be closed? (both domains must be closed first) */
    public function canCloseGlobal(): bool
    {
        return $this->global_status === 'open'
            && $this->project_status !== 'open'
            && $this->support_status !== 'open';
    }

    /** Get domain status: 'not_open' | 'open' | 'closed' */
    public function domainStatus(string $domain): string
    {
        return match($domain) {
            'project' => $this->project_status,
            'support' => $this->support_status,
            default   => 'not_open',
        };
    }

    // ── Static period helpers (preserved from original) ───────────────────────

    /**
     * Compute the period (year, month) for a given date.
     *
     * Convention (revised):
     *   - Day 1–20  of month M  → Period M   (e.g. Jan 15  → Period January)
     *   - Day 21–31 of month M  → Period M+1 (e.g. Dec 25  → Period January of next year)
     *
     * Examples:
     *   Dec 21 – Jan 20 → Period January
     *   Jan 21 – Feb 20 → Period February
     */
    public static function periodFor(Carbon $date): array
    {
        if ($date->day >= 21) {
            // Dates on or after the 21st belong to next month's period
            return $date->month === 12
                ? ['year' => $date->year + 1, 'month' => 1]
                : ['year' => $date->year,     'month' => $date->month + 1];
        }
        // Dates before the 21st stay in the current month's period
        return ['year' => $date->year, 'month' => $date->month];
    }

    /**
     * Default date range for a given period year/month.
     * Period M of year Y covers: 21st of (M-1) → 20th of M.
     */
    public static function dateRange(int $year, int $month): array
    {
        $startYear  = $month === 1 ? $year - 1 : $year;
        $startMonth = $month === 1 ? 12        : $month - 1;
        $start = Carbon::create($startYear, $startMonth, 21)->startOfDay();
        $end   = Carbon::create($year, $month, 20)->endOfDay();
        return ['start' => $start, 'end' => $end];
    }

    /**
     * Check whether a date range overlaps any existing period.
     * Pass $excludeId when updating an existing period so it is not compared against itself.
     *
     * Overlap exists when:
     *   - Another period's start_date or end_date falls inside [start, end], OR
     *   - Another period completely contains [start, end]
     */
    public static function hasOverlap(string $startDate, string $endDate, ?int $excludeId = null): bool
    {
        return self::when($excludeId, fn ($q) => $q->where('id', '!=', $excludeId))
            ->where(function ($q) use ($startDate, $endDate) {
                $q->whereBetween('start_date', [$startDate, $endDate])
                  ->orWhereBetween('end_date', [$startDate, $endDate])
                  ->orWhere(function ($q2) use ($startDate, $endDate) {
                      $q2->where('start_date', '<=', $startDate)
                         ->where('end_date', '>=', $endDate);
                  });
            })
            ->exists();
    }

    /**
     * Default start_date string for a given period.
     * Period M → starts on 21st of month (M-1).
     */
    public static function defaultStartDate(int $year, int $month): string
    {
        if ($month === 1) {
            return sprintf('%04d-12-21', $year - 1);
        }
        return sprintf('%04d-%02d-21', $year, $month - 1);
    }

    /**
     * Default end_date string for a given period.
     * Period M → ends on 20th of month M.
     */
    public static function defaultEndDate(int $year, int $month): string
    {
        return sprintf('%04d-%02d-20', $year, $month);
    }

    /**
     * Get the single globally-open period (null if none).
     */
    public static function getActive(): ?self
    {
        return self::where('global_status', 'open')->first();
    }

    /**
     * Get legacy "current" period info (backward compat for ReportingController).
     */
    public static function current(): array
    {
        $today = now();
        $p     = self::periodFor($today);
        $record = self::where('year', $p['year'])->where('month', $p['month'])->first();

        return [
            'year'           => $p['year'],
            'month'          => $p['month'],
            'is_closed'      => $record && $record->global_status === 'closed',
            'closed_at'      => $record?->closed_at?->toIso8601String(),
            'global_status'  => $record?->global_status ?? 'not_open',
            'project_status' => $record?->project_status ?? 'not_open',
            'support_status' => $record?->support_status ?? 'not_open',
        ];
    }

    /**
     * Next period from a given period.
     */
    public static function nextPeriod(int $year, int $month): array
    {
        return $month === 12
            ? ['year' => $year + 1, 'month' => 1]
            : ['year' => $year, 'month' => $month + 1];
    }

    /**
     * Check if a specific period is globally closed.
     */
    public static function isClosed(int $year, int $month): bool
    {
        $record = self::where('year', $year)->where('month', $month)->first();
        return $record && $record->global_status === 'closed';
    }
}
