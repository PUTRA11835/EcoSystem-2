<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;

/**
 * Pengaturan Payroll (satu baris, id = 1): saklar aktivasi + kebijakan perhitungan.
 * Auditable: setiap perubahan (termasuk menyalakan/mematikan modul) tercatat di audit log, modul "Payroll".
 */
class PayrollSetting extends Model
{
    use Auditable;

    protected static ?string $auditModule = 'Payroll';

    protected $table = 'payroll_settings';

    public $incrementing = false;

    protected $guarded = ['id'];

    protected $casts = ['module_enabled' => 'boolean', 'enabled_at' => 'datetime', 'attendance_enabled' => 'boolean', 'attendance_effective_from' => 'date',
        'cutoff_enabled' => 'boolean', 'timesheet_allowance_enabled' => 'boolean', 'tax_timesheet_allowance' => 'boolean', 'loan_deduction_enabled' => 'boolean',
        'block_on_pending_inputs' => 'boolean', 'calculate_in_background' => 'boolean'];

    /** Baris tunggal; dibuat bila belum ada (migrasi sudah membuatnya). */
    public static function current(): self
    {
        return static::query()->find(1) ?? static::query()->create(['id' => 1]);
    }

    public static function isEnabled(): bool
    {
        return (bool) static::query()->where('id', 1)->value('module_enabled');
    }

    public function auditRecordLabel(): string
    {
        return 'Payroll settings';
    }
}
