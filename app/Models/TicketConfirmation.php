<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TicketConfirmation extends Model
{
    protected $table = 'ticket_confirmation';
    protected $primaryKey = 'confirmation_id';
    
    protected $fillable = [
        'ticket_id',
        'employee_id',
        'member_ids',
        'man_days',
        'status',
        'confirmed_by',
        'confirmed_at',
        'rejection_reason'
    ];

    protected $casts = [
        'member_ids' => 'array',
        'confirmed_at' => 'datetime',
        'man_days' => 'decimal:2'
    ];

    public function ticket()
    {
        return $this->belongsTo(Ticket::class, 'ticket_id', 'ticket_id');
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id', 'employee_id');
    }
}