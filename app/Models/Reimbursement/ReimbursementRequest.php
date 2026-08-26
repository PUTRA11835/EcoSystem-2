<?php

namespace App\Models\Reimbursement;

use App\Models\Attendance\Branch;
use App\Models\Employee;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Kepala dokumen pengajuan reimbursement.
 *
 * Satu baris = satu dokumen; baris biayanya ada di ReimbursementItem.
 *
 * Memakai SoftDeletes, berbeda dari OvertimeRequest: dokumen yang berujung ke
 * pembayaran tidak boleh lenyap tanpa jejak (Keputusan D109). Yang dihapus tetap
 * menyimpan `deleted_by` dan `delete_reason`.
 */
class ReimbursementRequest extends Model
{
    use SoftDeletes;

    protected $table = 'reimbursement_requests';

    protected $fillable = [
        'request_no', 'employee_id', 'created_by',
        'request_date', 'title', 'supporting_url',
        'charged_branch_id', 'charged_to_label',
        'currency', 'total_amount', 'item_count',
        'status', 'current_step_order',
        'flags', 'notes',
        'period_year', 'period_month',
        'completed_at',
        'deleted_by', 'delete_reason',
    ];

    protected $casts = [
        'request_date'       => 'date',
        'charged_branch_id'  => 'integer',
        'total_amount'       => 'decimal:2',
        'item_count'         => 'integer',
        'current_step_order' => 'integer',
        'flags'              => 'array',
        'period_year'        => 'integer',
        'period_month'       => 'integer',
        'completed_at'       => 'datetime',
    ];

    // ── Status ──────────────────────────────────────────────────────────────
    //
    // EMPAT nilai saja (Keputusan D112). `cancelled` sengaja tidak ada: karyawan
    // tidak dapat membatalkan dokumennya (Keputusan D111), sehingga nilai itu
    // tidak akan pernah tercapai — dan nilai status yang tidak mungkin muncul
    // adalah kode mati yang menyesatkan pembacanya.
    //
    // Kolomnya bertipe string, jadi bila kelak dibutuhkan, menambahkannya
    // kembali berarti satu konstanta di sini — BUKAN migrasi.

    /** Baru diajukan, belum ada satu langkah pun yang bertindak. */
    public const STATUS_SUBMITTED = 'submitted';

    /** Sudah lulus minimal satu langkah, masih menunggu langkah berikutnya. */
    public const STATUS_IN_REVIEW = 'in_review';

    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    public const STATUSES = [
        self::STATUS_SUBMITTED,
        self::STATUS_IN_REVIEW,
        self::STATUS_APPROVED,
        self::STATUS_REJECTED,
    ];

    /** Status yang masih berjalan — masih menunggu tindakan seseorang. */
    public const OPEN_STATUSES = [
        self::STATUS_SUBMITTED,
        self::STATUS_IN_REVIEW,
    ];

    // ── Label pembebanan ────────────────────────────────────────────────────

    /** Dipakai `charged_to_label` bila itemnya lintas cabang. */
    public const LABEL_MULTIPLE_BRANCHES = 'Multiple branches';

    // ── Flags ───────────────────────────────────────────────────────────────

    /** Total melewati `max_request_amount` sementara kebijakannya `flag`. */
    public const FLAG_EXCEEDS_LIMIT = 'exceeds_limit';

    /** Tautan bukti tidak diisi sementara pengaturannya tidak menuntutnya. */
    public const FLAG_MISSING_SUPPORTING_URL = 'missing_supporting_url';

    /** Ada item tanpa nomor bukti. */
    public const FLAG_MISSING_RECEIPT_NO = 'missing_receipt_no';

    /** Ada item bernominal di bawah `min_item_amount`. */
    public const FLAG_BELOW_MIN_ITEM = 'below_min_item_amount';

    /** Itemnya membebani lebih dari satu cabang — penanda netral, bukan anomali. */
    public const FLAG_MULTI_BRANCH = 'multi_branch';

    /** Dibuat admin atas nama karyawan — penanda netral. */
    public const FLAG_CREATED_ON_BEHALF = 'created_on_behalf';

    /** Disetujui oleh pemohonnya sendiri — selalu ditandai meski diizinkan. */
    public const FLAG_SELF_APPROVED = 'self_approved';

    /** Diproses saat periodenya sudah dikunci. */
    public const FLAG_LOCKED_PERIOD = 'locked_period';

    /** Nominal disesuaikan penyetuju atau diubah HR setelah diajukan. */
    public const FLAG_AMOUNT_ADJUSTED = 'amount_adjusted';

    /**
     * Langkah persetujuan DITAMBAHKAN setelah dokumen ini berjalan.
     *
     * Penanda netral, bukan anomali — justru tandanya kontrol diperketat. Ada
     * supaya pertanyaan "kenapa dokumen ini tiba-tiba butuh satu tanda tangan
     * lagi?" selalu punya jawaban yang terlihat di layar, bukan hanya di log.
     */
    public const FLAG_WORKFLOW_EXTENDED = 'workflow_extended';

    public const FLAG_LABELS = [
        self::FLAG_EXCEEDS_LIMIT          => 'Exceeds limit',
        self::FLAG_MISSING_SUPPORTING_URL => 'No supporting document',
        self::FLAG_MISSING_RECEIPT_NO     => 'Missing receipt number',
        self::FLAG_BELOW_MIN_ITEM         => 'Item below minimum amount',
        self::FLAG_MULTI_BRANCH           => 'Multiple branches',
        self::FLAG_CREATED_ON_BEHALF      => 'Created on behalf',
        self::FLAG_SELF_APPROVED          => 'Self-approved',
        self::FLAG_LOCKED_PERIOD          => 'Locked period',
        self::FLAG_AMOUNT_ADJUSTED        => 'Amount adjusted',
        self::FLAG_WORKFLOW_EXTENDED      => 'Approval step added later',
    ];

