<?php

namespace App\Models\CashAdvance;

use App\Models\Employee;
use App\Models\EmployeeRole;
use Illuminate\Database\Eloquent\Model;

/**
 * Satu langkah persetujuan pada SATU dokumen Cash Advance Report.
 *
 * Cermin CashAdvanceApproval, dengan alasan yang persis sama: langkah DISALIN
 * bukan dirujuk, `step_name` dan `actor_role` ikut dibekukan, dan status
 * `skipped` membuat cetakan dokumen batal tidak menampilkan kolom tanda tangan
 * untuk orang yang tidak akan pernah bertindak.
 *
 * ── KENAPA TABEL SENDIRI, PADAHAL TABEL LANGKAHNYA DIBAGI ──────────────────
 * 🔴 Pertanyaan yang wajar, dan jawabannya bukan konsistensi melainkan bentuk
 * kunci asing. `cash_advance_approvals.cash_advance_id` menunjuk `cash_advances`;
 * baris CAR harus menunjuk `cash_advance_reports`. Menyatukannya berarti
 * membuang kunci asing dan menggantinya dengan pasangan (`document_type`,
 * `document_id`) yang TIDAK DAPAT ditegakkan basis data — persis jenis relasi
 * yang membiarkan baris yatim lahir diam-diam.
 *
 * Tabel CETAKAN langkah boleh dibagi karena ia tidak menunjuk dokumen apa pun;
 * tabel RIWAYAT tidak, karena justru itu satu-satunya tugasnya.
 *
 * ── SATU PERBEDAAN PERILAKU, dan letaknya di SERVICE bukan di sini ─────────
 * Menyetujui CAR pada langkah TERAKHIR ikut menutup buku CA induknya —
 * `settlement_status`, `reported_amount`, `outstanding_amount`, dan `settled_at`
 * pada `cash_advances` diperbarui dalam transaksi yang SAMA. Menyetujui laporan
 * lalu gagal menandai uang mukanya selesai adalah keadaan setengah jadi yang
 * tidak boleh bisa terjadi.
 */
class CashAdvanceReportApproval extends Model
{
    protected $table = 'cash_advance_report_approvals';

    protected $fillable = [
        'cash_advance_report_id', 'order_seq',
        'step_name', 'actor_role',
        'approver_type', 'approver_role_id', 'approver_employee_ids',
        'chosen_by_requester',
        'status', 'acted_by', 'acted_at', 'notes', 'flags',
    ];

    protected $casts = [
        'order_seq'             => 'integer',
        'approver_role_id'      => 'integer',
        'approver_employee_ids' => 'array',
        'chosen_by_requester'   => 'boolean',
        'acted_at'              => 'datetime',
        'flags'                 => 'array',
    ];

    // Nilai status dipinjam dari CashAdvanceApproval supaya CA dan CAR tidak
    // pernah berbeda pendapat tentang arti sebuah status (Keputusan D147).
    public const STATUS_WAITING  = CashAdvanceApproval::STATUS_WAITING;
    public const STATUS_APPROVED = CashAdvanceApproval::STATUS_APPROVED;
    public const STATUS_REJECTED = CashAdvanceApproval::STATUS_REJECTED;
    public const STATUS_SKIPPED  = CashAdvanceApproval::STATUS_SKIPPED;

    public const STATUSES = CashAdvanceApproval::STATUSES;

    // ── Relationships ───────────────────────────────────────────────────────

    public function report()
    {
        return $this->belongsTo(CashAdvanceReport::class, 'cash_advance_report_id', 'id');
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

    public function isApproverStep(): bool
    {
        return $this->actor_role === CashAdvanceApprovalStep::ACTOR_APPROVER;
    }

    public function actorLabel(): string
    {
        return CashAdvanceApprovalStep::ACTOR_LABELS[$this->actor_role]
            ?? ucfirst((string) $this->actor_role);
    }

    public function timelineLabel(): string
    {
        return $this->actorLabel() . ' - ' . $this->step_name;
    }

    /**
     * Apakah karyawan ini termasuk penyetuju yang berhak pada langkah ini?
     *
     * Salinan sengaja dari CashAdvanceApproval::allows(). Menariknya ke trait
     * bersama sudah dipertimbangkan dan ditolak: dua tabel riwayat ini akan
     * berbeda jalan begitu CAR punya aturannya sendiri (mis. langkah tambahan
     * saat selisihnya `claim`), dan trait yang dipakai dua kelas dengan masa
     * depan berbeda adalah kopling yang baru terasa mahal saat dilepas.
     *
     * @param  array<int>  $roleIds  Role yang dipegang karyawan tersebut.
     */
    public function allows(int $employeeId, array $roleIds): bool
    {
        if ($this->chosen_by_requester) {
            return in_array($employeeId, array_map('intval', $this->approver_employee_ids ?? []), true);
        }

        return match ($this->approver_type) {
            CashAdvanceApprovalStep::TYPE_ROLE
                => $this->approver_role_id !== null
                   && in_array((int) $this->approver_role_id, $roleIds, true),

            CashAdvanceApprovalStep::TYPE_EMPLOYEE
                => in_array($employeeId, array_map('intval', $this->approver_employee_ids ?? []), true),

            CashAdvanceApprovalStep::TYPE_DIRECT_MANAGER => false,

            default => false,
        };
    }

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
            CashAdvanceApprovalStep::TYPE_ROLE
                => $this->role?->name ?? 'Role #' . $this->approver_role_id,

            CashAdvanceApprovalStep::TYPE_EMPLOYEE
                => count($this->approver_employee_ids ?? []) . ' selected employee(s)',

            CashAdvanceApprovalStep::TYPE_DIRECT_MANAGER => 'Direct manager (not available yet)',

            default => (string) $this->approver_type,
        };
    }
}
