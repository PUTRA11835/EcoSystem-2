<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Engagement konsultan External (1:1). Lihat migrasi 2026_10_02_000002 untuk alasan dan aturan data.
 *
 * `$fillable` hanya kolom NON-sensitif. Tarif (`rate`, `currency`) dan `updated_by` diisi lewat
 * forceFill di service SETELAH pemeriksaan izin tarif — tidak dapat ditimpa oleh mass-assignment.
 */
class EmployeeEngagement extends Model
{
    use Auditable;

    protected static ?string $auditModule = 'Employee';

    protected $table = 'employee_engagement';

    protected $fillable = [
        'employee_id', 'engagement_scheme', 'vendor_partner', 'client_company',
        'assignment_role', 'managed_by', 'start_date', 'end_date',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date'   => 'date',
        'rate'       => 'decimal:2',
    ];

    /** Tarif tidak ikut ter-serialisasi ke JSON dan disamarkan di audit (`auditExcludedAttributes` memakai $hidden). */
    protected $hidden = ['rate', 'currency'];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id', 'employee_id');
    }
}
