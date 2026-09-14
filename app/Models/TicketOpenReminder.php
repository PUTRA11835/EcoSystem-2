<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Satu baris per tiket: kapan reminder "masih Open" terakhir dikirim ke Teams
 * dan sudah berapa kali. Lihat migration create_ticket_open_reminders_table
 * untuk alasan tabelnya dipisah dari `ticket`.
 */
class TicketOpenReminder extends Model
{
    protected $table = 'ticket_open_reminders';

    protected $fillable = [
        'ticket_id',
        'sent_count',
        'first_sent_at',
        'last_sent_at',
    ];

    protected $casts = [
        'sent_count'    => 'integer',
        'first_sent_at' => 'datetime',
        'last_sent_at'  => 'datetime',
    ];

    public function ticket()
    {
        return $this->belongsTo(Ticket::class, 'ticket_id', 'ticket_id');
    }
}
