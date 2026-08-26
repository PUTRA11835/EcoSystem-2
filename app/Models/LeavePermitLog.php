<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LeavePermitLog extends Model
{
    protected $table = 'leave_permit_logs';

    protected $fillable = [
        'application_id',
        'action',
        'performed_by',
        'notes',
    ];

    public function application()
    {
        return $this->belongsTo(LeavePermitApplication::class, 'application_id');
    }

    public function performer()
    {
        return $this->belongsTo(Employee::class, 'performed_by', 'employee_id');
    }
}
