<?php

namespace App\Models\CashAdvance;

use App\Models\Attendance\Branch;
use App\Models\DeliveryProject;
use App\Models\Employee;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Pengajuan uang muka — dokumen keuangan pertama di modul HR & General yang
 * benar-benar MENGELUARKAN uang perusahaan.
 *
 * Satu deskripsi, satu nominal (Keputusan C3) — tidak ada tabel item. Cetakannya
 * merender satu baris tabel dengan Total di bawahnya, persis aplikasi acuan.
 *
 * ── DUA SUMBU STATUS, JANGAN DISATUKAN (Keputusan D140) ────────────────────
 *
 *   status             menjawab "apakah DOKUMENnya disetujui?"
 *   settlement_status  menjawab "apakah UANGnya sudah dipertanggungjawabkan?"
 *
 * CA yang `approved` + `unreported` adalah keadaan yang sepenuhnya normal — dan
 * justru itulah keadaan yang paling perlu dilihat bagian keuangan: uang sudah
 * keluar, pertanggungjawabannya belum masuk.
 *
 * 🔴 Menyatukannya (mis. menambah nilai `settled` ke `status`) membuat setiap
 * kueri "dokumen yang disetujui" harus menyebut dua nilai, dan yang lupa
 * menyebutnya akan diam-diam kehilangan baris. Kehilangan baris tidak pernah
 * memunculkan galat.
 *
 * ── ANGKA PENYELESAIAN DIHITUNG LALU DISIMPAN ──────────────────────────────
 * `reported_amount`, `outstanding_amount`, `settlement_status`, `settled_at`,
 * dan `car_count` HANYA boleh ditulis CashAdvanceReportService — dan selalu
 * dari JUMLAH seluruh CAR yang `approved`, bukan dari satu baris CAR terakhir
 * (syarat mengikat Keputusan D143, supaya sakelar `car_multiple_per_ca` benar
 * sejak hari pertama).
 */
class CashAdvance extends Model
{
    use SoftDeletes;

    protected $table = 'cash_advances';

    protected $fillable = [
        'request_no', 'employee_id', 'created_by',
        'request_date', 'request_date_to',
        'description', 'currency', 'amount',
        'detail_url', 'notes',
        'cost_center_type', 'charged_branch_id', 'charged_project_id', 'charged_to_label',
        'status', 'current_step_order',
        'cancelled_at', 'cancelled_by', 'completed_at',
        'settlement_status', 'reported_amount', 'outstanding_amount', 'settled_at', 'car_count',
        'period_year', 'period_month', 'flags',
        'deleted_by', 'delete_reason',
    ];

    protected $casts = [
        'request_date'       => 'date',
        'request_date_to'    => 'date',
        'amount'             => 'decimal:2',
        'current_step_order' => 'integer',
        'cancelled_at'       => 'datetime',
        'completed_at'       => 'datetime',
        'reported_amount'    => 'decimal:2',
        'outstanding_amount' => 'decimal:2',
        'settled_at'         => 'datetime',
        'car_count'          => 'integer',
        'period_year'        => 'integer',
        'period_month'       => 'integer',
        'flags'              => 'array',
    ];

    // ── Status dokumen (LIMA nilai) ─────────────────────────────────────────

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

    /** Dokumen masih berjalan — belum berakhir dengan cara apa pun. */
    public const OPEN_STATUSES = [
        self::STATUS_SUBMITTED,
        self::STATUS_IN_REVIEW,
    ];

    // ── Status penyelesaian (sumbu KEDUA) ───────────────────────────────────

    /** Belum ada CAR sama sekali. */
    public const SETTLE_UNREPORTED = 'unreported';

    /** Sudah ada CAR, tetapi belum seluruhnya disetujui. */
    public const SETTLE_REPORTING = 'reporting';

    /** Buku ditutup: seluruh CAR yang menempel sudah disetujui. */
    public const SETTLE_SETTLED = 'settled';

    public const SETTLEMENT_STATUSES = [
        self::SETTLE_UNREPORTED,
        self::SETTLE_REPORTING,
        self::SETTLE_SETTLED,
    ];

    public const SETTLEMENT_LABELS = [
        self::SETTLE_UNREPORTED => 'Not reported',
        self::SETTLE_REPORTING  => 'Reporting',
        self::SETTLE_SETTLED    => 'Settled',
    ];

    // ── Jenis pembebanan ────────────────────────────────────────────────────

    public const COST_CENTER_BRANCH  = 'branch';
    public const COST_CENTER_PROJECT = 'project';

    public const COST_CENTER_TYPES = [
        self::COST_CENTER_BRANCH,
        self::COST_CENTER_PROJECT,
    ];

    // ── Relationships ───────────────────────────────────────────────────────

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

    public function branch()
    {
        return $this->belongsTo(Branch::class, 'charged_branch_id', 'id');
    }

    public function project()
    {
        return $this->belongsTo(DeliveryProject::class, 'charged_project_id', 'id');
    }

    public function approvals()
    {
        return $this->hasMany(CashAdvanceApproval::class, 'cash_advance_id', 'id')
                    ->orderBy('order_seq');
    }

