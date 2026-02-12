<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ConsultantMandaysDetail extends Model
{
    use HasFactory;

    protected $table = 'consultant_mandays_details';

    protected $fillable = [
        'consultant_mandays_id',
        'employee_id',
        'module',
        'mandays',
        'notes',
    ];

    protected $casts = [
        'mandays' => 'decimal:2',
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
