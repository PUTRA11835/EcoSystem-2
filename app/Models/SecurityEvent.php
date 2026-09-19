<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class SecurityEvent extends Model
{
    public $timestamps = false; // only created_at (set via useCurrent)

    protected $fillable = [
        'event_type',
        'severity',
        'module',
        'status',
        'title',
        'description',
        'target_employee_id',
        'target_identifier',
        'target_ip',
        'actor_employee_id',
        'actor_name',
        'ip_address',
        'user_agent',
        'request_method',
        'request_path',
        'payload',
        'related_audit_log_id',
        'resolved_by_id',
        'resolved_by_name',
        'resolved_at',
        'resolution_note',
    ];

    protected $casts = [
        'payload'     => 'array',
        'resolved_at' => 'datetime',
        'created_at'  => 'datetime',
    ];

    // ── Relationships ─────────────────────────────────────────────────────────

    public function targetEmployee()
    {
        return $this->belongsTo(Employee::class, 'target_employee_id', 'employee_id');
    }

    public function actorEmployee()
    {
        return $this->belongsTo(Employee::class, 'actor_employee_id', 'employee_id');
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /** Badge color class for severity, mirrors AuditLog::getEventColor() styling. */
    public function getSeverityColor(): string
    {
        return match ($this->severity) {
            'critical' => 'bg-red-100 text-red-700',
            'high'     => 'bg-orange-100 text-orange-700',
            'medium'   => 'bg-amber-100 text-amber-700',
            'low'      => 'bg-gray-100 text-gray-600',
            default    => 'bg-gray-100 text-gray-600',
        };
    }

    /**
     * Fire-and-forget write - same contract as AuditLog::record(): a logging
     * failure must never break the caller (login flow, request middleware,
     * scheduled job). Returns the created row so callers can reference its id
     * (e.g. for notification linking), or null if the write failed.
     */
    public static function record(array $attributes): ?self
    {
        try {
            return static::create($attributes);
        } catch (\Throwable $e) {
            Log::warning('SecurityEvent::record failed', [
                'error'      => $e->getMessage(),
                'event_type' => $attributes['event_type'] ?? null,
            ]);

            return null;
        }
    }
}
