<?php

namespace App\Models\Reimbursement;

use App\Models\Attendance\Branch;
use Illuminate\Database\Eloquent\Model;

/**
 * Satu baris biaya di dalam dokumen reimbursement — satu baris = satu nota.
 *
 * `cost_center_label` sudah DIBEKUKAN saat submit (Keputusan D105). Jangan
 * menyusunnya ulang dari relasi `branch` saat membaca: nama cabang dapat berubah
 * atau dinonaktifkan, dan dokumen yang berujung ke pembayaran harus tetap
 * terbaca persis seperti saat disetujui.
 */
class ReimbursementItem extends Model
{
    protected $table = 'reimbursement_items';

    protected $fillable = [
        'reimbursement_request_id', 'line_no', 'description',
        'cost_center_type', 'branch_id', 'delivery_project_id', 'cost_center_label',
        'receipt_no', 'receipt_date_from', 'receipt_date_to',
        'currency', 'amount',
    ];

    protected $casts = [
        'line_no'           => 'integer',
        'branch_id'         => 'integer',
        'receipt_date_from' => 'date',
        'receipt_date_to'   => 'date',
        'amount'            => 'decimal:2',
    ];

    // ── Jenis pembebanan biaya ──────────────────────────────────────────────

    /** Dibebankan ke cabang / kantor. Satu-satunya yang aktif hari ini. */
    public const COST_CENTER_BRANCH = 'branch';

    /**
     * Dibebankan ke proyek pelanggan.
     *
     * 🔴 BELUM DAPAT DIPILIH. Nilainya didaftarkan sejak awal supaya
     * pengaktifannya nanti tidak memerlukan migrasi — pilihannya dinonaktifkan
     * di UI (Keputusan D103, jawaban R1). Pola yang sama dengan
     * ReimbursementApprovalStep::TYPE_DIRECT_MANAGER.
     */
    public const COST_CENTER_PROJECT = 'project';

    public const COST_CENTER_TYPES = [
        self::COST_CENTER_BRANCH,
        self::COST_CENTER_PROJECT,
    ];

    /** Jenis yang benar-benar dapat dipilih hari ini. */
    public const SELECTABLE_COST_CENTER_TYPES = [
        self::COST_CENTER_BRANCH,
    ];

    // ── Relationships ───────────────────────────────────────────────────────

    public function request()
    {
        return $this->belongsTo(ReimbursementRequest::class, 'reimbursement_request_id', 'id');
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class, 'branch_id', 'id');
    }

    // ── Helpers ─────────────────────────────────────────────────────────────

    /** "20 Agu 2026" atau "20 Agu 2026 s.d 22 Agu 2026" bila rentangnya beda. */
    public function receiptDateLabel(): string
    {
        $from = $this->receipt_date_from?->format('d M Y');
        $to   = $this->receipt_date_to?->format('d M Y');

        if (!$from) {
            return '—';
        }

        return ($to === null || $to === $from) ? $from : $from . ' s.d ' . $to;
    }

    /**
     * Baris deskripsi untuk berkas Excel.
     *
     * Bentuknya mengikuti berkas acuan:
     *   "Testing Reimbursement 21/08/2026 - 21/08/2026 (EC-JOGJA)"
     *
     * Kode cabang diambil dari relasi bila masih ada, dan jatuh ke
     * `cost_center_label` yang sudah dibekukan bila cabangnya sudah dihapus.
     */
    public function exportDescription(): string
    {
        $range = trim(
            ($this->receipt_date_from?->format('d/m/Y') ?? '')
            . ' - '
            . ($this->receipt_date_to?->format('d/m/Y') ?? '')
        );

        $code = $this->branch?->code ?? $this->cost_center_label;

        return trim($this->description . ' ' . $range . ($code ? ' (' . $code . ')' : ''));
    }
}
