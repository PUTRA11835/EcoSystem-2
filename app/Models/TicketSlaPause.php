<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TicketSlaPause extends Model
{
    protected $table = 'ticket_sla_pauses';

    public $timestamps = false;

    protected $fillable = [
        'ticket_id',
        'pause_reason',
        'triggered_by_status',
        'started_at',
        'scheduled_end_at',
        'ended_at',
        'duration_hours',
        'started_by_message_id',
        'ended_by_message_id',
        'resumed_by',
    ];

    protected $casts = [
        'started_at'              => 'datetime',
        'scheduled_end_at'        => 'datetime',
        'ended_at'                => 'datetime',
        'duration_hours'          => 'decimal:2',
        'started_by_message_id'   => 'integer',
        'ended_by_message_id'     => 'integer',
    ];

    public function ticket()
    {
        return $this->belongsTo(Ticket::class, 'ticket_id', 'ticket_id');
    }

    public function startedByMessage()
    {
        return $this->belongsTo(TicketMessage::class, 'started_by_message_id');
    }

    public function endedByMessage()
    {
        return $this->belongsTo(TicketMessage::class, 'ended_by_message_id');
    }

    public function resumedBy()
    {
        return $this->belongsTo(Employee::class, 'resumed_by', 'employee_id');
    }

    public function isActive(): bool
    {
        return $this->ended_at === null;
    }
}
