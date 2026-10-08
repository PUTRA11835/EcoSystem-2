<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Komponen gaji karyawan (sumber kebenaran gaji pokok payroll). Aturan ada di
 * App\Support\Payroll\SalaryComponentRules. Nominal tidak masuk serialisasi JSON.
 */
class EmployeeSalaryComponent extends Model
{
    use Auditable;

    protected static ?string $auditModule = 'Employee';

    protected $table = 'employee_salary_components';

    protected $fillable = [
        'employee_id', 'name', 'category', 'amount',
        'effective_from', 'effective_to',
        'is_mandatory', 'is_active', 'taxable', 'bpjs_base',
    ];

    protected $casts = [
        'amount'         => 'decimal:2',
        'effective_from' => 'date',
        'effective_to'   => 'date',
        'is_mandatory'   => 'boolean',
        'is_active'      => 'boolean',
        'taxable'        => 'boolean',
        'bpjs_base'      => 'boolean',
    ];

    protected $hidden = ['amount'];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id', 'employee_id');
    }
}
