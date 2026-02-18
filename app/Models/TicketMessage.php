<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TicketMessage extends Model
{
    use HasFactory;

    protected $table = 'ticket_message';

    protected $fillable = [
        'ticket_id',
        'sender_type',
        'sender_id',
        'sender_email',
        'sender_name',
        'message',
        'is_internal_note',
        'channel',
        'email_message_id',
        'email_in_reply_to',
        'is_read_by_customer',
        'is_read_by_agent',
        'read_at',
    ];

    protected $casts = [
        'is_internal_note' => 'boolean',
        'is_read_by_customer' => 'boolean',
        'is_read_by_agent' => 'boolean',
        'read_at' => 'datetime',
    ];

    public function ticket()
    {
        return $this->belongsTo(Ticket::class, 'ticket_id', 'ticket_id');
    }

    public function sender()
    {
        if ($this->sender_type === 'employee') {
            return $this->belongsTo(Employee::class, 'sender_id', 'employee_id');
        } elseif ($this->sender_type === 'customer') {
            return $this->belongsTo(Customer::class, 'sender_id', 'customer_id');
        }
        return null;
    }

    public function attachments()
    {
        return $this->hasMany(TicketAttachment::class, 'message_id');
    }

    public function scopeInternal($query)
    {
        return $query->where('is_internal_note', true);
    }

    public function scopePublic($query)
    {
        return $query->where('is_internal_note', false);
    }

    public function scopeByChannel($query, $channel)
    {
        return $query->where('channel', $channel);
    }

    public function scopeUnreadByCustomer($query)
    {
        return $query->where('is_read_by_customer', false);
    }

    public function scopeUnreadByAgent($query)
    {
        return $query->where('is_read_by_agent', false);
    }
}
