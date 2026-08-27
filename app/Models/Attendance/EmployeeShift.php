<?php

namespace App\Models\Attendance;

use App\Models\Employee;
use Illuminate\Database\Eloquent\Model;

/**
 * Penugasan shift ke karyawan.
 *
 * Konvensi periode yang berlaku di basis kode ini: `end_date IS NULL` berarti
 * penugasan masih aktif.
 */
class EmployeeShift extends Model
{
    protected $table = 'employee_shifts';

    protected $fillable = [
        'employee_id', 'shift_id', 'start_date', 'end_date', 'assigned_by',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date'   => 'date',
    ];

    // ── Relationships ───────────────────────────────────────────────────────

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id', 'employee_id');
    }

    public function shift()
    {
        return $this->belongsTo(Shift::class, 'shift_id', 'id');
    }

    // ── Scopes ──────────────────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->whereNull('end_date');
    }

    public function scopeForEmployee($query, int $employeeId)
    {
        return $query->where('employee_id', $employeeId);
    }
}
