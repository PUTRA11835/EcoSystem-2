<?php

namespace App\Models\Overtime;

use Illuminate\Database\Eloquent\Model;

/**
 * Cetakan satu langkah persetujuan — KONFIGURASI, bukan riwayat.
 *
 * Riwayat tiap pengajuan ada di OvertimeRequestApproval, disalin dari sini saat
 * pengajuan dibuat. Lihat docblock migrasinya untuk alasan pemisahan itu.
 */
class OvertimeApprovalStep extends Model
{
    protected $table = 'overtime_approval_steps';

    protected $fillable = [
        'module', 'order_seq', 'name',
        'approver_type', 'approver_role_id', 'approver_employee_ids',
        'is_active',
    ];

    protected $casts = [
        'order_seq'             => 'integer',
        'approver_role_id'      => 'integer',
        'approver_employee_ids' => 'array',
        'is_active'             => 'boolean',
    ];

    // ── Konstanta ───────────────────────────────────────────────────────────

    public const MODULE_OVERTIME = 'overtime';

    /** Seluruh pemegang role boleh bertindak; cukup satu di antaranya. */
    public const TYPE_ROLE = 'role';

    /** Orang-orang tertentu yang disebut namanya. */
    public const TYPE_EMPLOYEE = 'employee';

    /**
     * Atasan langsung PEMOHON (Master Employee › Basic Data › Direct Supervision). Tidak menyebut nama tetap:
     * saat pengajuan dibuat, orangnya dicari dari data pemohon dan disalin ke langkah pengajuan itu sebagai
     * penyetuju bernama. `approver_role_id` di sini = role CADANGAN bila pemohon tak punya atasan.
     */
    public const TYPE_DIRECT_MANAGER = 'direct_manager';

    /**
     * Pemimpin proyek pemohon: Lead / Project Manager / Co Project Manager dari proyek delivery yang diikuti pemohon
     * pada tanggal lembur. Pemohon yang tak ada di proyek mana pun memakai role cadangan (`approver_role_id`).
     */
    public const TYPE_PROJECT_LEAD = 'project_lead';

    public const TYPES = [
        self::TYPE_ROLE,
        self::TYPE_EMPLOYEE,
        self::TYPE_DIRECT_MANAGER,
        self::TYPE_PROJECT_LEAD,
    ];

    /** Tipe yang dapat dipilih. */
    public const SELECTABLE_TYPES = self::TYPES;

    /** Tipe yang orangnya ditentukan per pemohon, bukan per langkah. */
    public const DYNAMIC_TYPES = [self::TYPE_DIRECT_MANAGER, self::TYPE_PROJECT_LEAD];

    public const TYPE_LABELS = [
        self::TYPE_ROLE           => 'By Role',
        self::TYPE_EMPLOYEE       => 'Specific Employees',
        self::TYPE_DIRECT_MANAGER => "Employee's Supervisor",
        self::TYPE_PROJECT_LEAD   => 'Project Leader',
    ];

    // ── Relationships ───────────────────────────────────────────────────────

    public function role()
    {
        return $this->belongsTo(\App\Models\EmployeeRole::class, 'approver_role_id', 'id');
    }

    // ── Scopes ──────────────────────────────────────────────────────────────

    public function scopeForOvertime($query)
    {
        return $query->where('module', self::MODULE_OVERTIME);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    // ── Helpers ─────────────────────────────────────────────────────────────

    public function isSelectable(): bool
    {
        return in_array($this->approver_type, self::SELECTABLE_TYPES, true);
    }

    public function typeLabel(): string
    {
        return self::TYPE_LABELS[$this->approver_type] ?? $this->approver_type;
    }
}
