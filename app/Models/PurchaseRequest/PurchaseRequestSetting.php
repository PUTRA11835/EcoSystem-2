<?php

namespace App\Models\PurchaseRequest;

use Illuminate\Database\Eloquent\Model;

/**
 * Konfigurasi global sub-modul Purchase Request. Tabel satu baris.
 *
 * Selalu diambil lewat PurchaseRequestSetting::current() supaya kode pemanggil
 * tidak perlu tahu bahwa tabelnya hanya berisi satu baris, dan supaya
 * pembacaannya tidak berulang dalam satu request. Meniru OvertimeSetting dan
 * ReimbursementSetting.
 *
 * 🔴 TIDAK PUNYA relasi penanda tangan, berbeda dari ReimbursementSetting
 * (Keputusan D129). Blok tanda tangan pada cetakan diturunkan dari LANGKAH ALUR
 * — lihat PurchaseRequestService::signatureColumns(). Menyimpan penanda tangan
 * di dua tempat hanya menciptakan satu kelas kesalahan baru.
 */
class PurchaseRequestSetting extends Model
{
    protected $table = 'purchase_request_settings';

    protected $fillable = [
        'company_name', 'use_branch_name_in_header',
        'allow_future_date', 'max_backdate_days',
        'require_use_date', 'require_period',
        'max_items_per_request', 'max_qty_per_item',
        'allowed_units', 'default_unit',
        'require_cost_center_per_item', 'allowed_cost_center_types',
        'require_title_min_chars',
        'allow_self_approval', 'self_approval_fallback_role_id',
        'allow_approver_adjust_items',
        'allow_requester_cancel',
        'locked_period_policy',
        'updated_by',
    ];

    protected $casts = [
        'use_branch_name_in_header'    => 'boolean',
        'allow_future_date'            => 'boolean',
        'max_backdate_days'            => 'integer',
        'require_use_date'             => 'boolean',
        'require_period'               => 'boolean',
        'max_items_per_request'        => 'integer',
        'max_qty_per_item'             => 'decimal:2',
        'require_cost_center_per_item' => 'boolean',
        'require_title_min_chars'      => 'integer',
        'allow_self_approval'          => 'boolean',
        'allow_approver_adjust_items'  => 'boolean',
        'allow_requester_cancel'       => 'boolean',
    ];

    // ── Kebijakan periode terkunci ──────────────────────────────────────────

    /** Penguncian periode diabaikan sepenuhnya. */
    public const LOCK_OFF = 'off';

    /** Karyawan tidak bisa; pemegang `general.purchase-request.manage` tetap bisa. */
    public const LOCK_BLOCK_EMPLOYEE = 'block_employee';

    /** Siapa pun tidak bisa. */
    public const LOCK_BLOCK_ALL = 'block_all';

    public const LOCK_POLICIES = [
        self::LOCK_OFF,
        self::LOCK_BLOCK_EMPLOYEE,
        self::LOCK_BLOCK_ALL,
    ];

    /** Cache per-request; bukan Cache facade, supaya perubahan langsung terasa. */
    private static ?self $cached = null;

    /**
     * Baris konfigurasi yang berlaku.
     *
     * Membuatkan baris default bila belum ada. Ini pengaman: migrasi sudah
     * menyisipkannya, tetapi database yang dipulihkan dari dump lama bisa saja
     * tidak punya, dan seluruh modul akan mati bila mengembalikan null.
     */
    public static function current(): self
    {
        if (self::$cached) {
            return self::$cached;
        }

        return self::$cached = self::first() ?? self::create([
            'locked_period_policy'      => self::LOCK_BLOCK_EMPLOYEE,
            'allowed_units'             => 'PC,UNIT,SET,BOX,LOT',
            'default_unit'              => 'PC',
            'allowed_cost_center_types' => PurchaseRequestItem::COST_CENTER_BRANCH
                                           . ',' . PurchaseRequestItem::COST_CENTER_PROJECT,
        ]);
    }

    /** Dipanggil setelah menyimpan perubahan agar pembacaan berikutnya segar. */
    public static function forgetCache(): void
    {
        self::$cached = null;
    }

    // ── Helpers ─────────────────────────────────────────────────────────────

    /** Nilai 0 berarti "tanpa batas", bukan "nol". Konsisten dengan Overtime. */
    public function hasBackdateLimit(): bool
    {
        return $this->max_backdate_days > 0;
    }

    public function hasItemLimit(): bool
    {
        return $this->max_items_per_request > 0;
    }

    public function hasQtyLimit(): bool
    {
        return (float) $this->max_qty_per_item > 0;
    }

    /**
     * Satuan yang boleh dipakai baris item (Keputusan D128).
     *
     * Disimpan CSV, bukan tabel master: nilainya tetap DATA yang dapat diubah
     * pemilik sistem — tuntutan D57 terpenuhi — hanya wadahnya lebih ringan.
     * Dinormalkan ke huruf besar supaya "pc" dan "PC" tidak menjadi dua satuan
     * berbeda saat diringkas di `qty_summary`.
     *
     * @return array<string>
     */
    public function unitOptions(): array
    {
        $units = collect(explode(',', (string) $this->allowed_units))
            ->map(fn ($unit) => strtoupper(trim($unit)))
            ->filter()
            ->unique()
            ->values()
            ->all();

        // Jaring pengaman: daftar kosong akan membuat setiap pengajuan ditolak
        // tanpa cara memperbaikinya dari layar mana pun.
        return $units !== [] ? $units : ['PC'];
    }

    /** Satuan bawaan baris baru; selalu salah satu dari unitOptions(). */
    public function defaultUnit(): string
    {
        $default = strtoupper(trim((string) $this->default_unit));
        $options = $this->unitOptions();

        return in_array($default, $options, true) ? $default : $options[0];
    }

    /**
     * Jenis pembebanan yang boleh dipilih (Keputusan D127).
     *
     * Katup pengaman: bila kelak hanya cabang yang dipakai, buang `project` dari
     * CSV ini dan dropdown proyek hilang — nol perubahan kode.
     *
     * @return array<string>
     */
    public function costCenterTypeOptions(): array
    {
        $types = collect(explode(',', (string) $this->allowed_cost_center_types))
            ->map(fn ($type) => strtolower(trim($type)))
            ->filter(fn ($type) => in_array($type, PurchaseRequestItem::COST_CENTER_TYPES, true))
            ->unique()
            ->values()
            ->all();

        return $types !== [] ? $types : [PurchaseRequestItem::COST_CENTER_BRANCH];
    }

    public function allowsCostCenterType(string $type): bool
    {
        return in_array($type, $this->costCenterTypeOptions(), true);
    }
}
