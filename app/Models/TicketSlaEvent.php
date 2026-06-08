<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TicketSlaEvent extends Model
{
    protected $table = 'ticket_sla_events';

    public $timestamps = false;

    protected $fillable = [
        'ticket_id',
        'staging_ticket_id',
        'message_id',
        'event_type',
        'jarvis_status',
        'event_at',
        'waiting_hours',
        'response_hours',
        'resolution_hours',
        'notes',
        'triggered_by',
        'triggered_by_type',
    ];

    protected $casts = [
        'event_at'         => 'datetime',
        'message_id'       => 'integer',
        'waiting_hours'    => 'decimal:2',
        'response_hours'   => 'decimal:2',
        'resolution_hours' => 'decimal:2',
    ];

    public function ticket()
    {
        return $this->belongsTo(Ticket::class, 'ticket_id', 'ticket_id');
    }

    public function message()
    {
        return $this->belongsTo(TicketMessage::class, 'message_id');
    }

    public function getEventLabelAttribute(): string
    {
        return match ($this->event_type) {
            'email_received'        => 'Email received',
            'ticket_validated'      => 'Ticket validated',
            'agent_replied'         => 'Agent replied',
            'customer_replied'      => 'Customer replied',
            'resolution_sent'       => 'Resolution sent',
            'escalated_to_sap'      => 'Escalated to SAP',
            'escalated_to_support'  => 'Returned to helpdesk',
            'sla_warning'           => 'SLA warning',
            'sla_breached'          => 'SLA breached',
            'ticket_closed'         => 'Ticket closed',
            default                 => $this->event_type,
        };
    }
}
