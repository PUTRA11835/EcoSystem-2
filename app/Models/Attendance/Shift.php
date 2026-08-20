<?php

namespace App\Models\Attendance;

use Illuminate\Database\Eloquent\Model;

/**
 * Pola jam kerja beserta toleransi keterlambatannya.
 *
 * Karyawan tanpa penugasan shift memakai baris yang bertanda `is_default`.
 */
class Shift extends Model
{
    protected $table = 'shifts';

    protected $fillable = [
        'name', 'check_in_time', 'check_out_time',
        'late_tolerance_minutes', 'break_minutes', 'work_days',
        'crosses_midnight', 'is_default', 'is_active', 'notes',
    ];

    protected $casts = [
        'late_tolerance_minutes' => 'integer',
        'break_minutes'          => 'integer',
        'crosses_midnight'       => 'boolean',
        'is_default'             => 'boolean',
        'is_active'              => 'boolean',
    ];

    // ── Relationships ───────────────────────────────────────────────────────

    public function assignments()
    {
        return $this->hasMany(EmployeeShift::class, 'shift_id', 'id');
    }

    // ── Scopes ──────────────────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    // ── Helpers ─────────────────────────────────────────────────────────────

    /**
     * Hari kerja sebagai array integer ISO-8601 (1 = Senin ... 7 = Minggu).
     */
    public function workDayNumbers(): array
    {
        return collect(explode(',', (string) $this->work_days))
            ->map(fn ($d) => (int) trim($d))
            ->filter(fn ($d) => $d >= 1 && $d <= 7)
            ->values()
            ->all();
    }

    /** "08:00 - 17:00" untuk ditampilkan di tabel. */
    public function getTimeRangeAttribute(): string
    {
        return substr((string) $this->check_in_time, 0, 5)
            . ' - '
            . substr((string) $this->check_out_time, 0, 5);
    }
}