    public function reports()
    {
        return $this->hasMany(CashAdvanceReport::class, 'cash_advance_id', 'id')
                    ->orderBy('report_date');
    }

    // ── Scopes ──────────────────────────────────────────────────────────────

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', self::OPEN_STATUSES);
    }

    public function scopeForPeriod(Builder $query, int $year, int $month): Builder
    {
        return $query->where('period_year', $year)->where('period_month', $month);
    }

    /**
     * CA yang uangnya sudah keluar tetapi belum dipertanggungjawabkan.
     *
     * Inilah daftar yang paling berguna bagi bagian keuangan, dan alasan kedua
     * sumbu status dipisah.
     */
    public function scopeOutstanding(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_APPROVED)
                     ->where('settlement_status', '!=', self::SETTLE_SETTLED);
    }

    // ── Helpers ─────────────────────────────────────────────────────────────

    public function isOpen(): bool
    {
        return in_array($this->status, self::OPEN_STATUSES, true);
    }

    /**
     * Boleh ditarik kembali pemohonnya? (Keputusan D131)
     *
     * 🔴 HANYA saat `submitted`. Begitu satu penyetuju bertindak, statusnya jadi
     * `in_review` dan tombolnya hilang — penyetuju yang sudah meluangkan waktu
     * meninjau tidak boleh kehilangan pekerjaannya karena pemohon berubah
     * pikiran. Setelan `allow_requester_cancel` adalah gerbang KEDUA, diperiksa
     * service; method ini hanya menjawab pertanyaan tentang keadaan dokumennya.
     */
    public function isCancellable(): bool
    {
        return $this->status === self::STATUS_SUBMITTED;
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function isSettled(): bool
    {
        return $this->settlement_status === self::SETTLE_SETTLED;
    }

    // 🔴 acceptsReport() SENGAJA TIDAK ADA DI SINI (Keputusan D154).
    //
    // Dulu ada, berbunyi `isApproved() && ! isSettled()`, dan itu MENYESATKAN.
    // Namanya menjanjikan jawaban atas "boleh dibuatkan CAR?", padahal ia hanya
    // menjawab "bukunya belum ditutup?". Model tidak membaca setelan, jadi ia
    // tidak tahu `car_multiple_per_ca` (D143) dan tidak melihat laporan yang
    // sedang berjalan. Akibatnya nyata: CA yang laporannya sudah dikirim tetap
    // menampilkan tombol "Create CAR", dan tombol itu selalu berakhir ditolak
    // service — dilaporkan pemilik sistem sebagai cacat.
    //
    // Satu-satunya yang berwenang menjawab adalah
    // CashAdvanceReportService::checkEligibility() (satu dokumen) atau
    // ::eligibleAdvanceIds() (satu halaman daftar). Method ini tidak
    // dihidupkan kembali: method yang menjadi jebakan lebih buruk daripada
    // tidak ada method sama sekali.

    /** Langkah yang sedang menunggu tindakan, bila ada. */
    public function currentApproval(): ?CashAdvanceApproval
    {
        if (! $this->isOpen() || $this->current_step_order === null) {
            return null;
        }

        return $this->approvals()
                    ->where('order_seq', $this->current_step_order)
                    ->first();
    }

    /**
     * Label rentang tanggal untuk rekap — meniru dua baris pada acuan
     * ("18.08.2026" / "until 18.08.2026").
     */
    public function dateRangeLabel(): ?string
    {
        return $this->request_date_to
            ? 'until ' . $this->request_date_to->format('d.m.Y')
            : null;
    }


    /**
     * Label status untuk layar.
     *
     * Dokumen yang masih berjalan menyebut LANGKAH yang sedang menunggunya —
     * "Waiting Verification" jauh lebih berguna daripada "In review", karena ia
     * menjawab pertanyaan yang sebenarnya: menunggu siapa.
     *
     * Kata pembukanya berbeda menurut tempat: rekap HR memakai "Pending",
     * halaman detail dan ESS memakai "Waiting". Konvensi yang sama sudah berlaku
     * di Reimbursement dan Purchase Request.
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

    /**
     * Siapa yang tercantum di kolom APPROVER pada rekap.
     *
     * Dokumen berjalan menyebut penyetuju yang sedang ditunggu; dokumen yang
     * sudah selesai menyebut orang yang TERAKHIR bertindak — bukan kandidat,
     * karena pertanyaan yang dijawab kolom itu berubah begitu dokumennya tutup.
     */
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

    /** Label status penyelesaian untuk kolom CAR pada rekap. */
    public function settlementLabel(): string
    {
        return self::SETTLEMENT_LABELS[$this->settlement_status]
            ?? ucfirst(str_replace('_', ' ', (string) $this->settlement_status));
    }

    /**
     * Menandai dokumen dengan sinyal anomali tanpa menimpa sinyal lain.
     *
     * Pola D10: `flags` adalah kantong terbuka karena jenis sinyalnya akan
     * bertambah (lewat batas nominal, lewat tenggat CAR, alur diperluas saat
     * dokumen berjalan). Menyimpannya sebagai kolom boolean satu per satu
     * berarti satu migrasi setiap kali muncul sinyal baru.
     */
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
