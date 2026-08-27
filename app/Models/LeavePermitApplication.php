<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LeavePermitApplication extends Model
{
    protected $table = 'leave_permit_applications';

    protected $fillable = [
        'application_no',
        'employee_id',
        'leave_permit_type_id',
        'start_date',
        'end_date',
        'total_days',
        'reason',
        'attachment_path',
        'status',
        'revision_notes',
        'rejection_reason',
        'reviewed_by',
        'reviewed_at',
    ];

    protected $casts = [
        'start_date' => 'date:Y-m-d',
        'end_date' => 'date:Y-m-d',
        'total_days' => 'float',
        'reviewed_at' => 'datetime',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id', 'employee_id');
    }

    public function reviewer()
    {
        return $this->belongsTo(Employee::class, 'reviewed_by', 'employee_id');
    }

    public function leavePermitType()
    {
        return $this->belongsTo(LeavePermitType::class, 'leave_permit_type_id');
    }

    public function logs()
    {
        return $this->hasMany(LeavePermitLog::class, 'application_id')->orderBy('created_at', 'desc');
    }
}
