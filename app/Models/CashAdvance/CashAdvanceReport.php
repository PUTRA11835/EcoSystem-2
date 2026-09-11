<?php

namespace App\Models\CashAdvance;

use App\Models\Employee;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Cash Advance Report (CAR) — pertanggungjawaban atas satu Cash Advance.
 *
 * CA menjawab "berapa uang yang saya minta"; CAR menjawab "berapa yang
 * benar-benar saya belanjakan, dan berapa sisanya". Tanpa CAR, sebuah CA yang
 * disetujui adalah uang keluar yang tidak pernah ditutup bukunya.
 *
 * ── advance_amount DIBEKUKAN, TIDAK DIBACA DARI RELASI (Keputusan D139) ────
 * 🔴 Kolom yang paling mudah dianggap mubazir — "kan tinggal ambil dari
 * cashAdvance->amount". Justru itu masalahnya. Setelan
 * `allow_approver_adjust_amount` membuka jalan bagi penyetuju mengubah nominal
 * CA; bila CAR membacanya lewat relasi, selisih pada laporan YANG SUDAH
 * DITANDATANGANI berubah sendiri, dan kertas yang sudah dicetak jadi berbeda
 * dari layar.
 *
 * ── TIGA ANGKA, SATU JALUR TULIS ───────────────────────────────────────────
 *   reported_amount    jumlah seluruh baris realisasi
 *   difference_amount  advance_amount - reported_amount
 *   settlement_type    refund | claim | exact  (turunan tanda difference)
 *
 * Ketiganya HANYA ditulis CashAdvanceReportService::recalculateTotals(), di
 * dalam transaksi yang sama dengan penyimpanan itemnya. Menghitungnya saat
 * dibaca terdengar lebih aman sampai seseorang mengedit item dokumen yang sudah
 * disetujui — yang tercetak harus angka yang DISETUJUI, bukan angka yang
 * kebetulan berlaku saat halaman dibuka (alasan sama dengan D104).
 *
 * `settlement_type` disimpan meski dapat diturunkan dari tanda
 * `difference_amount`, karena ia dipakai MENYARING ("tampilkan CAR yang masih
 * menunggu pengembalian") — dan menyaring berdasarkan ekspresi menghalangi
 * indeks. Nilai `exact` bukan kemewahan: selisih nol berbeda artinya dari
 * "belum dihitung", dan keduanya tidak boleh terlihat sama.
 */
class CashAdvanceReport extends Model
{
    use SoftDeletes;

    protected $table = 'cash_advance_reports';

    protected $fillable = [
        'report_no', 'cash_advance_id', 'employee_id', 'created_by',
        'report_date', 'description', 'currency',
        'advance_amount', 'reported_amount', 'difference_amount',
        'settlement_type', 'item_count', 'notes',
        'status', 'current_step_order',
        'cancelled_at', 'cancelled_by', 'completed_at',
        'period_year', 'period_month', 'flags',
        'deleted_by', 'delete_reason',
    ];

    protected $casts = [
        'report_date'        => 'date',
        'advance_amount'     => 'decimal:2',
        'reported_amount'    => 'decimal:2',
        'difference_amount'  => 'decimal:2',
        'item_count'         => 'integer',
        'current_step_order' => 'integer',
        'cancelled_at'       => 'datetime',
        'completed_at'       => 'datetime',
        'period_year'        => 'integer',
        'period_month'       => 'integer',
        'flags'              => 'array',
    ];

    // ── Status dokumen — LIMA nilai, sama persis dengan CA ──────────────────

    public const STATUS_SUBMITTED = 'submitted';
    public const STATUS_IN_REVIEW = 'in_review';
    public const STATUS_APPROVED  = 'approved';
    public const STATUS_REJECTED  = 'rejected';
    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [
        self::STATUS_SUBMITTED,
        self::STATUS_IN_REVIEW,
        self::STATUS_APPROVED,
        self::STATUS_REJECTED,
        self::STATUS_CANCELLED,
    ];

    public const STATUS_LABELS = [
        self::STATUS_SUBMITTED => 'Submitted',
        self::STATUS_IN_REVIEW => 'In review',
        self::STATUS_APPROVED  => 'Approved',
        self::STATUS_REJECTED  => 'Rejected',
        self::STATUS_CANCELLED => 'Cancelled',
    ];

    public const OPEN_STATUSES = [
        self::STATUS_SUBMITTED,
        self::STATUS_IN_REVIEW,
    ];

    // ── Jenis penyelesaian ──────────────────────────────────────────────────

    /** Realisasi lebih kecil — ada sisa yang dikembalikan karyawan. */
    public const SETTLEMENT_REFUND = 'refund';

    /** Realisasi lebih besar — kekurangan yang ditagihkan ke perusahaan. */
    public const SETTLEMENT_CLAIM = 'claim';

    /** Pas. Berbeda artinya dari "belum dihitung" — lihat docblock. */
    public const SETTLEMENT_EXACT = 'exact';

    public const SETTLEMENT_TYPES = [
        self::SETTLEMENT_REFUND,
        self::SETTLEMENT_CLAIM,
        self::SETTLEMENT_EXACT,
    ];

    public const SETTLEMENT_LABELS = [
        self::SETTLEMENT_REFUND => 'Refund',
        self::SETTLEMENT_CLAIM  => 'Claim',
        self::SETTLEMENT_EXACT  => 'Exact',
    ];

    // ── Relationships ───────────────────────────────────────────────────────

    public function cashAdvance()
    {
        return $this->belongsTo(CashAdvance::class, 'cash_advance_id', 'id');
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id', 'employee_id');
    }

    public function creator()
    {
        return $this->belongsTo(Employee::class, 'created_by', 'employee_id');
    }

    public function canceller()
    {
        return $this->belongsTo(Employee::class, 'cancelled_by', 'employee_id');
    }

    public function deleter()
    {
        return $this->belongsTo(Employee::class, 'deleted_by', 'employee_id');
    }

    public function items()
    {
        return $this->hasMany(CashAdvanceReportItem::class, 'cash_advance_report_id', 'id')
                    ->orderBy('line_no');
    }

    public function approvals()
    {
        return $this->hasMany(CashAdvanceReportApproval::class, 'cash_advance_report_id', 'id')
                    ->orderBy('order_seq');
    }

    // ── Scopes ──────────────────────────────────────────────────────────────

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', self::OPEN_STATUSES);
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_APPROVED);
    }

    public function scopeForPeriod(Builder $query, int $year, int $month): Builder
    {
        return $query->where('period_year', $year)->where('period_month', $month);
    }

    // ── Helpers ─────────────────────────────────────────────────────────────

    public function isOpen(): bool
    {
        return in_array($this->status, self::OPEN_STATUSES, true);
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function settlementLabel(): string
    {
        return self::SETTLEMENT_LABELS[$this->settlement_type] ?? (string) $this->settlement_type;
    }

    /** Langkah yang sedang menunggu tindakan, bila ada. */
    public function currentApproval(): ?CashAdvanceReportApproval
    {
        if (! $this->isOpen() || $this->current_step_order === null) {
            return null;
        }

        return $this->approvals()
                    ->where('order_seq', $this->current_step_order)
                    ->first();
    }

    /**
     * Label status untuk layar.
     *
     * Bentuknya sama dengan CashAdvance::statusLabel(): dokumen yang masih
     * berjalan menyebut LANGKAH yang sedang menunggunya, karena itulah yang
     * benar-benar ingin diketahui pembacanya.
     */
    public function statusLabel(string $openWord = 'Waiting'): string
    {
        if (! $this->isOpen()) {
            return self::STATUS_LABELS[$this->status]
                ?? ucfirst(str_replace('_', ' ', $this->status));
        }

        $step = $this->currentStepName();

        return $step ? $openWord . ' ' . $step : 'In review';
    }

    public function currentStepName(): ?string
    {
        return $this->currentApproval()?->step_name;
    }

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

    /**
     * Boleh ditarik kembali pemohonnya? Hanya saat `submitted`, sama dengan CA.
     *
     * Setelan `allow_requester_cancel` adalah gerbang KEDUA, diperiksa service;
     * method ini hanya menjawab pertanyaan tentang keadaan dokumennya.
     */
    public function isCancellable(): bool
    {
        return $this->status === self::STATUS_SUBMITTED;
    }

    /**
     * Selisihnya menguntungkan siapa, dalam kalimat yang terbaca manusia.
     *
     * 🔴 `difference_amount` POSITIF berarti realisasi lebih kecil — ada sisa yang
     * dikembalikan KARYAWAN. Negatif berarti karyawan menalangi lebih dulu dan
     * perusahaan yang harus mengganti. Arahnya mudah tertukar saat dibaca cepat,
     * jadi kalimatnya dibuat di satu tempat.
     */
    public function settlementDirection(): string
    {
        return match ($this->settlement_type) {
            self::SETTLEMENT_REFUND => 'Employee returns the remainder',
            self::SETTLEMENT_CLAIM  => 'Company reimburses the shortfall',
            default                 => 'Fully spent, nothing to settle',
        };
    }

    public function addFlag(string $key, mixed $value = true): void
    {
        $flags       = $this->flags ?? [];
        $flags[$key] = $value;
        $this->flags = $flags;
    }

    public function hasFlag(string $key): bool
    {
        return array_key_exists($key, $this->flags ?? []);
    }
}
