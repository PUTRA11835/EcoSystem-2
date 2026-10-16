<?php

namespace App\Models\Reimbursement;

use App\Models\EmployeeRole;
use Illuminate\Database\Eloquent\Model;

/**
 * Cetakan satu langkah persetujuan reimbursement — KONFIGURASI, bukan riwayat.
 *
 * Riwayat tiap dokumen ada di ReimbursementRequestApproval, disalin dari sini
 * saat dokumen dibuat. Lihat docblock migrasinya untuk alasan pemisahan itu, dan
 * mengapa tabelnya dibuat sendiri alih-alih menumpang overtime_approval_steps
 * (Keputusan D102).
 */
class ReimbursementApprovalStep extends Model
{
    protected $table = 'reimbursement_approval_steps';

    protected $fillable = [
        'module', 'order_seq', 'name',
        'approver_type', 'approver_role_id', 'approver_employee_ids',
        'signs_as', 'requester_selectable',
        'is_active',
    ];

    protected $casts = [
        'order_seq'             => 'integer',
        'approver_role_id'      => 'integer',
        'approver_employee_ids' => 'array',
        'is_active'             => 'boolean',
        'requester_selectable'  => 'boolean',
    ];

    // ── Konstanta ───────────────────────────────────────────────────────────

    public const MODULE_REIMBURSEMENT = 'reimbursement';

    /** Seluruh pemegang role boleh bertindak; cukup satu di antaranya. */
    public const TYPE_ROLE = 'role';

    /** Orang-orang tertentu yang disebut namanya. */
    public const TYPE_EMPLOYEE = 'employee';

    /**
     * Atasan langsung pemohon.
     *
     * 🔴 BELUM DAPAT DIJALANKAN. Tabel `employee` tidak punya `reports_to_id`,
     * dan `employee_basic_data.direct_supervision` / `.manager` 100% NULL untuk
     * seluruh karyawan. Nilainya didaftarkan sejak awal supaya pengaktifannya
     * nanti tidak memerlukan migrasi — pilihannya dinonaktifkan di UI.
     */
    public const TYPE_DIRECT_MANAGER = 'direct_manager';

    public const TYPES = [
        self::TYPE_ROLE,
        self::TYPE_EMPLOYEE,
        self::TYPE_DIRECT_MANAGER,
    ];

    /** Tipe yang benar-benar dapat dipilih hari ini. */
    public const SELECTABLE_TYPES = [
        self::TYPE_ROLE,
        self::TYPE_EMPLOYEE,
    ];

    public const TYPE_LABELS = [
        self::TYPE_ROLE           => 'By Role',
        self::TYPE_EMPLOYEE       => 'Specific Employees',
        self::TYPE_DIRECT_MANAGER => 'Direct Manager',
    ];

    /**
     * Kolom tanda tangan pada dokumen yang diisi langkah ini. Nama yang
     * tercetak adalah orang yang bertindak pada langkahnya.
     */
    public const SIGNS_ACCOUNTING = 'accounting';
    public const SIGNS_CASHIER    = 'cashier';
    public const SIGNS_APPROVER   = 'approver';

    public const SIGNS_AS_LABELS = [
        self::SIGNS_ACCOUNTING => 'Accounting',
        self::SIGNS_CASHIER    => 'Cashier',
        self::SIGNS_APPROVER   => 'Approver',
    ];

    // ── Relationships ───────────────────────────────────────────────────────

    public function role()
    {
        return $this->belongsTo(EmployeeRole::class, 'approver_role_id', 'id');
    }

    // ── Scopes ──────────────────────────────────────────────────────────────

    public function scopeForReimbursement($query)
    {
        return $query->where('module', self::MODULE_REIMBURSEMENT);
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

    /**
     * Karyawan yang dapat bertindak pada langkah ini — kandidat dropdown bila
     * langkahnya dipilih pemohon.
     *
     * @return array<int>
     */
    public function candidateEmployeeIds(): array
    {
        if ($this->approver_type === self::TYPE_EMPLOYEE) {
            return array_values(array_unique(array_map('intval', $this->approver_employee_ids ?? [])));
        }

        if ($this->approver_type === self::TYPE_ROLE && $this->approver_role_id) {
            return \Illuminate\Support\Facades\DB::table('employee_role_assignment')
                ->where('role_id', $this->approver_role_id)
                ->pluck('employee_id')
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values()
                ->all();
        }

        return [];
    }

    public function typeLabel(): string
    {
        return self::TYPE_LABELS[$this->approver_type] ?? $this->approver_type;
    }
}
