<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LeavePermitQuota extends Model
{
    protected $table = 'leave_permit_quotas';

    protected $fillable = [
        'year',
        'leave_permit_type_id',
        'employee_id',
        'quota_amount',
        'description',
    ];

    protected $casts = [
        'year' => 'integer',
        'quota_amount' => 'float',
    ];

    public function leavePermitType()
    {
        return $this->belongsTo(LeavePermitType::class, 'leave_permit_type_id');
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id', 'employee_id');
    }

    /**
     * Get effective quota amount for a specific employee and type in a given year.
     * Checks individual quota first, then falls back to global quota (employee_id is null).
     */
    public static function getEffectiveQuota(int $employeeId, int $typeId, int $year): ?float
    {
        // 1. Check individual override
        $individual = static::where('year', $year)
            ->where('leave_permit_type_id', $typeId)
            ->where('employee_id', $employeeId)
            ->first();

        if ($individual !== null) {
            return (float) $individual->quota_amount;
        }

        // 2. Fall back to global setting
        $global = static::where('year', $year)
            ->where('leave_permit_type_id', $typeId)
            ->whereNull('employee_id')
            ->first();

        if ($global !== null) {
            return (float) $global->quota_amount;
        }

        return null; // No quota set by HR
    }
}
