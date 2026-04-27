<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * 2-level late exception approval flow (request-based only).
 *
 * Flow: employee creates request → Head approves (L1) → RPMO approves (L2, must set expires_at) → access granted.
 * Either level can reject, ending the flow.
 * Access is determined purely by this record (status=approved, expires_at > now()).
 * No separate PeriodLateException record is created.
 */
class PeriodLateExceptionRequest extends Model
{
    protected $fillable = [
        'period_id', 'employee_id', 'domain', 'notes', 'status',
        'head_id', 'head_approved_at', 'head_notes',
        'rpmo_id', 'rpmo_approved_at', 'rpmo_notes',
        'rejected_by', 'rejected_at', 'rejection_notes',
        'expires_at',
    ];

    protected $casts = [
        'head_approved_at' => 'datetime',
        'rpmo_approved_at' => 'datetime',
        'rejected_at'      => 'datetime',
        'expires_at'       => 'datetime',
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

    public function head()
    {
        return $this->belongsTo(Employee::class, 'head_id', 'employee_id');
    }

    public function rpmo()
    {
        return $this->belongsTo(Employee::class, 'rpmo_id', 'employee_id');
    }

    public function rejectedBy()
    {
        return $this->belongsTo(Employee::class, 'rejected_by', 'employee_id');
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /** True if the request is approved AND the deadline has not passed. */
    public function isAccessActive(): bool
    {
        return $this->status === 'approved'
            && $this->expires_at !== null
            && $this->expires_at->isFuture();
    }

    /** True if approved but the deadline has already passed. */
    public function isExpired(): bool
    {
        return $this->status === 'approved'
            && $this->expires_at !== null
            && $this->expires_at->isPast();
    }

    public function isPending(): bool
    {
        return in_array($this->status, ['pending_head', 'pending_rpmo'], true);
    }

    public function getStatusLabel(): string
    {
        if ($this->isExpired()) return 'Expired';

        return match($this->status) {
            'pending_head' => 'Waiting for Head Approval',
            'pending_rpmo' => 'Waiting for RPMO Approval',
            'approved'     => 'Active',
            'rejected'     => 'Rejected',
            default        => ucwords(str_replace('_', ' ', $this->status)),
        };
    }

    public function getStatusColor(): string
    {
        if ($this->isExpired()) return 'bg-gray-100 text-gray-500';

        return match($this->status) {
            'pending_head' => 'bg-yellow-100 text-yellow-700',
            'pending_rpmo' => 'bg-blue-100 text-blue-700',
            'approved'     => 'bg-green-100 text-green-700',
            'rejected'     => 'bg-red-100 text-red-700',
            default        => 'bg-gray-100 text-gray-600',
        };
    }
}
