<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ConsultantMandaysDetail extends Model
{
    use HasFactory;

    protected $table = 'consultant_mandays_detail';

    protected $fillable = [
        'consultant_mandays_id',
        'employee_id',
        'module',
        'mandays',
        'approved_mandays',
        'additional_mandays',
        'approved_additional',
        'notes',
        'progress_percentage',
        'progress_note',
        'progress_updated_at',
        'progress_updated_by',
    ];

    protected $casts = [
        'mandays'              => 'decimal:2',
        'approved_mandays'     => 'decimal:2',
        'additional_mandays'   => 'decimal:2',
        'approved_additional'  => 'decimal:2',
        'progress_percentage'  => 'decimal:2',
        'progress_updated_at'  => 'datetime',
    ];

    public function consultantMandays()
    {
        return $this->belongsTo(ConsultantMandays::class, 'consultant_mandays_id');
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id', 'employee_id');
    }

    public function scopeByModule($query, $module)
    {
        return $query->where('module', $module);
    }

    public function scopeByEmployee($query, $employeeId)
    {
        return $query->where('employee_id', $employeeId);
    }
}
