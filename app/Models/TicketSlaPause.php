<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketSlaPause extends Model
{
    protected $table    = 'ticket_sla_pauses';
    public    $timestamps = false;

    protected $fillable = [
        'ticket_id',
        'pause_reason',
        'triggered_by_status',
        'started_at',
        'ended_at',
        'duration_hours',
        'started_by_message_id',
        'ended_by_message_id',
        'resumed_by',
        'created_at',
    ];

    protected $casts = [
        'started_at'     => 'datetime',
        'ended_at'       => 'datetime',
        'created_at'     => 'datetime',
        'duration_hours' => 'decimal:2',
    ];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class, 'ticket_id', 'ticket_id');
    }

    public function startedByMessage(): BelongsTo
    {
        return $this->belongsTo(TicketMessage::class, 'started_by_message_id', 'id');
    }

    public function endedByMessage(): BelongsTo
    {
        return $this->belongsTo(TicketMessage::class, 'ended_by_message_id', 'id');
    }

    public function resumedBy(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'resumed_by', 'employee_id');
    }

    public function isActive(): bool
    {
        return is_null($this->ended_at);
    }
}
