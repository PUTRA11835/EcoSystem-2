<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Profil HR karyawan (1:1). Lihat migrasi 2026_09_30_000003 untuk alasan & aturan tiap kelompok kolom.
 *
 * `$fillable` SENGAJA hanya kolom yang boleh diisi lewat layanan. Kolom sistem (locked_*, updated_by,
 * path foto/tanda tangan) dan seluruh kolom payroll NONAKTIF diisi lewat metode/alur khusus supaya
 * tidak dapat ditimpa oleh mass-assignment dari request.
 */
class EmployeeHrProfile extends Model
{
    use Auditable;

    protected static ?string $auditModule = 'Employee';

    /**
     * Nilai yang TIDAK boleh masuk jejak audit (data kesehatan/pribadi pihak ketiga). Audit tetap
     * mencatat siapa, kapan, dan field mana yang berubah — tetapi bukan isinya. Kolom `$hidden`
     * (catatan HR, payroll nonaktif, path berkas) otomatis ikut disamarkan.
     */
    protected static array $auditExcept = [
        'blood_type', 'mother_maiden_name',
        'emergency_contact_name', 'emergency_contact_relation', 'emergency_contact_phone',
    ];

    protected $table = 'employee_hr_profile';

    protected $fillable = [
        'employee_id',
        'employment_status', 'grade_id', 'probation_end_date', 'hr_notes',
        'blood_type', 'mother_maiden_name',
        'emergency_contact_name', 'emergency_contact_relation', 'emergency_contact_phone',
    ];

    protected $casts = [
        'probation_end_date'     => 'date',
        'bpjs_health_active'     => 'boolean',
        'bpjs_employment_active' => 'boolean',
        'payroll_activated'      => 'boolean',
        'locked_at'              => 'datetime',
    ];

    /** Data pribadi/kesehatan/gaji: jangan ikut ter-serialisasi ke JSON secara tak sengaja. */
    protected $hidden = [
        'hr_notes', 'ptkp_code', 'dependents_count', 'bpjs_health_active', 'bpjs_employment_active',
        'bpjs_dependents_count', 'payroll_activated', 'photo_path', 'signature_path',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id', 'employee_id');
    }

    public function grade(): BelongsTo
    {
        return $this->belongsTo(Grade::class, 'grade_id');
    }

    public function isLocked(): bool
    {
        return $this->locked_at !== null;
    }
}
