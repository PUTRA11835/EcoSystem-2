<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PeriodLateException extends Model
{
    protected $fillable = [
        'period_id',
        'employee_id',
        'domain',
        'granted_by',
        'granted_at',
        'expires_at',
        'notes',
    ];

    protected $casts = [
        'granted_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    // ── Relationships ─────────────────────────────────────────────────────────

    public function period()
    {
        return $this->belongsTo(ReportingPeriod::class, 'period_id');
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id', 'employee_id');
    }

    public function grantedBy()
    {
        return $this->belongsTo(Employee::class, 'granted_by', 'employee_id');
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /** Is this exception still active (not expired)? */
    public function isActive(): bool
    {
        return is_null($this->expires_at) || $this->expires_at->isFuture();
    }
}
