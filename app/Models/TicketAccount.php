<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TicketAccount extends Model
{
    use HasFactory;

    protected $table = 'ticket_account';

    protected $fillable = [
        'ticket_id',
        'account_type',
        'account_email',
        'can_view',
        'can_reply',
        'joined_at',
        'last_seen_at',
    ];

    protected $casts = [
        'can_view' => 'boolean',
        'can_reply' => 'boolean',
        'joined_at' => 'datetime',
        'last_seen_at' => 'datetime',
    ];

    public function ticket()
    {
        return $this->belongsTo(Ticket::class, 'ticket_id', 'ticket_id');
    }

    public function scopeCustomers($query)
    {
        return $query->where('account_type', 'customer');
    }

    public function scopeEmployees($query)
    {
        return $query->where('account_type', 'employee');
    }

    public function scopeCanView($query)
    {
        return $query->where('can_view', true);
    }

    public function scopeCanReply($query)
    {
        return $query->where('can_reply', true);
    }
}
