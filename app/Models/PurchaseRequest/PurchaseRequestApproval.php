<?php

namespace App\Models\PurchaseRequest;

use App\Models\Employee;
use App\Models\EmployeeRole;
use Illuminate\Database\Eloquent\Model;

/**
 * Satu langkah persetujuan pada SATU dokumen Purchase Request.
 *
 * Barisnya disalin dari PurchaseRequestApprovalStep saat dokumen dibuat,
 * sehingga mengubah konfigurasi tidak pernah mengubah dokumen yang sedang
 * berjalan.
 *
 * 🔴 Baris inilah yang juga menentukan KOLOM TANDA TANGAN pada cetakan
 * (Keputusan D129): `step_name` menjadi judul kolom, dan `acted_by` menjadi nama
 * yang tercetak. Karena barisnya salinan, cetak ulang dokumen lama setelah alur
 * diubah tetap menghasilkan kertas yang sama.
 */
class PurchaseRequestApproval extends Model
{
    protected $table = 'purchase_request_approvals';

    protected $fillable = [
        'purchase_request_id', 'order_seq',
        'step_name', 'approver_type', 'approver_role_id', 'approver_employee_ids',
        'chosen_by_requester',
        'status', 'acted_by', 'acted_at', 'notes',
    ];

    protected $casts = [
        'order_seq'             => 'integer',
        'approver_role_id'      => 'integer',
        'approver_employee_ids' => 'array',
        'chosen_by_requester'   => 'boolean',
        'acted_at'              => 'datetime',
    ];

    // ── Konstanta ───────────────────────────────────────────────────────────

    public const STATUS_WAITING  = 'waiting';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    /**
     * Langkah yang tidak sempat dijalani karena dokumennya sudah selesai —
     * ditolak di langkah sebelumnya, atau dibatalkan pemohonnya.
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
        return $this->belongsTo(PurchaseRequest::class, 'purchase_request_id', 'id');
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

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    /**
     * Apakah karyawan ini termasuk penyetuju yang berhak pada langkah ini?
     *
     * Hanya memeriksa DEFINISI langkah. Pemeriksaan lain — apakah langkah ini
     * yang sedang menunggu, dan apakah pemohon boleh menyetujui dirinya sendiri
     * — dikerjakan PurchaseRequestService, karena keduanya menyangkut keadaan
     * dokumen, bukan definisi langkahnya.
     *
     * 🔴 Untuk langkah yang penyetujunya DIPILIH PEMOHON, kolom
     * `approver_employee_ids` berisi TEPAT SATU id — pilihan itu (Keputusan
     * D126). Jadi pemeriksaan di bawah otomatis menyempit ke orang tersebut,
     * tanpa cabang khusus: pembekuannya terjadi saat menyalin, bukan saat
     * memeriksa.
     *
     * @param  array<int>  $roleIds  Role yang dipegang karyawan tersebut.
     */
    public function allows(int $employeeId, array $roleIds): bool
    {
        // Pilihan pemohon selalu menang atas definisi tipe: bila langkah ini
        // ditujukan ke satu orang tertentu, hanya dia yang boleh bertindak —
        // meski tipenya `role` dan orang lain memegang role yang sama.
        if ($this->chosen_by_requester) {
            return in_array($employeeId, array_map('intval', $this->approver_employee_ids ?? []), true);
        }

        return match ($this->approver_type) {
            PurchaseRequestApprovalStep::TYPE_ROLE
                => $this->approver_role_id !== null
                   && in_array((int) $this->approver_role_id, $roleIds, true),

            PurchaseRequestApprovalStep::TYPE_EMPLOYEE
                => in_array($employeeId, array_map('intval', $this->approver_employee_ids ?? []), true),

            // Belum dapat dijalankan: hierarki atasan belum ada di basis data.
            // Dikembalikan false secara eksplisit, bukan dibiarkan jatuh ke
            // default, supaya jelas ini keadaan yang disengaja.
            PurchaseRequestApprovalStep::TYPE_DIRECT_MANAGER => false,

            default => false,
        };
    }

    /** Ringkasan penyetuju untuk ditampilkan di layar. */
    public function approverLabel(): string
    {
        if ($this->chosen_by_requester) {
            $ids  = array_map('intval', $this->approver_employee_ids ?? []);
            $name = $ids === []
                ? null
                : Employee::with('basicData')->find($ids[0])?->basicData?->nick_name;

            return $name ? $name . ' (chosen by requester)' : 'Chosen by requester';
        }

        return match ($this->approver_type) {
            PurchaseRequestApprovalStep::TYPE_ROLE
                => $this->role?->name ?? 'Role #' . $this->approver_role_id,

            PurchaseRequestApprovalStep::TYPE_EMPLOYEE
                => count($this->approver_employee_ids ?? []) . ' selected employee(s)',

            PurchaseRequestApprovalStep::TYPE_DIRECT_MANAGER => 'Direct manager (not available yet)',

            default => $this->approver_type,
        };
    }
}