    /**
     * Flag yang benar-benar perlu perhatian HR.
     *
     * `multi_branch`, `created_on_behalf`, dan `workflow_extended` sengaja TIDAK
     * termasuk: ketiganya penanda netral, bukan anomali. `workflow_extended`
     * bahkan menandakan kontrol yang DIPERKETAT. Menandai keadaan yang lazim
     * sebagai janggal akan membuat seluruh penandaan kehilangan artinya —
     * pelajaran yang sama dengan `future_claim` pada Overtime.
     */
    public const ATTENTION_FLAGS = [
        self::FLAG_EXCEEDS_LIMIT,
        self::FLAG_MISSING_SUPPORTING_URL,
        self::FLAG_MISSING_RECEIPT_NO,
        self::FLAG_BELOW_MIN_ITEM,
        self::FLAG_SELF_APPROVED,
        self::FLAG_LOCKED_PERIOD,
        self::FLAG_AMOUNT_ADJUSTED,
    ];

    // ── Relationships ───────────────────────────────────────────────────────

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id', 'employee_id');
    }

    /** Terisi hanya bila admin membuat dokumen ini atas nama karyawan. */
    public function creator()
    {
        return $this->belongsTo(Employee::class, 'created_by', 'employee_id');
    }

    public function remover()
    {
        return $this->belongsTo(Employee::class, 'deleted_by', 'employee_id');
    }

    public function chargedBranch()
    {
        return $this->belongsTo(Branch::class, 'charged_branch_id', 'id');
    }

    public function items()
    {
        return $this->hasMany(ReimbursementItem::class, 'reimbursement_request_id', 'id')
                    ->orderBy('line_no');
    }

    public function approvals()
    {
        return $this->hasMany(ReimbursementRequestApproval::class, 'reimbursement_request_id', 'id')
                    ->orderBy('order_seq');
    }

    // ── Scopes ──────────────────────────────────────────────────────────────

    public function scopeForEmployee($query, int $employeeId)
    {
        return $query->where('employee_id', $employeeId);
    }

    public function scopeOpen($query)
    {
        return $query->whereIn('status', self::OPEN_STATUSES);
    }

    public function scopeForPeriod($query, int $year, int $month)
    {
        return $query->where('period_year', $year)->where('period_month', $month);
    }

    // ── Helpers ─────────────────────────────────────────────────────────────

    public function isOpen(): bool
    {
        return in_array($this->status, self::OPEN_STATUSES, true);
    }

    /** Hanya dokumen yang masih berjalan boleh diubah HR. */
    public function isEditable(): bool
    {
        return $this->isOpen();
    }

    public function hasFlag(string $flag): bool
    {
        return in_array($flag, $this->flags ?? [], true);
    }

    /** Flag yang perlu perhatian HR — dipakai mewarnai baris di rekap. */
    public function attentionFlags(): array
    {
        return array_values(array_intersect($this->flags ?? [], self::ATTENTION_FLAGS));
    }

    /** Langkah yang sedang menunggu tindakan, bila masih ada. */
    public function currentApproval(): ?ReimbursementRequestApproval
    {
        if (!$this->isOpen() || $this->current_step_order === null) {
            return null;
        }

        return $this->approvals->firstWhere('order_seq', $this->current_step_order);
    }

    /** "GA Verification" — dipakai baris "Current step:" di rekap. */
    public function currentStepName(): ?string
    {
        return $this->currentApproval()?->step_name;
    }

    /**
     * Label status untuk layar.
     *
     * Nilai `status` tetap terbatas pada empat kemungkinan, tetapi label yang
     * dibaca pengguna mengambil NAMA LANGKAH yang sedang menunggu — sehingga
     * muncul "Waiting GA Verification" tanpa menambah nilai status baru setiap
     * kali alur persetujuan berubah.
     *
     * $openWord dapat diganti karena aplikasi acuan memakai dua kata untuk
     * keadaan yang sama: "Pending" di halaman rekap, "Waiting" di halaman detail
     * dan seluruh sisi karyawan (jawaban R11). Bukan dua keadaan berbeda — dua
     * penulisan untuk keadaan yang satu.
     */
    public function statusLabel(string $openWord = 'Waiting'): string
    {
        if (!$this->isOpen()) {
            return ucfirst(str_replace('_', ' ', $this->status));
        }

        $step = $this->currentStepName();

        return $step ? $openWord . ' ' . $step : 'In review';
    }

    /** Penyetuju yang relevan untuk kolom APPROVER di rekap. */
    public function approverLabel(): string
    {
        if ($this->isOpen()) {
            return $this->currentApproval()?->approverLabel() ?? '—';
        }

        $lastActed = $this->approvals
            ->whereNotNull('acted_at')
            ->sortByDesc('order_seq')
            ->first();

        return $lastActed?->actor?->basicData?->nick_name
            ?? $lastActed?->approverLabel()
            ?? '—';
    }

    /** "Rp 300.000" — dipakai di seluruh tampilan supaya formatnya tidak bercabang. */
    public function totalLabel(): string
    {
        return self::formatRupiah($this->total_amount);
    }

    public static function formatRupiah($amount): string
    {
        return 'Rp ' . number_format((float) $amount, 0, ',', '.');
    }
}
