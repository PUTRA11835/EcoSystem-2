<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MeetingTemplate extends Model
{
    protected $fillable = [
        'ticket_id',
        'employee_id',
        'name',
        'meeting_link',
        'notes',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id', 'employee_id');
    }

    public function ticket()
    {
        return $this->belongsTo(Ticket::class, 'ticket_id', 'ticket_id');
    }
}
