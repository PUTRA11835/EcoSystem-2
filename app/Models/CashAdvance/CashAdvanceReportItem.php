<?php

namespace App\Models\CashAdvance;

use App\Models\Attendance\Branch;
use App\Models\DeliveryProject;
use Illuminate\Database\Eloquent\Model;

/**
 * Satu baris realisasi belanja pada sebuah Cash Advance Report.
 *
 * Di sinilah letak asimetri pokok sub-modul ini: CA punya SATU deskripsi dan
 * SATU nominal — permintaannya memang sederhana ("saya butuh 350.000 untuk beli
 * kamera") — sedangkan CAR hampir selalu MULTI-BARIS, karena uangnya terpakai
 * untuk beberapa hal dengan beberapa nota. Menyamakan bentuk keduanya demi
 * kerapian justru memaksa salah satunya jadi canggung.
 *
 * 🔴 NAMA FIELD DI FORM MEMAKAI KUNCI ACAK: `items[<uuid>][amount]`, BUKAN
 * `items[0][amount]`. Baris ditambah dan dihapus lewat JavaScript; dengan indeks
 * berurutan, menghapus baris di tengah membuat sisanya bergeser dan pesan
 * validasi menunjuk baris yang salah. Server mengurutkan ulang jadi `line_no`
 * saat menyimpan. Pelajaran ini sudah dibayar di Reimbursement dan diulang di
 * Purchase Request.
 *
 * ── SATU BARIS, SATU TIPE PEMBEBANAN ───────────────────────────────────────
 * CA membebankan seluruh dokumen ke satu tempat (bila dipakai sama sekali);
 * CAR membebankan PER BARIS, karena satu uang muka wajar terpakai di dua tempat.
 * `cost_center_type` menentukan kolom mana yang boleh terisi, dan yang lain
 * DIPAKSA NULL di server — bukan hanya disembunyikan di layar (aturan D127).
 *
 * `cost_center_label` DIBEKUKAN saat submit: cabang bisa dinonaktifkan dan
 * proyek bisa ditutup, sementara dokumen lama harus tetap terbaca (D105).
 */
class CashAdvanceReportItem extends Model
{
    protected $table = 'cash_advance_report_items';

    protected $fillable = [
        'cash_advance_report_id', 'line_no',
        'expense_date', 'description', 'receipt_no', 'amount', 'receipt_url',
        'cost_center_type', 'branch_id', 'delivery_project_id', 'cost_center_label',
    ];

    protected $casts = [
        'line_no'      => 'integer',
        'expense_date' => 'date',
        'amount'       => 'decimal:2',
    ];

    // Jenis pembebanan memakai konstanta CashAdvance supaya CA dan CAR tidak
    // pernah berbeda pendapat tentang arti 'branch' / 'project'.
    public const COST_CENTER_BRANCH  = CashAdvance::COST_CENTER_BRANCH;
    public const COST_CENTER_PROJECT = CashAdvance::COST_CENTER_PROJECT;
    public const COST_CENTER_TYPES   = CashAdvance::COST_CENTER_TYPES;

    // ── Relationships ───────────────────────────────────────────────────────

    public function report()
    {
        return $this->belongsTo(CashAdvanceReport::class, 'cash_advance_report_id', 'id');
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

    /**
     * Label pembebanan yang ditampilkan.
     *
     * Selalu mengutamakan label yang DIBEKUKAN. Relasinya hanya dipakai sebagai
     * cadangan untuk baris lama yang labelnya belum sempat terisi — bukan
     * sebagai sumber utama, karena nama cabang bisa sudah berubah sejak dokumen
     * ditandatangani.
     */
    public function costCenterLabel(): string
    {
        if ($this->cost_center_label) {
            return $this->cost_center_label;
        }

        return match ($this->cost_center_type) {
            self::COST_CENTER_BRANCH  => $this->branch?->name ?? '—',
            self::COST_CENTER_PROJECT => $this->project?->name ?? '—',
            default                   => '—',
        };
    }
}
