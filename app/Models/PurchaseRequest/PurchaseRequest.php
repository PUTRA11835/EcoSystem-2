<?php

namespace App\Models\PurchaseRequest;

use App\Models\Attendance\Branch;
use App\Models\DeliveryProject;
use App\Models\Employee;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Kepala dokumen Purchase Request.
 *
 * Satu baris = satu dokumen; baris permintaannya ada di PurchaseRequestItem.
 *
 * Memakai SoftDeletes dengan alasan yang sama seperti ReimbursementRequest
 * (Keputusan D109), meski PR bukan dokumen pembayaran: PR yang disetujui adalah
 * dasar pengadaan, dan bila barisnya lenyap tidak ada yang dapat menjawab
 * dokumen mana yang hilang, siapa yang menghapusnya, dan atas dasar apa.
 */
class PurchaseRequest extends Model
{
    use SoftDeletes;

    protected $table = 'purchase_requests';

    protected $fillable = [
        'request_no', 'employee_id', 'created_by',
        'request_date', 'title', 'notes',
        'cost_center_type', 'charged_branch_id', 'charged_project_id', 'charged_to_label',
        'item_count', 'qty_summary', 'estimated_total',
        'status', 'current_step_order',
        'flags',
        'period_year', 'period_month',
        'completed_at',
        'cancelled_at', 'cancelled_by',
        'converted_at', 'converted_by',
        'deleted_by', 'delete_reason',
    ];

    protected $casts = [
        'request_date'        => 'date',
        'charged_branch_id'   => 'integer',
        'charged_project_id'  => 'integer',
        'item_count'          => 'integer',
        'estimated_total'     => 'decimal:2',
        'current_step_order'  => 'integer',
        'flags'               => 'array',
        'period_year'         => 'integer',
        'period_month'        => 'integer',
        'completed_at'        => 'datetime',
        'cancelled_at'        => 'datetime',
        'converted_at'        => 'datetime',
    ];

    // ── Status ──────────────────────────────────────────────────────────────
    //
    // LIMA nilai (Keputusan D131), berbeda dari ReimbursementRequest yang hanya
    // empat. Di sana karyawan memang tidak dapat membatalkan (D111), dan
    // alasannya adalah sifat dokumen KEUANGAN — reimbursement yang disetujui
    // adalah dasar pembayaran. Purchase Request belum menimbulkan komitmen uang,
    // jadi sifat itu tidak berlaku, dan `cancelled` benar-benar dapat tercapai.

    /** Baru diajukan, belum ada satu langkah pun yang bertindak. */
    public const STATUS_SUBMITTED = 'submitted';

    /** Sudah lulus minimal satu langkah, masih menunggu langkah berikutnya. */
    public const STATUS_IN_REVIEW = 'in_review';

    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    /** Ditarik kembali oleh pemohonnya sendiri, sebelum ada yang meninjau. */
    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [
        self::STATUS_SUBMITTED,
        self::STATUS_IN_REVIEW,
        self::STATUS_APPROVED,
        self::STATUS_REJECTED,
        self::STATUS_CANCELLED,
    ];

    /** Status yang masih berjalan — masih menunggu tindakan seseorang. */
    public const OPEN_STATUSES = [
        self::STATUS_SUBMITTED,
        self::STATUS_IN_REVIEW,
    ];

    public const STATUS_LABELS = [
        self::STATUS_SUBMITTED => 'Submitted',
        self::STATUS_IN_REVIEW => 'In review',
        self::STATUS_APPROVED  => 'Approved',
        self::STATUS_REJECTED  => 'Rejected',
        self::STATUS_CANCELLED => 'Cancelled',
    ];

    // ── Pembebanan ──────────────────────────────────────────────────────────

    /** Dipakai `charged_to_label` bila itemnya lintas cabang/proyek. */
    public const LABEL_MULTIPLE = 'Multiple cost centers';

    /** Nilai `cost_center_type` header bila itemnya tidak seragam. */
    public const COST_CENTER_MIXED = 'mixed';

    // ── Flags ───────────────────────────────────────────────────────────────

    /** Ada baris tanpa tanggal pakai, sementara pengaturannya tidak menuntutnya. */
    public const FLAG_MISSING_USE_DATE = 'missing_use_date';

    /** Ada baris tanpa periode, sementara pengaturannya tidak menuntutnya. */
    public const FLAG_MISSING_PERIOD = 'missing_period';

    /** Ada baris tanpa cabang/proyek, sementara pengaturannya tidak menuntutnya. */
    public const FLAG_MISSING_COST_CENTER = 'missing_cost_center';

    /** Itemnya membebani lebih dari satu cabang/proyek — penanda NETRAL. */
    public const FLAG_MIXED_COST_CENTER = 'mixed_cost_center';

    /** Tanggal pakai sudah lewat saat dokumen diajukan — barang telat diminta. */
    public const FLAG_USE_DATE_PASSED = 'use_date_passed';

    /** Dibuat admin atas nama karyawan — penanda NETRAL. */
    public const FLAG_CREATED_ON_BEHALF = 'created_on_behalf';

    /** Disetujui oleh pemohonnya sendiri — selalu ditandai meski diizinkan. */
    public const FLAG_SELF_APPROVED = 'self_approved';

    /** Diproses saat periodenya sudah dikunci. */
    public const FLAG_LOCKED_PERIOD = 'locked_period';

    /** Isi dokumen diubah setelah diajukan. */
    public const FLAG_ITEMS_ADJUSTED = 'items_adjusted';

    /**
     * Langkah persetujuan DITAMBAHKAN setelah dokumen ini berjalan (D116).
     *
     * Penanda NETRAL, bukan anomali — justru tandanya kontrol diperketat.
     */
    public const FLAG_WORKFLOW_EXTENDED = 'workflow_extended';

