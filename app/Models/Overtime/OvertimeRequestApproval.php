<?php

namespace App\Models\Overtime;

use App\Models\Employee;
use App\Models\EmployeeRole;
use Illuminate\Database\Eloquent\Model;

/**
 * Satu langkah persetujuan pada SATU pengajuan.
 *
 * Barisnya disalin dari OvertimeApprovalStep saat pengajuan dibuat, sehingga
 * mengubah konfigurasi tidak pernah mengubah pengajuan yang sedang berjalan.
 */
class OvertimeRequestApproval extends Model
{
    protected $table = 'overtime_request_approvals';

    protected $fillable = [
        'overtime_request_id', 'order_seq',
        'step_name', 'approver_type', 'approver_role_id', 'approver_employee_ids',
        'status', 'acted_by', 'acted_at', 'notes',
    ];

    protected $casts = [
        'order_seq'             => 'integer',
        'approver_role_id'      => 'integer',
        'approver_employee_ids' => 'array',
        'acted_at'              => 'datetime',
    ];

    // ── Konstanta ───────────────────────────────────────────────────────────

    public const STATUS_WAITING  = 'waiting';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    /**
     * Langkah yang tidak sempat dijalani karena pengajuannya sudah selesai —
     * ditolak di langkah sebelumnya, atau dibatalkan pemohon.
     */
    public const STATUS_SKIPPED = 'skipped';

    public const STATUSES = [
        self::STATUS_WAITING,
        self::STATUS_APPROVED,
        self::STATUS_REJECTED,
        self::STATUS_SKIPPED,
    ];

    // ── Relationships ───────────────────────────────────────────────────────

    public function request()
    {
        return $this->belongsTo(OvertimeRequest::class, 'overtime_request_id', 'id');
    }

    public function actor()
    {
        return $this->belongsTo(Employee::class, 'acted_by', 'employee_id');
    }

    public function role()
    {
        return $this->belongsTo(EmployeeRole::class, 'approver_role_id', 'id');
    }

    // ── Helpers ─────────────────────────────────────────────────────────────

    public function isWaiting(): bool
    {
        return $this->status === self::STATUS_WAITING;
    }

    /**
     * Apakah karyawan ini termasuk penyetuju yang berhak pada langkah ini?
     *
     * Hanya memeriksa DEFINISI langkah. Pemeriksaan lain — apakah langkah ini
     * yang sedang menunggu, dan apakah pemohon boleh menyetujui dirinya sendiri
     * — dikerjakan OvertimeService, karena keduanya menyangkut keadaan
     * pengajuan, bukan definisi langkahnya.
     *
     * @param  array<int>  $roleIds  Role yang dipegang karyawan tersebut.
     */
    public function allows(int $employeeId, array $roleIds): bool
    {
        return match ($this->approver_type) {
            OvertimeApprovalStep::TYPE_ROLE
                => $this->approver_role_id !== null
                   && in_array((int) $this->approver_role_id, $roleIds, true),

            OvertimeApprovalStep::TYPE_EMPLOYEE
                => in_array($employeeId, array_map('intval', $this->approver_employee_ids ?? []), true),

            // Belum dapat dijalankan: hierarki atasan belum ada di basis data.
            // Dikembalikan false secara eksplisit, bukan dibiarkan jatuh ke
            // default, supaya jelas ini keadaan yang disengaja.
            OvertimeApprovalStep::TYPE_DIRECT_MANAGER => false,

            default => false,
        };
    }

    /** Ringkasan penyetuju untuk ditampilkan di layar. */
    public function approverLabel(): string
    {
        return match ($this->approver_type) {
            OvertimeApprovalStep::TYPE_ROLE
                => $this->role?->name ?? 'Role #' . $this->approver_role_id,

            OvertimeApprovalStep::TYPE_EMPLOYEE
                => count($this->approver_employee_ids ?? []) . ' selected employee(s)',

            OvertimeApprovalStep::TYPE_DIRECT_MANAGER => 'Direct manager (not available yet)',

            default => $this->approver_type,
        };
    }
}
