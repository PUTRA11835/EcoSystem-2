<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PeriodAuditLog extends Model
{
    public $timestamps = false; // only created_at (set via useCurrent)

    protected $fillable = [
        'period_id',
        'action',
        'actor_id',
        'actor_role_id',
        'is_force',
        'metadata',
    ];

    protected $casts = [
        'is_force'   => 'boolean',
        'metadata'   => 'array',
        'created_at' => 'datetime',
    ];

    // ── Relationships ─────────────────────────────────────────────────────────

    public function period()
    {
        return $this->belongsTo(ReportingPeriod::class, 'period_id');
    }

    public function actor()
    {
        return $this->belongsTo(Employee::class, 'actor_id', 'employee_id');
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /** Human-readable action label */
    public function getActionLabel(): string
    {
        return match($this->action) {
            'period_created'                    => 'Period Created',
            'global_open'                       => 'Global Period Opened',
            'global_reopen'                     => 'Global Period Re-Opened by RPMO',
            'global_close'                      => 'Global Period Closed',
            'project_open'                      => 'Project Domain Opened',
            'project_close'                     => 'Project Domain Closed',
            'support_open'                      => 'Support Domain Opened',
            'support_close'                     => 'Support Domain Closed',
            'force_close_project'               => 'Force Closed — Project Domain',
            'force_close_support'               => 'Force Closed — Support Domain',
            'late_exception_granted_project'    => 'Late Access Granted (Project)',
            'late_exception_granted_support'    => 'Late Access Granted (Support)',
            'late_exception_revoked_project'    => 'Late Access Revoked (Project)',
            'late_exception_revoked_support'    => 'Late Access Revoked (Support)',
            'date_updated'                      => 'Period Dates Updated',
            default                             => ucwords(str_replace('_', ' ', $this->action)),
        };
    }

    /** Badge color class for action type */
    public function getActionColor(): string
    {
        if ($this->is_force) return 'bg-red-100 text-red-700';

        return match(true) {
            str_contains($this->action, 'created')  => 'bg-blue-100 text-blue-700',
            str_contains($this->action, 'open')      => 'bg-green-100 text-green-700',
            str_contains($this->action, 'close')     => 'bg-gray-100 text-gray-700',
            str_contains($this->action, 'granted')   => 'bg-yellow-100 text-yellow-700',
            str_contains($this->action, 'revoked')   => 'bg-orange-100 text-orange-700',
            default                                  => 'bg-gray-100 text-gray-600',
        };
    }
}
