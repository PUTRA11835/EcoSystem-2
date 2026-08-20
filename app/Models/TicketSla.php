<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\Auditable;

class TicketSla extends Model
{
    use Auditable;

    protected static ?string $auditModule = 'Ticket';

    protected $table = 'ticket_sla';

    protected $fillable = [
        'staging_ticket_id',
        'ticket_id',
        'sla_policy_id',
        'sla_mode',
        'sla_start_at',
        'response_due_at',
        'resolution_due_at',
        'first_responded_at',
        'validation_duration_hours',
        'response_status',
        'session_start_at',
        'solution_started_at',
        'resolved_at',
        'total_waiting_hours',
        'net_resolution_hours',
        'resolution_status',
        'ball_holder',
        'sla_paused_at',
    ];

    protected $casts = [
        'sla_start_at'               => 'datetime',
        'response_due_at'            => 'datetime',
        'resolution_due_at'          => 'datetime',
        'first_responded_at'         => 'datetime',
        'session_start_at'           => 'datetime',
        'solution_started_at'        => 'datetime',
        'resolved_at'                => 'datetime',
        'sla_paused_at'              => 'datetime',
        'validation_duration_hours'  => 'decimal:2',
        'total_waiting_hours'        => 'decimal:2',
        'net_resolution_hours'       => 'decimal:2',
    ];

    public function ticket()
    {
        return $this->belongsTo(Ticket::class, 'ticket_id', 'ticket_id');
    }

    public function stagingTicket()
    {
        return $this->belongsTo(StagingTicket::class, 'staging_ticket_id');
    }

    public function policy()
    {
        return $this->belongsTo(SlaPolicy::class, 'sla_policy_id');
    }

    public function pauses()
    {
        return $this->hasMany(TicketSlaPause::class, 'ticket_id', 'ticket_id');
    }

    public function events()
    {
        return $this->hasMany(TicketSlaEvent::class, 'ticket_id', 'ticket_id')
                    ->orderBy('event_at');
    }

    public function activePause(): ?TicketSlaPause
    {
        return $this->pauses()->whereNull('ended_at')->first();
    }

    public function isCurrentlyPaused(): bool
    {
        return $this->resolution_status === 'paused';
    }

    public function isClosed(): bool
    {
        return in_array($this->resolution_status, ['met', 'breached']);
    }
}
