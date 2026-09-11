<?php

namespace App\Models\CashAdvance;

use App\Models\Employee;
use App\Models\EmployeeRole;
use Illuminate\Database\Eloquent\Model;

/**
 * Satu langkah persetujuan pada SATU dokumen Cash Advance.
 *
 * Barisnya disalin dari CashAdvanceApprovalStep saat dokumen dibuat, sehingga
 * mengubah konfigurasi tidak pernah mengubah dokumen yang sedang berjalan.
 *
 * ── EMPAT HAL YANG DIBEKUKAN, DAN AKIBATNYA BILA TIDAK ─────────────────────
 *
 *   step_name              ganti nama langkah di Settings = riwayat lama ikut
 *                          berubah artinya
 *   🔴 actor_role          kolom inilah yang menentukan siapa tercetak di
 *                          "Approved by". Kalau dibaca dari konfigurasi saat
 *                          mencetak, mencetak ULANG dokumen lama setelah alur
 *                          diubah menghasilkan KERTAS BERBEDA — dengan nama
 *                          orang yang keliru di kolom persetujuan
 *   approver_role_id /     ganti kandidat di Settings = penyetuju dokumen yang
 *   approver_employee_ids  sedang menunggu ikut berubah
 *   chosen_by_requester    menandai baris yang penyetujunya DIPILIH PEMOHON.
 *                          Pertanyaan "kenapa dia yang menyetujui?" harus punya
 *                          jawaban di data, bukan di ingatan orang
 */
class CashAdvanceApproval extends Model
{
    protected $table = 'cash_advance_approvals';

    protected $fillable = [
        'cash_advance_id', 'order_seq',
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

    // ── Status ──────────────────────────────────────────────────────────────
    //
    // Nilainya `waiting`, bukan `pending` — menyamai overtime_request_approvals,
    // reimbursement_request_approvals, dan purchase_request_approvals
    // (Keputusan D147). Label "Pending" pada layar acuan tetap boleh dipakai;
    // yang diselaraskan adalah nilai yang DISIMPAN, supaya kueri lintas modul
    // tidak diam-diam kehilangan baris CA.

    public const STATUS_WAITING  = 'waiting';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    /**
     * Langkah yang tidak sempat dijalani karena dokumennya sudah selesai —
     * ditolak di langkah sebelumnya, atau dibatalkan pemohonnya.
     *
     * 🔴 Bukan hiasan: signatureColumns() MELEWATI langkah berstatus skipped,
     * sehingga cetakan dokumen batal tidak menampilkan kolom tanda tangan untuk
     * orang yang tidak akan pernah bertindak. Perilaku itu baru terlihat saat
     * cetakan Purchase Request dirender betulan di langkah P4.
     */
    public const STATUS_SKIPPED = 'skipped';

    public const STATUSES = [
        self::STATUS_WAITING,
        self::STATUS_APPROVED,
        self::STATUS_REJECTED,
        self::STATUS_SKIPPED,
    ];

    // ── Relationships ───────────────────────────────────────────────────────

    public function cashAdvance()
    {
        return $this->belongsTo(CashAdvance::class, 'cash_advance_id', 'id');
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

    /** "Verificator" / "Approver" — bagian KIRI label pada Approval Timeline. */
    public function actorLabel(): string
    {
        return CashAdvanceApprovalStep::ACTOR_LABELS[$this->actor_role]
            ?? ucfirst((string) $this->actor_role);
    }

    /**
     * Label lengkap timeline, meniru acuan: "Verificator - Verification".
     */
    public function timelineLabel(): string
    {
        return $this->actorLabel() . ' - ' . $this->step_name;
    }

    /**
     * Apakah karyawan ini termasuk penyetuju yang berhak pada langkah ini?
     *
     * Hanya memeriksa DEFINISI langkah. Pemeriksaan lain — apakah langkah ini
     * yang sedang menunggu, dan apakah pemohon boleh menyetujui dirinya sendiri
     * — dikerjakan CashAdvanceService, karena keduanya menyangkut keadaan
     * dokumen, bukan definisi langkahnya.
     *
     * 🔴 Untuk langkah yang penyetujunya DIPILIH PEMOHON, kolom
     * `approver_employee_ids` berisi TEPAT SATU id — pilihan itu. Jadi
     * pemeriksaan di bawah otomatis menyempit ke orang tersebut tanpa cabang
     * khusus: pembekuannya terjadi saat MENYALIN, bukan saat memeriksa.
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
            CashAdvanceApprovalStep::TYPE_ROLE
                => $this->approver_role_id !== null
                   && in_array((int) $this->approver_role_id, $roleIds, true),

            CashAdvanceApprovalStep::TYPE_EMPLOYEE
                => in_array($employeeId, array_map('intval', $this->approver_employee_ids ?? []), true),

            // Belum dapat dijalankan: hierarki atasan belum ada di basis data
            // (pekerjaan tertunda T.2). Dikembalikan false secara EKSPLISIT,
            // bukan dibiarkan jatuh ke default, supaya jelas ini disengaja.
            CashAdvanceApprovalStep::TYPE_DIRECT_MANAGER => false,

            default => false,
        };
    }

    /**
     * Ringkasan penyetuju untuk ditampilkan di layar.
     *
     * Kembarannya ada di CashAdvanceApprovalStep dan itu DISENGAJA: yang di sana
     * menjawab "langkah ini ditujukan ke siapa" (konfigurasi), yang ini menjawab
     * "dokumen ini menunggu siapa" (riwayat yang dibekukan). Menggabungkannya
     * berarti salah satu pertanyaan kehilangan jawabannya — pelajaran dari bug
     * 500 yang ditemukan pemilik sistem di langkah P8.
     */
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
