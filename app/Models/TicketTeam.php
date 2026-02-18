<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TicketTeam extends Model
{
    use HasFactory;

    protected $table = 'ticket_team';

    protected $fillable = [
        'ticket_id',
        'employee_id',
        'joined_at',
        'left_at',
    ];

    protected $casts = [
        'joined_at' => 'datetime',
        'left_at' => 'datetime',
    ];

    public function ticket()
    {
        return $this->belongsTo(Ticket::class, 'ticket_id', 'ticket_id');
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id', 'employee_id');
    }

    public function scopeActive($query)
    {
        return $query->whereNull('left_at');
    }

    public function scopeInactive($query)
    {
        return $query->whereNotNull('left_at');
    }
}
