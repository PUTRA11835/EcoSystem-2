<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketSlaEvent extends Model
{
    protected $table      = 'ticket_sla_events';
    public    $timestamps = false;

    protected $fillable = [
        'ticket_id',
        'staging_ticket_id',
        'message_id',
        'event_type',
        'event_at',
        'waiting_hours',
        'response_hours',
        'resolution_hours',
        'notes',
        'triggered_by',
        'triggered_by_type',
        'created_at',
    ];

    protected $casts = [
        'event_at'         => 'datetime',
        'created_at'       => 'datetime',
        'waiting_hours'    => 'decimal:2',
        'response_hours'   => 'decimal:2',
        'resolution_hours' => 'decimal:2',
    ];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class, 'ticket_id', 'ticket_id');
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(TicketMessage::class, 'message_id', 'id');
    }

    // Label teks untuk setiap event type
    public function getEventLabelAttribute(): string
    {
        return match ($this->event_type) {
            'email_received'        => 'Email / Request Masuk',
            'ticket_validated'      => 'Tiket Dibuat',
            'resolution_sent'       => 'Resolusi Dikirim',
            'customer_replied'      => 'Customer Membalas',
            'escalated_to_sap'      => 'Diteruskan ke SAP',
            'escalated_to_support'  => 'Diteruskan ke Support',
            'sla_warning'           => 'Peringatan SLA',
            'sla_breached'          => 'SLA Breach',
            'ticket_closed'         => 'Tiket Ditutup',
            default                 => $this->event_type,
        };
    }
}
