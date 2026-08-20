<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    public $timestamps = false; // only created_at (set via useCurrent)

    protected $fillable = [
        'auditable_type',
        'auditable_id',
        'module',
        'event',
        'record_label',
        'description',
        'actor_id',
        'actor_role_id',
        'actor_name',
        'old_values',
        'new_values',
        'ip_address',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'created_at' => 'datetime',
    ];

    // ── Relationships ─────────────────────────────────────────────────────────

    public function actor()
    {
        return $this->belongsTo(Employee::class, 'actor_id', 'employee_id');
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /** Human-readable event label */
    public function getEventLabel(): string
    {
        return match ($this->event) {
            'created' => 'Created',
            'updated' => 'Updated',
            'deleted' => 'Deleted',
            default   => ucfirst($this->event),
        };
    }

    /** Badge color class for event type */
    public function getEventColor(): string
    {
        return match ($this->event) {
            'created' => 'bg-green-100 text-green-700',
            'updated' => 'bg-blue-100 text-blue-700',
            'deleted' => 'bg-red-100 text-red-700',
            default   => 'bg-gray-100 text-gray-600',
        };
    }
}
