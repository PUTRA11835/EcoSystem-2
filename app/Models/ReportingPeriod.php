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

    public function opener()
    {
        return $this->belongsTo(Employee::class, 'opened_by', 'employee_id');
    }

    // ── Computed helpers ──────────────────────────────────────────────────────

    /** Human-readable period label, e.g. "March 2026" */
    public function getLabel(): string
    {
        return Carbon::create($this->year, $this->month, 1)->format('F Y');
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
     * Rule: day 21 of month M → day 20 of month M+1 = "Period M".
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
     * Default date range for a given period year/month (21-20 rule).
     */
    public static function dateRange(int $year, int $month): array
    {
        $start = Carbon::create($year, $month, 21)->startOfDay();
        $end   = $month === 12
            ? Carbon::create($year + 1, 1, 20)->endOfDay()
            : Carbon::create($year, $month + 1, 20)->endOfDay();
        return ['start' => $start, 'end' => $end];
    }

    /**
     * Default start_date string for a given period.
     */
    public static function defaultStartDate(int $year, int $month): string
    {
        return sprintf('%04d-%02d-21', $year, $month);
    }

    /**
     * Default end_date string for a given period.
     */
    public static function defaultEndDate(int $year, int $month): string
    {
        if ($month === 12) {
            return sprintf('%04d-01-20', $year + 1);
        }
        return sprintf('%04d-%02d-20', $year, $month + 1);
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
