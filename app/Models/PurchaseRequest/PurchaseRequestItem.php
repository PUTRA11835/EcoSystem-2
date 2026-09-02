<?php

namespace App\Models\PurchaseRequest;

use App\Models\Attendance\Branch;
use App\Models\DeliveryProject;
use Illuminate\Database\Eloquent\Model;

/**
 * Satu baris permintaan di dalam dokumen Purchase Request.
 *
 * `cost_center_label` sudah DIBEKUKAN saat submit (Keputusan D105 & D127).
 * Jangan menyusunnya ulang dari relasi `branch` / `project` saat membaca: nama
 * cabang dapat berubah dan proyek dapat ditutup, sementara dokumen yang menjadi
 * dasar pengadaan harus tetap terbaca persis seperti saat disetujui. Relasinya
 * ada untuk PENYARINGAN dan penautan, bukan untuk menampilkan label.
 */
class PurchaseRequestItem extends Model
{
    protected $table = 'purchase_request_items';

    protected $fillable = [
        'purchase_request_id', 'line_no', 'description',
        'qty', 'unit',
        'period_from', 'period_to', 'use_date',
        'cost_center_type', 'branch_id', 'delivery_project_id', 'cost_center_label',
        'estimated_unit_price', 'estimated_amount',
    ];

    protected $casts = [
        'line_no'              => 'integer',
        'qty'                  => 'decimal:2',
        'branch_id'            => 'integer',
        'delivery_project_id'  => 'integer',
        'period_from'          => 'date',
        'period_to'            => 'date',
        'use_date'             => 'date',
        'estimated_unit_price' => 'decimal:2',
        'estimated_amount'     => 'decimal:2',
    ];

    // ── Jenis pembebanan biaya (Keputusan D127) ─────────────────────────────
    //
    // 🔴 BERBEDA dari ReimbursementItem: di sana `project` didaftarkan tetapi
    // pilihannya dimatikan di UI (D103). Di sini KEDUANYA HIDUP, karena datanya
    // terbukti ada — 22 dari 23 `delivery_projects` belum ditutup, lengkap
    // dengan `io_number`.

    /** Dibebankan ke cabang / kantor. */
    public const COST_CENTER_BRANCH = 'branch';

    /** Dibebankan ke proyek delivery. */
    public const COST_CENTER_PROJECT = 'project';

    public const COST_CENTER_TYPES = [
        self::COST_CENTER_BRANCH,
        self::COST_CENTER_PROJECT,
    ];

    public const COST_CENTER_LABELS = [
        self::COST_CENTER_BRANCH  => 'Branch',
        self::COST_CENTER_PROJECT => 'Project',
    ];

    // ── Relationships ───────────────────────────────────────────────────────

    public function request()
    {
        return $this->belongsTo(PurchaseRequest::class, 'purchase_request_id', 'id');
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class, 'branch_id', 'id');
    }

    public function project()
    {
        return $this->belongsTo(DeliveryProject::class, 'delivery_project_id', 'id');
    }

    // ── Helpers ─────────────────────────────────────────────────────────────

    /** "2 UNIT" — dipakai di rekap, detail, cetakan, dan Excel. */
    public function qtyLabel(): string
    {
        return self::formatQty($this->qty) . ' ' . $this->unit;
    }

    /**
     * Kuantitas tanpa desimal yang tidak berarti.
     *
     * 2.00 dicetak "2", 0.50 dicetak "0,5". Kolomnya decimal(12,2) karena 0,5
     * LOT itu wajar, tetapi mencetak "2,00 UNIT" untuk dua unit hanya membuat
     * dokumen terlihat seperti nominal uang — dan dokumen ini justru sengaja
     * tidak punya nominal.
     */
    public static function formatQty($qty): string
    {
        $value = (float) $qty;

        return floor($value) === $value
            ? number_format($value, 0, ',', '.')
            : rtrim(rtrim(number_format($value, 2, ',', '.'), '0'), ',');
    }

    /** "01/09/2026 - 30/09/2026", atau "—" bila periodenya tidak diisi. */
    public function periodLabel(): string
    {
        $from = $this->period_from?->format('d/m/Y');
        $to   = $this->period_to?->format('d/m/Y');

        if (!$from && !$to) {
            return '—';
        }

        if (!$to || $to === $from) {
            return $from ?? '—';
        }

        return $from . ' - ' . $to;
    }

    public function useDateLabel(): string
    {
        return $this->use_date?->format('d/m/Y') ?? '—';
    }

    /**
     * Label pembebanan yang aman ditampilkan.
     *
     * Selalu memakai nilai yang DIBEKUKAN. Relasi hanya dipakai sebagai cadangan
     * untuk baris lama yang labelnya belum sempat terisi — bukan sebaliknya.
     */
    public function costCenterLabel(): string
    {
        if ($this->cost_center_label) {
            return $this->cost_center_label;
        }

        return $this->cost_center_type === self::COST_CENTER_PROJECT
            ? ($this->project?->name ?? '—')
            : ($this->branch?->name ?? '—');
    }

    /** "Branch" / "Project" — dipakai lencana kecil di sebelah label. */
    public function costCenterTypeLabel(): string
    {
        return self::COST_CENTER_LABELS[$this->cost_center_type] ?? $this->cost_center_type;
    }
}
