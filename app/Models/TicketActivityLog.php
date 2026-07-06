<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TicketActivityLog extends Model
{
    protected $table = 'ticket_activity_logs';

    protected $fillable = [
        'ticket_id',
        'employee_id',
        'activity_date',
        'activity',
        'file_ref_url',
        'file_ref_path',
        'file_ref_name',
    ];

    protected $casts = [
        'activity_date' => 'date',
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