    public const FLAG_LABELS = [
        self::FLAG_MISSING_USE_DATE    => 'No use date',
        self::FLAG_MISSING_PERIOD      => 'No period',
        self::FLAG_MISSING_COST_CENTER => 'No cost center',
        self::FLAG_MIXED_COST_CENTER   => 'Multiple cost centers',
        self::FLAG_USE_DATE_PASSED     => 'Use date already passed',
        self::FLAG_CREATED_ON_BEHALF   => 'Created on behalf',
        self::FLAG_SELF_APPROVED       => 'Self-approved',
        self::FLAG_LOCKED_PERIOD       => 'Locked period',
        self::FLAG_ITEMS_ADJUSTED      => 'Items adjusted',
        self::FLAG_WORKFLOW_EXTENDED   => 'Approval step added later',
    ];

    /**
     * Flag yang benar-benar perlu perhatian penyetuju.
     *
     * `mixed_cost_center`, `created_on_behalf`, dan `workflow_extended` sengaja
     * TIDAK termasuk: ketiganya penanda netral. Menandai keadaan yang lazim
     * sebagai janggal akan membuat seluruh penandaan kehilangan artinya —
     * pelajaran yang sama dengan `future_claim` pada Overtime dan `multi_branch`
     * pada Reimbursement.
     */
    public const ATTENTION_FLAGS = [
        self::FLAG_MISSING_USE_DATE,
        self::FLAG_MISSING_PERIOD,
        self::FLAG_MISSING_COST_CENTER,
        self::FLAG_USE_DATE_PASSED,
        self::FLAG_SELF_APPROVED,
        self::FLAG_LOCKED_PERIOD,
        self::FLAG_ITEMS_ADJUSTED,
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

    public function canceller()
    {
        return $this->belongsTo(Employee::class, 'cancelled_by', 'employee_id');
    }

    public function chargedBranch()
    {
        return $this->belongsTo(Branch::class, 'charged_branch_id', 'id');
    }

    public function chargedProject()
    {
        return $this->belongsTo(DeliveryProject::class, 'charged_project_id', 'id');
    }

    public function items()
    {
        return $this->hasMany(PurchaseRequestItem::class, 'purchase_request_id', 'id')
                    ->orderBy('line_no');
    }

    public function approvals()
    {
        return $this->hasMany(PurchaseRequestApproval::class, 'purchase_request_id', 'id')
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

    /** Hanya dokumen yang masih berjalan boleh diubah. */
    public function isEditable(): bool
    {
        return $this->isOpen();
    }

    /**
     * Bolehkah pemohon menariknya kembali? (Keputusan D131)
     *
     * HANYA saat belum satu pun langkah bertindak. Begitu ada satu approval,
     * status berubah jadi `in_review` dan syaratnya tidak lagi terpenuhi —
     * akibat langsung dari syarat ini, bukan aturan terpisah. Batasnya menjaga
     * agar penyetuju yang sudah meluangkan waktu meninjau tidak kehilangan
     * pekerjaannya karena pemohon berubah pikiran.
     *
     * Sakelar `allow_requester_cancel` diperiksa PurchaseRequestService, bukan
     * di sini: model tidak membaca konfigurasi.
     */
    public function isCancellable(): bool
    {
        return $this->status === self::STATUS_SUBMITTED;
    }

    public function hasFlag(string $flag): bool
    {
        return in_array($flag, $this->flags ?? [], true);
    }

    /** Flag yang perlu perhatian — dipakai mewarnai baris di rekap. */
    public function attentionFlags(): array
    {
        return array_values(array_intersect($this->flags ?? [], self::ATTENTION_FLAGS));
    }

    /** Langkah yang sedang menunggu tindakan, bila masih ada. */
    public function currentApproval(): ?PurchaseRequestApproval
    {
        if (!$this->isOpen() || $this->current_step_order === null) {
            return null;
        }

        return $this->approvals->firstWhere('order_seq', $this->current_step_order);
    }

    /** "Verification" — dipakai baris "Current step:" di rekap. */
    public function currentStepName(): ?string
    {
        return $this->currentApproval()?->step_name;
    }

    /**
     * Label status untuk layar.
     *
     * Nilai `status` tetap terbatas pada lima kemungkinan, tetapi label yang
     * dibaca pengguna mengambil NAMA LANGKAH yang sedang menunggu — sehingga
     * muncul "Waiting Verification" tanpa menambah nilai status baru setiap kali
     * alur persetujuan berubah.
     *
     * $openWord dapat diganti karena aplikasi acuan memakai dua kata untuk
     * keadaan yang sama: "Pending" di halaman rekap, "Waiting" di halaman detail
     * dan seluruh sisi karyawan. Bukan dua keadaan berbeda — dua penulisan untuk
     * keadaan yang satu.
     */
    public function statusLabel(string $openWord = 'Waiting'): string
    {
        if (!$this->isOpen()) {
            return self::STATUS_LABELS[$this->status]
                ?? ucfirst(str_replace('_', ' ', $this->status));
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

    /** "2 items" — kolom ITEM di rekap. */
    public function itemCountLabel(): string
    {
        return $this->item_count . ' ' . ($this->item_count === 1 ? 'item' : 'items');
    }

    /**
     * "10 PC · 5 SET" — pengganti "total" pada dokumen tanpa nominal.
     *
     * Dibaca dari kolom yang SUDAH DISIMPAN, bukan dijumlahkan ulang dari item:
     * yang tercetak harus ringkasan yang disetujui, bukan yang kebetulan berlaku
     * saat halaman dibuka (alasan yang sama dengan `total_amount` di D104).
     */
    public function qtySummaryLabel(): string
    {
        return $this->qty_summary ?: '—';
    }
}
