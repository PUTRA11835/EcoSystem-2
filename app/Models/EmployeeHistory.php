<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmployeeHistory extends Model
{
    use HasFactory;

    protected $table = 'employee_history';

    protected $primaryKey = 'history_id';

    protected $fillable = [
        'employee_id',
        'action',
        'description',
        'performed_by',
        'performed_at',
    ];

    protected $casts = [
        'performed_at' => 'datetime',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id', 'employee_id');
    }

    public function performer()
    {
        return $this->belongsTo(Employee::class, 'performed_by', 'employee_id');
    }
}
